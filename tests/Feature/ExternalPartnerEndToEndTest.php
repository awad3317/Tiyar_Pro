<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\ParcelStatus;
use App\Enums\ShipmentStatus;
use App\Models\ExternalPartnerLink;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use App\Models\Shipment;
use App\Services\OutgoingParcelService;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalPartnerEndToEndTest extends TestCase
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
            'name'                  => 'مكتب النجم الدولي',
            'branch_name'           => 'صنعاء',
            'whatsapp_sender_phone' => '770940001',
            'httpsms_from_phone'    => '+967770940001',
            'httpsms_api_key'       => 'test-key-940001',
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

    public function test_full_end_to_end_external_partner_shipment_and_receiving_lifecycle(): void
    {
        // 1. إنشاء رابط المكتب الخارجي من داخل جلسة المكتب المشترك
        $this->actingAs($this->receivingOffice, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'مكتب البراق السريع',
                'partner_city'   => 'مأرب',
                'partner_phone'  => '771112223',
                'receipt_prefix' => 'mrb-',
                'access_pin'     => '2468',
            ])
            ->assertRedirect(route('office.external-partner-links.index'));

        $link = ExternalPartnerLink::where('office_id', $this->receivingOffice->id)->firstOrFail();
        $this->assertSame('MRB-', $link->receipt_prefix);

        // 2. فتح البوابة العامة والتحقق من الـ PIN ثم إرسال كشف يحتوي على طردين
        $this->post('/p/' . $link->token . '/verify-pin', ['pin' => '2468'])
            ->assertRedirect(route('partner.portal.show', ['token' => $link->token]));

        $submitResponse = $this->postJson('/p/' . $link->token . '/shipments', [
            'idempotency_key' => (string) Str::uuid(),
            'driver_name'     => 'منصور السائق',
            'driver_phone'    => '775554443',
            'notes'           => 'كشف مسائي مباشر',
            'parcels'         => [
                [
                    'recipient_name'  => 'نبيل المستلم الأول',
                    'recipient_phone' => '772111222',
                    'payment_type'    => 'paid',
                    'cod_amount'      => 1500,
                    'package_type'    => 'كرتون قطع غيار',
                    'receipt_number'  => '301',
                ],
                [
                    'recipient_name'  => 'رامي المستلم الثاني',
                    'recipient_phone' => '733222333',
                    'payment_type'    => 'deferred',
                    'cod_amount'      => 3000,
                    'package_type'    => 'طرد ملابس',
                    'receipt_number'  => '',
                ],
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $shipment = Shipment::findOrFail($submitResponse->json('shipment_id'));
        $parcels = OutgoingParcel::where('shipment_id', $shipment->id)
            ->orderBy('receipt_number')
            ->get();

        $this->assertCount(2, $parcels);
        $this->assertSame('MRB-301', $parcels[0]->receipt_number);
        $this->assertSame('MRB-302', $parcels[1]->receipt_number);

        // 3. التأكد من ظهور الكشف والطردين في «الشحنات المستلمة» و«في الطريق»، وعدم ظهورهما في «الطرود الصادرة»
        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertDontSee('MRB-301')
            ->assertDontSee('MRB-302')
            ->assertDontSee('نبيل المستلم الأول');

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('مكتب البراق السريع - مأرب')
            ->assertSee('data-reject-shipment="' . $shipment->id . '"', false);

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertSee('MRB-301')
            ->assertSee('MRB-302')
            ->assertSee('مكتب البراق السريع - مأرب');

        // 4. استلام الطرد الأول برقم السند (MRB-301) من شاشة استلام الكشف
        $receiveFirst = $this->actingAs($this->receivingOffice, 'office')
            ->postJson(route('office.shipments.received.receive-parcel', $shipment), [
                'code'    => 'MRB-301',
                'channel' => 'sms',
            ])
            ->assertOk()
            ->assertJson([
                'ok'                    => true,
                'receipt_number'        => 'MRB-301',
                'remaining_in_shipment' => 1,
                'shipment_status'       => ShipmentStatus::PARTIALLY_RECEIVED->value,
            ]);

        $incomingParcel1 = Parcel::findOrFail($receiveFirst->json('incoming_parcel_id'));
        $this->assertSame($this->receivingOffice->id, $incomingParcel1->office_id);
        $this->assertSame('MRB-301', $incomingParcel1->receipt_number);
        $this->assertSame('مكتب البراق السريع - مأرب', $incomingParcel1->source_office_name);
        $this->assertSame('مأرب', $incomingParcel1->source_region);
        $this->assertSame(ParcelStatus::InOffice, $incomingParcel1->status);
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $parcels[0]->fresh()->status);

        // 5. التأكد من منع رفض الكشف بعد استلام الطرد الأول واختفاء زر الرفض
        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertDontSee('data-reject-shipment="' . $shipment->id . '"', false);

        $this->actingAs($this->receivingOffice, 'office')
            ->postJson(route('office.shipments.received.reject', $shipment), [
                'rejection_reason' => 'محاولة رفض بعد استلام جزئي',
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertNull($shipment->fresh()->rejected_at);

        // 6. استلام الطرد الثاني يدوياً عبر receiveAtDestination واكتمال الشحنة بالكامل
        $incomingParcel2 = app(OutgoingParcelService::class)
            ->receiveAtDestination($parcels[1]->fresh(), $this->receivingOffice, false, 'sms');

        $this->assertSame('MRB-302', $incomingParcel2->receipt_number);
        $this->assertSame('مكتب البراق السريع - مأرب', $incomingParcel2->source_office_name);
        $this->assertSame('مأرب', $incomingParcel2->source_region);
        $this->assertSame(ShipmentStatus::RECEIVED, $shipment->fresh()->status);
    }
}
