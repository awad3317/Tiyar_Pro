<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\Feature;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SentShipmentsFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected Office $officeA;
    protected Office $officeB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->seed(FeatureSeeder::class);

        $this->officeA = Office::create([
            'name'                  => 'مكتب الشحنات المفعلة',
            'branch_name'           => 'الفرع الرئيسي',
            'whatsapp_sender_phone' => '770991101',
            'httpsms_from_phone'    => '+967770991101',
            'httpsms_api_key'       => 'test-key-ship-a',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeB = Office::create([
            'name'                  => 'مكتب الشحنات المقفلة',
            'branch_name'           => 'فرع آخر',
            'whatsapp_sender_phone' => '770991102',
            'httpsms_from_phone'    => '+967770991102',
            'httpsms_api_key'       => 'test-key-ship-b',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);
    }

    public function test_sent_shipments_feature_is_seeded_and_disabled_by_default(): void
    {
        $feature = Feature::query()->where('code', 'sent_shipments')->first();

        $this->assertNotNull($feature);
        $this->assertSame('الشحنات المرسلة', $feature->name);
        $this->assertSame('general', $feature->category);

        $this->assertFalse($this->officeA->hasFeature('sent_shipments'));
        $this->assertFalse($this->officeB->hasFeature('sent_shipments'));
    }

    public function test_sent_shipments_is_gated_independently_from_outgoing_parcels(): void
    {
        // تفعيل الطرود الصادرة فقط بدون الشحنات المرسلة
        $this->officeA->syncFeaturesStatus(['outgoing_parcels']);
        $this->officeA->refreshFeaturesCache();

        $this->assertTrue($this->officeA->hasFeature('outgoing_parcels'));
        $this->assertFalse($this->officeA->hasFeature('sent_shipments'));

        // الطرود الصادرة تعمل بشكل طبيعي
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk();

        // الشحنات المرسلة محجوبة (403)
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.index'))
            ->assertStatus(403);

        $this->actingAs($this->officeA, 'office')
            ->post(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق تجريبي',
                'driver_phone' => '771234567',
                'parcel_ids'   => [],
            ])
            ->assertStatus(403);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.lookup'), ['code' => '1'])
            ->assertStatus(403);

        // الشريط السفلي واللوحة يعكسان حالة القفل
        $dashboardResponse = $this->actingAs($this->officeA, 'office')
            ->get(route('office.dashboard'))
            ->assertOk();

        $dashboardResponse->assertSee('الطرود الصادرة (الصادر)');
        $dashboardResponse->assertDontSee('الشحنات المرسلة (الكشوفات)');
        $dashboardResponse->assertDontSee('href="' . route('office.shipments.sent.index') . '"', false);
        $dashboardResponse->assertSee('ميزة الشحنات المرسلة غير مفعّلة لهذا المكتب');
    }

    public function test_office_with_sent_shipments_enabled_can_access_while_other_office_remains_locked(): void
    {
        $this->officeA->syncFeaturesStatus(['sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $this->assertTrue($this->officeA->hasFeature('sent_shipments'));
        $this->assertFalse($this->officeB->hasFeature('sent_shipments'));

        // المكتب المفعّل يفتح الشحنات المرسلة ويرى الرابط في الشريط السفلي ولوحة التحكم
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.index'))
            ->assertOk()
            ->assertSee('الشحنات المرسلة')
            ->assertSee('كشف شحنة جديد');

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertSee('الشحنات المرسلة (الكشوفات)')
            ->assertSee('href="' . route('office.shipments.sent.index') . '"', false);

        // المكتب الآخر يبقى مقفلاً على الشحنات المرسلة، لكن الشحنات المستلمة متاحة له
        $this->actingAs($this->officeB, 'office')
            ->get(route('office.shipments.sent.index'))
            ->assertStatus(403);

        $this->actingAs($this->officeB, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk();

        // عند إغلاق الميزة عن المكتب A يتم قفلها فوراً
        $this->officeA->syncFeaturesStatus([]);
        $this->officeA->refreshFeaturesCache();

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.index'))
            ->assertStatus(403);
    }

    public function test_dispatching_draft_shipment_cascades_in_transit_to_all_parcels_and_blocks_empty_shipment(): void
    {
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $p1 = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل شحنة 1',
            'sender_phone'          => '771100200',
            'recipient_name'        => 'مستلم شحنة 1',
            'recipient_phone'       => '772100200',
            'package_type'          => 'كرتون أبيض وسط',
            'receipt_number'        => '1',
            'cod_amount'            => 5000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $p2 = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل شحنة 2',
            'sender_phone'          => '771300400',
            'recipient_name'        => 'مستلم شحنة 2',
            'recipient_phone'       => '772300400',
            'package_type'          => 'كيس أزرق صغير',
            'receipt_number'        => '2',
            'cod_amount'            => 3000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // 1. إنشاء شحنة بحالة مسودة (draft) تضم الطردين
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الاختبار',
                'driver_phone' => '771500600',
                'parcel_ids'   => [$p1->id, $p2->id],
                'dispatch_now' => false,
            ])
            ->assertStatus(201)
            ->assertJson(['ok' => true]);

        $shipment = Shipment::query()->where('office_id', $this->officeA->id)->firstOrFail();
        $this->assertSame(ShipmentStatus::DRAFT, $shipment->status);
        $this->assertSame(OutgoingParcelStatus::PENDING, $p1->fresh()->status);
        $this->assertSame(OutgoingParcelStatus::PENDING, $p2->fresh()->status);

        // 2. إرسال الشحنة يحدّث الشحنة وجميع طرودها ذرياً إلى in_transit
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.dispatch', $shipment))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $shipment->refresh();
        $p1->refresh();
        $p2->refresh();

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->status);
        $this->assertNotNull($shipment->dispatched_at);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $p1->status);
        $this->assertNotNull($p1->dispatched_at);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $p2->status);
        $this->assertNotNull($p2->dispatched_at);

        // 3. تظهر الطرود والشحنة فوراً لدى الفرع المستلم في شاشة في الطريق وشاشة الشحنات المستلمة
        $this->actingAs($this->officeB, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertSee('مستلم شحنة 1')
            ->assertSee('مستلم شحنة 2');

        $this->actingAs($this->officeB, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee($shipment->shipment_number);

        // 4. منع إرسال شحنة فارغة (0 طرود)
        $emptyShipment = Shipment::create([
            'office_id'       => $this->officeA->id,
            'shipment_number' => '999900099',
            'driver_name'     => 'سائق فارغ',
            'driver_phone'    => '771900800',
            'parcels_count'   => 0,
            'status'          => ShipmentStatus::DRAFT,
        ]);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.dispatch', $emptyShipment))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(ShipmentStatus::DRAFT, $emptyShipment->fresh()->status);
    }
}
