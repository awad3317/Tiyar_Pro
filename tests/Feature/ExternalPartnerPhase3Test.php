<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\ShipmentStatus;
use App\Models\ExternalPartnerLink;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalPartnerPhase3Test extends TestCase
{
    use DatabaseTransactions;

    protected Office $receivingOffice;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['status' => 'ok', 'data' => ['id' => 'fake-msg']], 200),
        ]);

        $this->seed(FeatureSeeder::class);

        $this->receivingOffice = Office::create([
            'name'                  => 'مكتب الصقر الدولي',
            'branch_name'           => 'المكلا',
            'whatsapp_sender_phone' => '770930001',
            'httpsms_from_phone'    => '+967770930001',
            'httpsms_api_key'       => 'test-key-930001',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->receivingOffice->syncFeaturesStatus([
            'outgoing_parcels',
            'sent_shipments',
            'external_partner_links',
        ]);
        $this->receivingOffice->refreshFeaturesCache();
    }

    public function test_invalid_or_soft_deleted_token_returns_404_and_paused_states_show_paused_screen(): void
    {
        // 1. رابط غير موجود -> 404
        $this->get('/p/' . Str::random(64))->assertStatus(404);

        // 2. رابط محذوف ناعماً -> 404
        $deletedLink = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب محذوف',
            'token'          => Str::random(64),
            'receipt_prefix' => 'DEL-',
            'is_active'      => true,
        ]);
        $deletedLink->delete();

        $this->get('/p/' . $deletedLink->token)->assertStatus(404);

        // 3. رابط موقوف (is_active = false) -> شاشة الإيقاف ومنع الإرسال
        $inactiveLink = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب شبوة السريع',
            'partner_city'   => 'عتق',
            'token'          => Str::random(64),
            'receipt_prefix' => 'SHB-',
            'is_active'      => false,
        ]);

        $this->get('/p/' . $inactiveLink->token)
            ->assertOk()
            ->assertSee('هذا الرابط متوقف مؤقتاً من قِبل إدارة المكتب المستلم')
            ->assertSee('مكتب الصقر الدولي');

        $this->postJson('/p/' . $inactiveLink->token . '/shipments', [
            'idempotency_key' => (string) Str::uuid(),
            'driver_name'     => 'سالم',
            'driver_phone'    => '771234567',
            'parcels'         => [[
                'recipient_name'  => 'مستلم',
                'recipient_phone' => '772345678',
                'payment_type'    => 'paid',
                'cod_amount'      => 1000,
            ]],
        ])->assertStatus(403);

        // 4. رابط نشط لكن ميزة external_partner_links معطلة للمكتب المستلم -> شاشة الإيقاف
        $activeLink = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب مأرب',
            'token'          => Str::random(64),
            'receipt_prefix' => 'MRB-',
            'is_active'      => true,
        ]);

        $this->receivingOffice->syncFeaturesStatus([]);
        $this->receivingOffice->refreshFeaturesCache();

        $this->get('/p/' . $activeLink->token)
            ->assertOk()
            ->assertSee('هذا الرابط متوقف مؤقتاً من قِبل إدارة المكتب المستلم');
    }

    public function test_pin_verification_never_leaks_pin_and_enforces_5_attempts_rate_limit(): void
    {
        $lockedLink = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب محمي برمز',
            'partner_city'   => 'سيئون',
            'token'          => Str::random(64),
            'access_pin'     => '7391',
            'receipt_prefix' => 'SYN-',
            'is_active'      => true,
        ]);

        // شاشة الـ PIN لا تكشف قيمة access_pin مطلقاً في الاستجابة
        $this->get('/p/' . $lockedLink->token)
            ->assertOk()
            ->assertSee('أدخل رمز الدخول السري (PIN)')
            ->assertDontSee('7391');

        // لا يمكن الإرسال قبل التحقق من الـ PIN
        $this->postJson('/p/' . $lockedLink->token . '/shipments', [
            'idempotency_key' => (string) Str::uuid(),
            'driver_name'     => 'سائق',
            'driver_phone'    => '771234567',
            'parcels'         => [[
                'recipient_name'  => 'عميل',
                'recipient_phone' => '772345678',
                'payment_type'    => 'paid',
                'cod_amount'      => 500,
            ]],
        ])->assertStatus(403);

        // 4 محاولات خاطئة تعيد خطأ تحقق، والمحاولة الخامسة الخاطئة تفعل الحظر 429
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/p/' . $lockedLink->token . '/verify-pin', ['pin' => '0000'])
                ->assertStatus(422);
        }

        $this->postJson('/p/' . $lockedLink->token . '/verify-pin', ['pin' => '0000'])
            ->assertStatus(429);

        // حتى المحاولة السادسة بالرمز الصحيح تظل محظورة بـ 429 خلال فترة الحظر
        $this->postJson('/p/' . $lockedLink->token . '/verify-pin', ['pin' => '7391'])
            ->assertStatus(429);

        // رابط آخر مستقل: التحقق بنجاح مع تطبيع الأرقام العربية وفتح نموذج البوابة
        $validPinLink = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب الغيضة',
            'partner_city'   => 'الغيضة',
            'token'          => Str::random(64),
            'access_pin'     => '5824',
            'receipt_prefix' => 'GHD-',
            'is_active'      => true,
        ]);

        $this->post('/p/' . $validPinLink->token . '/verify-pin', ['pin' => '٥٨٢٤'])
            ->assertRedirect(route('partner.portal.show', ['token' => $validPinLink->token]));

        $this->get('/p/' . $validPinLink->token)
            ->assertOk()
            ->assertSee('إضافة طرد إلى الكشف')
            ->assertSee('اعتماد وإرسال الكشف');
    }

    public function test_full_shipment_submission_prefixes_receipts_updates_stats_sends_zero_sms_and_appears_in_office(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب البراق الخارجي',
            'partner_city'   => 'مأرب',
            'partner_phone'  => '771112223',
            'token'          => Str::random(64),
            'receipt_prefix' => 'MRB-',
            'is_active'      => true,
        ]);

        $otherLink = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب آخر',
            'token'          => Str::random(64),
            'receipt_prefix' => 'OTH-',
            'is_active'      => true,
        ]);

        $idempotencyKey = (string) Str::uuid();

        $response = $this->postJson('/p/' . $link->token . '/shipments', [
            'idempotency_key' => $idempotencyKey,
            'driver_name'     => 'أكرم السائق',
            'driver_phone'    => '٧٧١٢٣٤٥٦٧',
            'notes'           => 'يتحرك عصراً',
            'parcels'         => [
                [
                    'recipient_name'  => 'عبدالله المستلم',
                    'recipient_phone' => '+967772345678',
                    'payment_type'    => 'paid',
                    'cod_amount'      => '١٥٠٠',
                    'package_type'    => 'كرتون أدوية',
                    'receipt_number'  => '105',
                    'notes'           => 'يحفظ بعيداً عن الحرارة',
                ],
                [
                    'recipient_name'  => 'فؤاد التاجر',
                    'recipient_phone' => '٧٣٣٤٥٦٧٨٩',
                    'payment_type'    => 'deferred',
                    'cod_amount'      => 4000,
                    'package_type'    => 'قطع غيار',
                    'receipt_number'  => 'mrb-108',
                ],
                [
                    'recipient_name'  => 'سعيد العميل',
                    'recipient_phone' => '714567890',
                    'payment_type'    => 'paid',
                    'cod_amount'      => 500,
                    'receipt_number'  => '',
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $shipmentId = $response->json('shipment_id');
        $shipment = Shipment::findOrFail($shipmentId);

        // التحقق من بيانات الكشف المُنشأ
        $this->assertNull($shipment->office_id);
        $this->assertSame($link->id, $shipment->external_partner_link_id);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->status);
        $this->assertSame('771234567', $shipment->driver_phone);
        $this->assertSame(3, $shipment->parcels_count);
        $this->assertEquals(2000.0, (float) $shipment->total_paid_amount);
        $this->assertEquals(4000.0, (float) $shipment->total_deferred_amount);
        $this->assertEquals(6000.0, (float) $shipment->total_amount);

        // التحقق من الطرود وترقيم السندات المسبوق بالبادئة
        $parcels = OutgoingParcel::where('shipment_id', $shipment->id)
            ->orderBy('receipt_number')
            ->get();

        $this->assertCount(3, $parcels);
        $this->assertEqualsCanonicalizing(
            ['MRB-105', 'MRB-108', 'MRB-109'],
            $parcels->pluck('receipt_number')->all()
        );

        foreach ($parcels as $p) {
            $this->assertNull($p->office_id);
            $this->assertSame($link->id, $p->external_partner_link_id);
            $this->assertSame($this->receivingOffice->id, $p->destination_office_id);
            $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $p->status);
        }

        // عدم إطلاق أي إشعارات SMS أو واتساب عند الإرسال من البوابة
        Http::assertNothingSent();

        // تحديث عداد الشحنات وآخر استخدام للرابط
        $link->refresh();
        $this->assertSame(1, $link->shipments_count);
        $this->assertNotNull($link->last_used_at);

        // صفحة النجاح تعرض ملخص الكشف ومحمية من الوصول عبر توكن رابط آخر
        $this->get(route('partner.portal.shipments.success', [
            'token'    => $link->token,
            'shipment' => $shipment->id,
        ]))
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('MRB-105')
            ->assertSee('MRB-108')
            ->assertSee('MRB-109')
            ->assertSee('إرسال كشف جديد');

        $this->get(route('partner.portal.shipments.success', [
            'token'    => $otherLink->token,
            'shipment' => $shipment->id,
        ]))->assertStatus(404);

        // ظهور الشحنة مباشرة في شاشة «الشحنات المستلمة» لدى المكتب المستلم
        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('مكتب البراق الخارجي - مأرب');
    }

    public function test_idempotency_key_returns_existing_shipment_without_creating_duplicate(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب عتق',
            'partner_city'   => 'عتق',
            'token'          => Str::random(64),
            'receipt_prefix' => 'ATQ-',
            'is_active'      => true,
        ]);

        $payload = [
            'idempotency_key' => 'idem-' . Str::uuid(),
            'driver_name'     => 'ناجي السائق',
            'driver_phone'    => '775556667',
            'parcels'         => [[
                'recipient_name'  => 'خالد المستلم',
                'recipient_phone' => '781234567',
                'payment_type'    => 'deferred',
                'cod_amount'      => 2500,
                'package_type'    => 'أمانة',
            ]],
        ];

        $first = $this->postJson('/p/' . $link->token . '/shipments', $payload)
            ->assertOk();

        $second = $this->postJson('/p/' . $link->token . '/shipments', $payload)
            ->assertOk();

        $this->assertSame($first->json('shipment_id'), $second->json('shipment_id'));
        $this->assertSame($first->json('shipment_number'), $second->json('shipment_number'));

        $this->assertSame(1, Shipment::where('external_partner_link_id', $link->id)->count());
        $this->assertSame(1, OutgoingParcel::where('external_partner_link_id', $link->id)->count());
        $this->assertSame(1, $link->fresh()->shipments_count);
    }

    public function test_invalid_yemeni_phone_numbers_are_rejected(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب بيحان',
            'token'          => Str::random(64),
            'receipt_prefix' => 'BHN-',
            'is_active'      => true,
        ]);

        // رقم سائق يبدأ بـ 75 (غير يمني صالح)
        $this->postJson('/p/' . $link->token . '/shipments', [
            'idempotency_key' => (string) Str::uuid(),
            'driver_name'     => 'سائق',
            'driver_phone'    => '751234567',
            'parcels'         => [[
                'recipient_name'  => 'مستلم',
                'recipient_phone' => '771234567',
                'payment_type'    => 'paid',
                'cod_amount'      => 500,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['driver_phone']);

        // رقم مستلم من 8 أرقام فقط أو يبدأ بـ 79
        $this->postJson('/p/' . $link->token . '/shipments', [
            'idempotency_key' => (string) Str::uuid(),
            'driver_name'     => 'سائق',
            'driver_phone'    => '771234567',
            'parcels'         => [[
                'recipient_name'  => 'مستلم',
                'recipient_phone' => '791234567',
                'payment_type'    => 'paid',
                'cod_amount'      => 500,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parcels.0.recipient_phone']);

        $this->assertSame(0, Shipment::where('external_partner_link_id', $link->id)->count());
    }
}
