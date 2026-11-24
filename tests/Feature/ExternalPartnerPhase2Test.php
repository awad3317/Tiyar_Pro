<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\ExternalPartnerLink;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use App\Services\OutgoingParcelService;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalPartnerPhase2Test extends TestCase
{
    use DatabaseTransactions;

    protected Office $officeA;
    protected Office $officeB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['status' => 'ok', 'data' => ['id' => 'fake-msg']], 200),
        ]);

        $this->seed(FeatureSeeder::class);

        $this->officeA = Office::create([
            'name'                  => 'مكتب الأمانة السريع',
            'branch_name'           => 'صنعاء',
            'whatsapp_sender_phone' => '770920001',
            'httpsms_from_phone'    => '+967770920001',
            'httpsms_api_key'       => 'test-key-920001',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeB = Office::create([
            'name'                  => 'مكتب الخليج للنقل',
            'branch_name'           => 'عدن',
            'whatsapp_sender_phone' => '770920002',
            'httpsms_from_phone'    => '+967770920002',
            'httpsms_api_key'       => 'test-key-920002',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeA->syncPartnerOffices([$this->officeB->id]);
    }

    public function test_feature_gate_blocks_access_and_hides_menu_when_feature_disabled(): void
    {
        $this->assertFalse($this->officeA->hasFeature('external_partner_links'));

        // الرابط الجانبي/العلوي مخفي عند تعطيل الميزة
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertDontSee(route('office.external-partner-links.index'));

        // منع الوصول لمسارات إدارة الروابط بـ 403
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.external-partner-links.index'))
            ->assertStatus(403);

        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'مكتب تجريبي',
                'receipt_prefix' => 'TST-',
            ])
            ->assertStatus(403);

        // عند التفعيل يظهر عنصر القائمة وتعمل الشاشة
        $this->officeA->syncFeaturesStatus(['external_partner_links']);
        $this->officeA->refreshFeaturesCache();

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertSee('روابط المكاتب الخارجية')
            ->assertSee(route('office.external-partner-links.index'));

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.external-partner-links.index'))
            ->assertOk()
            ->assertSee('روابط المكاتب الخارجية')
            ->assertSee('إضافة مكتب خارجي');
    }

    public function test_crud_prefix_uniqueness_soft_delete_reuse_toggle_and_token_regeneration(): void
    {
        $this->officeA->syncFeaturesStatus(['external_partner_links']);
        $this->officeA->refreshFeaturesCache();

        $this->officeB->syncFeaturesStatus(['external_partner_links']);
        $this->officeB->refreshFeaturesCache();

        // 1. التحقق من رفض رمز PIN غير مكون من 4 أرقام
        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'مكتب البراق',
                'receipt_prefix' => 'mrb-',
                'access_pin'     => '123',
            ])
            ->assertSessionHasErrorsIn('partnerLink', ['access_pin']);

        // 2. إنشاء رابط سليم مع تحويل البادئة تلقائياً لأحرف كبيرة ودعم الأرقام العربية في الـ PIN
        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'مكتب البراق',
                'partner_city'   => 'مأرب',
                'partner_phone'  => '771234567',
                'receipt_prefix' => 'mrb-',
                'access_pin'     => '٤٨٢٩',
            ])
            ->assertRedirect(route('office.external-partner-links.index'));

        $link = ExternalPartnerLink::where('office_id', $this->officeA->id)->firstOrFail();
        $this->assertSame('MRB-', $link->receipt_prefix);
        $this->assertSame('4829', $link->access_pin);
        $this->assertSame(64, strlen($link->token));
        $this->assertTrue($link->is_active);
        $oldToken = $link->token;

        // ظهور بيانات البطاقة ورابط البوابة في الصفحة
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.external-partner-links.index'))
            ->assertOk()
            ->assertSee('مكتب البراق')
            ->assertSee('MRB-')
            ->assertSee('4829')
            ->assertSee('/p/' . $oldToken);

        // 3. منع تكرار نفس البادئة لنفس المكتب طالما الرابط غير محذوف
        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'مكتب مأرب آخر',
                'receipt_prefix' => 'MRB-',
            ])
            ->assertSessionHasErrorsIn('partnerLink', ['receipt_prefix']);

        // 4. السماح لمكتب آخر مختلف باستخدام نفس البادئة MRB-
        $this->actingAs($this->officeB, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'شريك عدن بمأرب',
                'receipt_prefix' => 'MRB-',
            ])
            ->assertRedirect(route('office.external-partner-links.index'));

        // 5. تعديل بيانات الرابط مع الإبقاء على نفس البادئة وإزالة الـ PIN
        $this->actingAs($this->officeA, 'office')
            ->put(route('office.external-partner-links.update', $link), [
                'partner_name'   => 'مكتب البراق الحديث',
                'partner_city'   => 'مأرب - الوادي',
                'partner_phone'  => '779998887',
                'receipt_prefix' => 'MRB-',
                'access_pin'     => '',
            ])
            ->assertRedirect(route('office.external-partner-links.index'));

        $link->refresh();
        $this->assertSame('مكتب البراق الحديث', $link->partner_name);
        $this->assertSame('مأرب - الوادي', $link->partner_city);
        $this->assertNull($link->access_pin);

        // 6. إيقاف ثم إعادة تفعيل الرابط
        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.toggle-active', $link))
            ->assertRedirect(route('office.external-partner-links.index'));
        $this->assertFalse($link->fresh()->is_active);

        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.toggle-active', $link))
            ->assertRedirect(route('office.external-partner-links.index'));
        $this->assertTrue($link->fresh()->is_active);

        // 7. تدوير الرابط (Regenerate Token)
        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.regenerate-token', $link))
            ->assertRedirect(route('office.external-partner-links.index'));

        $link->refresh();
        $this->assertSame(64, strlen($link->token));
        $this->assertNotSame($oldToken, $link->token);

        // 8. الحذف الناعم وإعادة استخدام نفس البادئة MRB- لنفس المكتب بنجاح
        $this->actingAs($this->officeA, 'office')
            ->delete(route('office.external-partner-links.destroy', $link))
            ->assertRedirect(route('office.external-partner-links.index'));

        $this->assertSoftDeleted('external_partner_links', ['id' => $link->id]);

        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.store'), [
                'partner_name'   => 'مكتب البراق الجديد بعد الحذف',
                'receipt_prefix' => 'MRB-',
            ])
            ->assertRedirect(route('office.external-partner-links.index'));

        $this->assertSame(
            1,
            ExternalPartnerLink::where('office_id', $this->officeA->id)
                ->where('receipt_prefix', 'MRB-')
                ->count()
        );
    }

    public function test_strict_data_isolation_prevents_office_from_modifying_another_offices_link(): void
    {
        $this->officeA->syncFeaturesStatus(['external_partner_links']);
        $this->officeA->refreshFeaturesCache();

        $this->officeB->syncFeaturesStatus(['external_partner_links']);
        $this->officeB->refreshFeaturesCache();

        $officeBLink = ExternalPartnerLink::create([
            'office_id'      => $this->officeB->id,
            'partner_name'   => 'مكتب خاص بعدن',
            'partner_city'   => 'عتق',
            'token'          => Str::random(64),
            'receipt_prefix' => 'ATQ-',
            'is_active'      => true,
        ]);

        // مكتب A لا يرى رابط مكتب B في قائمته
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.external-partner-links.index'))
            ->assertOk()
            ->assertDontSee('مكتب خاص بعدن');

        // جميع محاولات التعديل أو الإيقاف أو التدوير أو الحذف تُرفض بـ 403
        $this->actingAs($this->officeA, 'office')
            ->put(route('office.external-partner-links.update', $officeBLink), [
                'partner_name'   => 'اختراق',
                'receipt_prefix' => 'HCK-',
            ])
            ->assertStatus(403);

        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.toggle-active', $officeBLink))
            ->assertStatus(403);

        $this->actingAs($this->officeA, 'office')
            ->post(route('office.external-partner-links.regenerate-token', $officeBLink))
            ->assertStatus(403);

        $this->actingAs($this->officeA, 'office')
            ->delete(route('office.external-partner-links.destroy', $officeBLink))
            ->assertStatus(403);

        $officeBLink->refresh();
        $this->assertSame('مكتب خاص بعدن', $officeBLink->partner_name);
        $this->assertTrue($officeBLink->is_active);
        $this->assertNull($officeBLink->deleted_at);
    }

    public function test_reject_external_shipment_succeeds_when_unreceived_and_blocks_partial_or_internal_shipments(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->officeA->id,
            'partner_name'   => 'مكتب الصقر الخارجي',
            'partner_city'   => 'مأرب',
            'token'          => Str::random(64),
            'receipt_prefix' => 'SQR-',
            'is_active'      => true,
        ]);

        // 1. كشف خارجي لم يُستلم منه أي طرد -> يظهر زر الرفض وينجح الرفض
        $unreceivedExternal = Shipment::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_number'          => 'EXT-ALLOW-01',
            'driver_name'              => 'سالم السائق',
            'driver_phone'             => '771112223',
            'parcels_count'            => 1,
            'total_paid_amount'        => 2000,
            'total_deferred_amount'    => 0,
            'total_amount'             => 2000,
            'status'                   => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        OutgoingParcel::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_id'              => $unreceivedExternal->id,
            'destination_office_id'    => $this->officeA->id,
            'receipt_number'           => 'SQR-101',
            'sender_name'              => 'مرسل 1',
            'sender_phone'             => '771000111',
            'recipient_name'           => 'مستلم 1',
            'recipient_phone'          => '772000111',
            'package_type'             => 'كرتون',
            'payment_type'             => OutgoingPaymentType::PAID,
            'cod_amount'               => 2000,
            'status'                   => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee('EXT-ALLOW-01')
            ->assertSee('data-reject-shipment="' . $unreceivedExternal->id . '"', false);

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.received.show', $unreceivedExternal))
            ->assertOk()
            ->assertSee('رفض الكشف');

        // مكتب B غير الموجّه إليه الكشف يُمنع من رفضه
        $this->actingAs($this->officeB, 'office')
            ->postJson(route('office.shipments.received.reject', $unreceivedExternal), [
                'rejection_reason' => 'محاولة من مكتب آخر',
            ])
            ->assertStatus(422);
        $this->assertNull($unreceivedExternal->fresh()->rejected_at);

        // رفض ناجح من المكتب الموجّه إليه الكشف (officeA)
        $this->actingAs($this->officeA, 'office')
            ->post(route('office.shipments.received.reject', $unreceivedExternal), [
                'rejection_reason' => 'كشف مكرر بالخطأ',
            ])
            ->assertRedirect(route('office.shipments.received.index'));

        $unreceivedExternal->refresh();
        $this->assertNotNull($unreceivedExternal->rejected_at);
        $this->assertSame('كشف مكرر بالخطأ', $unreceivedExternal->rejection_reason);

        // يختفي فوراً من قائمة بطاقات الشحنات المستلمة ويعطي 404 عند فتحه
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee('تم رفض الكشف الخارجي #EXT-ALLOW-01')
            ->assertDontSee(route('office.shipments.received.show', $unreceivedExternal));

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.received.show', $unreceivedExternal))
            ->assertStatus(404);

        // 2. كشف خارجي تم استلام طرد واحد منه -> يُمنع رفضه ويختفي زر الرفض
        $partialExternal = Shipment::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_number'          => 'EXT-PARTIAL-02',
            'driver_name'              => 'محسن السائق',
            'driver_phone'             => '773334445',
            'parcels_count'            => 2,
            'total_paid_amount'        => 4000,
            'total_deferred_amount'    => 0,
            'total_amount'             => 4000,
            'status'                   => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        $p1 = OutgoingParcel::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_id'              => $partialExternal->id,
            'destination_office_id'    => $this->officeA->id,
            'receipt_number'           => 'SQR-201',
            'sender_name'              => 'مرسل أ',
            'sender_phone'             => '771000222',
            'recipient_name'           => 'مستلم أ',
            'recipient_phone'          => '772000222',
            'package_type'             => 'طرد 1',
            'payment_type'             => OutgoingPaymentType::PAID,
            'cod_amount'               => 2000,
            'status'                   => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        OutgoingParcel::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_id'              => $partialExternal->id,
            'destination_office_id'    => $this->officeA->id,
            'receipt_number'           => 'SQR-202',
            'sender_name'              => 'مرسل ب',
            'sender_phone'             => '771000333',
            'recipient_name'           => 'مستلم ب',
            'recipient_phone'          => '772000333',
            'package_type'             => 'طرد 2',
            'payment_type'             => OutgoingPaymentType::PAID,
            'cod_amount'               => 2000,
            'status'                   => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        // استلام الطرد الأول فقط
        app(OutgoingParcelService::class)->receiveAtDestination($p1, $this->officeA, 'sms', false);

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee('EXT-PARTIAL-02')
            ->assertDontSee('data-reject-shipment="' . $partialExternal->id . '"', false);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.received.reject', $partialExternal), [
                'rejection_reason' => 'محاولة بعد الاستلام',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['ok' => false]);

        $this->assertNull($partialExternal->fresh()->rejected_at);

        // 3. كشف داخلي مرسل من مكتب مشترك (officeB) -> يُمنع رفضه نهائياً
        $internalShipment = Shipment::create([
            'office_id'             => $this->officeB->id,
            'shipment_number'       => '26112499',
            'driver_name'           => 'قائد السائق',
            'driver_phone'          => '775556667',
            'parcels_count'         => 1,
            'total_paid_amount'     => 1000,
            'total_deferred_amount' => 0,
            'total_amount'          => 1000,
            'status'                => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        OutgoingParcel::create([
            'office_id'             => $this->officeB->id,
            'shipment_id'           => $internalShipment->id,
            'destination_office_id' => $this->officeA->id,
            'receipt_number'        => 'INT-901',
            'sender_name'           => 'مرسل داخلي',
            'sender_phone'          => '771999888',
            'recipient_name'        => 'مستلم داخلي',
            'recipient_phone'       => '772999888',
            'package_type'          => 'مظروف',
            'payment_type'          => OutgoingPaymentType::PAID,
            'cod_amount'            => 1000,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee('26112499')
            ->assertDontSee('data-reject-shipment="' . $internalShipment->id . '"', false);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.received.reject', $internalShipment), [
                'rejection_reason' => 'محاولة رفض كشف داخلي',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['ok' => false]);

        $this->assertNull($internalShipment->fresh()->rejected_at);
    }
}
