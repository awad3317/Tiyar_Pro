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

    public function test_sent_shipment_print_view_renders_formal_manifest_and_excludes_cancelled_parcels_from_totals(): void
    {
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $paidParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل محاسب',
            'sender_phone'          => '771111222',
            'recipient_name'        => 'مستلم محاسب',
            'recipient_phone'       => '772222333',
            'package_type'          => 'كرتون أبيض وسط',
            'receipt_number'        => '1',
            'cod_amount'            => 3000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'notes'                 => 'قابل للكسر',
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $deferredParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => null,
            'external_office_name'  => 'مكتب عدن الخارجي',
            'sender_name'           => 'مرسل آجل',
            'sender_phone'          => '773333444',
            'recipient_name'        => 'مستلم آجل',
            'recipient_phone'       => '774444555',
            'package_type'          => 'كيس ملابس كبير',
            'receipt_number'        => '2',
            'cod_amount'            => 5000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $cancelledParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل ملغي',
            'sender_phone'          => '775555666',
            'recipient_name'        => 'مستلم ملغي',
            'recipient_phone'       => '776666777',
            'package_type'          => 'ضرف مستبعد',
            'receipt_number'        => '3',
            'cod_amount'            => 9000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الطباعة',
                'driver_phone' => '771800900',
                'parcel_ids'   => [$paidParcel->id, $deferredParcel->id, $cancelledParcel->id],
                'dispatch_now' => true,
                'notes'        => 'تسليم عاجل',
            ])
            ->assertStatus(201);

        $shipment = Shipment::query()->where('office_id', $this->officeA->id)->firstOrFail();

        // 1. إخراج الطرد الثالث من الشحنة عبر شاشة الشحنة المرسلة ثم إلغاؤه
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.detach-parcel', [$shipment, $cancelledParcel]))
            ->assertOk()
            ->assertJson([
                'ok'        => true,
                'parcel_id' => $cancelledParcel->id,
                'status'    => 'pending',
            ]);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.outgoing-parcels.cancel', $cancelledParcel))
            ->assertOk();

        // 2. صفحة الطباعة الرسمية (توأم كشف المحاسب: 8 أعمدة + 3 مربعات مالية + عمودا توقيع) ترجع 200
        $printRes = $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.print', $shipment))
            ->assertOk();

        $printRes->assertSee('كشف شحنة صادرة')
            ->assertSee('#' . $shipment->shipment_number)
            ->assertSee('سائق الطباعة')
            ->assertSee('771800900')
            ->assertSee('رقم السند')
            ->assertSee('اسم المستلم')
            ->assertSee('رقم الجوال')
            ->assertSee('النوع')
            ->assertSee('الوجهة')
            ->assertSee('الدفع')
            ->assertSee('المبلغ (ر.ي)')
            ->assertSee('مستلم محاسب')
            ->assertSee('مستلم آجل')
            ->assertDontSee('مستلم ملغي')
            ->assertSee('تسليم عاجل')
            ->assertSee('إجمالي طرود الآجل')
            ->assertSee('إجمالي طرود المحاسب')
            ->assertSee('الإجمالي الكلي')
            ->assertSee('3,000 ر.ي')
            ->assertSee('5,000 ر.ي')
            ->assertSee('8,000 ر.ي')
            ->assertDontSee('14,000 ر.ي')
            ->assertDontSee('17,000 ر.ي')
            ->assertSee('توقيع الموظف')
            ->assertSee('توقيع السائق / المستلم')
            ->assertDontSee('الختم الرسمي')
            ->assertSee('size: A4 portrait;', false);

        // 3. صفحة تفاصيل الشحنة تفعّل الطباعة المباشرة (window.print) وتعرض زر «إخراج من الشحنة» للطرود غير المستلمة
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.show', $shipment))
            ->assertOk()
            ->assertSee('onclick="window.print()"', false)
            ->assertSee('إخراج من الشحنة')
            ->assertSee('توقيع السائق / المستلم');

        // 4. بعد استلام الطرد الأول بالفرع B، يُرفض إخراجه من صفحة الشحنة بـ 422
        $this->actingAs($this->officeB, 'office')
            ->postJson(route('office.transit-parcels.receive', $paidParcel))
            ->assertOk();

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.detach-parcel', [$shipment, $paidParcel]))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'لا يمكن إخراج الطرد لأنه تم استلامه بالفعل من الفرع المستلم.',
            ]);

        // 5. مكتب آخر لا يستطيع طباعة شحنة لا تخصه (403)
        $this->officeB->syncFeaturesStatus(['sent_shipments']);
        $this->officeB->refreshFeaturesCache();

        $this->actingAs($this->officeB, 'office')
            ->get(route('office.shipments.sent.print', $shipment))
            ->assertStatus(403);
    }

    public function test_cancelling_shipment_releases_all_parcels_back_to_pending_and_is_blocked_if_any_parcel_received(): void
    {
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        // --- السيناريو 1: شحنة جميع طرودها في الطريق (لا يوجد أي طرد مستلم) → ينجح الإلغاء وتعود الطرود للانتظار ---
        $p1 = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل إلغاء 1',
            'sender_phone'          => '771111901',
            'recipient_name'        => 'مستلم إلغاء 1',
            'recipient_phone'       => '772222901',
            'package_type'          => 'كرتون إلغاء 1',
            'receipt_number'        => '31',
            'cod_amount'            => 2000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $p2 = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل إلغاء 2',
            'sender_phone'          => '771111902',
            'recipient_name'        => 'مستلم إلغاء 2',
            'recipient_phone'       => '772222902',
            'package_type'          => 'كيس إلغاء 2',
            'receipt_number'        => '32',
            'cod_amount'            => 3500,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الكشف الملغي',
                'driver_phone' => '771999100',
                'parcel_ids'   => [$p1->id, $p2->id],
                'dispatch_now' => true,
            ])
            ->assertStatus(201);

        $cancellableShipment = Shipment::query()->where('office_id', $this->officeA->id)->latest('created_at')->firstOrFail();

        // قائمة الثلاث نقاط تعرض خيار «إلغاء الكشف» و«طباعة الكشف» و«تفاصيل الكشف»
        $sentIndexHtml = $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('more_vert', $sentIndexHtml);
        $this->assertStringContainsString('data-shipment-action="cancel"', $sentIndexHtml);
        $this->assertStringContainsString(route('office.shipments.sent.print', $cancellableShipment), $sentIndexHtml);

        // إلغاء الكشف يعيد جميع الطرود إلى pending ويفك ارتباطها ويخفيها من قائمة في الطريق لدى الفرع المستلم
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.cancel', $cancellableShipment))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(ShipmentStatus::CANCELLED, $cancellableShipment->fresh()->status);
        $this->assertNotNull($cancellableShipment->fresh()->cancelled_at);

        foreach ([$p1, $p2] as $released) {
            $released->refresh();
            $this->assertNull($released->shipment_id);
            $this->assertSame(OutgoingParcelStatus::PENDING, $released->status);
            $this->assertNull($released->dispatched_at);
        }

        $this->actingAs($this->officeB, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertDontSee('مستلم إلغاء 1')
            ->assertDontSee('مستلم إلغاء 2');

        // --- السيناريو 2: شحنة تم استلام طرد واحد منها بالفرع المستلم → يُحجب خيار الإلغاء ويُرفض بـ 422 ---
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الكشف المقفل',
                'driver_phone' => '771999200',
                'parcel_ids'   => [$p1->id, $p2->id],
                'dispatch_now' => true,
            ])
            ->assertStatus(201);

        $lockedShipment = Shipment::query()
            ->where('office_id', $this->officeA->id)
            ->where('id', '!=', $cancellableShipment->id)
            ->firstOrFail();

        // الفرع المستهدف يستلم الطرد الأول فقط
        $this->actingAs($this->officeB, 'office')
            ->postJson(route('office.transit-parcels.receive', $p1))
            ->assertOk();

        // محاولة إلغاء الكشف تُرفض بالرسالة الدقيقة وتبقى الشحنة وطرودها سليمة
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.cancel', $lockedShipment))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'لا يمكن إلغاء الكشف لوجود طرود تم استلامها بالفعل من الفرع المستلم.',
            ]);

        $this->assertSame(ShipmentStatus::PARTIALLY_RECEIVED, $lockedShipment->fresh()->status);
        $this->assertSame($lockedShipment->id, $p1->fresh()->shipment_id);
        $this->assertSame($lockedShipment->id, $p2->fresh()->shipment_id);
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $p1->fresh()->status);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $p2->fresh()->status);

        // خيار «إلغاء الكشف» مخفي في قائمة الكشوفات وشاشة التفاصيل للكشف الذي يحتوي على طرد مستلم
        $afterReceiveIndexHtml = $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.index', ['status' => 'partially_received']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($lockedShipment->shipment_number, $afterReceiveIndexHtml);
        $this->assertStringNotContainsString('data-shipment-action="cancel"', $afterReceiveIndexHtml);

        $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.show', $lockedShipment))
            ->assertOk()
            ->assertDontSee('إلغاء الكشف');
    }

    public function test_detached_parcel_from_in_transit_shipment_can_be_rescanned_and_dispatched_in_new_shipment(): void
    {
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $loadedParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل محمّل',
            'sender_phone'          => '771111801',
            'recipient_name'        => 'مستلم محمّل',
            'recipient_phone'       => '772222801',
            'package_type'          => 'كرتون محمّل',
            'receipt_number'        => '41',
            'cod_amount'            => 2500,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $forgottenParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل منسي',
            'sender_phone'          => '771111802',
            'recipient_name'        => 'مستلم منسي',
            'recipient_phone'       => '772222802',
            'package_type'          => 'كيس لم يُحمّل',
            'receipt_number'        => '42',
            'cod_amount'            => 4500,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // 1. إنشاء وشحن الكشف #1 (في الطريق) مع الطردين
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الكشف الأول',
                'driver_phone' => '771555111',
                'parcel_ids'   => [$loadedParcel->id, $forgottenParcel->id],
                'dispatch_now' => true,
            ])
            ->assertStatus(201);

        $shipment1 = Shipment::query()->where('office_id', $this->officeA->id)->latest('created_at')->firstOrFail();
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment1->status);

        // محاولة مسح الطرد وهو ما زال داخل الكشف #1 تُرفض
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.lookup'), ['code' => $forgottenParcel->id])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        // 2. إخراج الطرد الذي نسي السائق تحميله من الكشف #1 (وهو في الطريق)
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.detach-parcel', [$shipment1, $forgottenParcel]))
            ->assertOk()
            ->assertJson([
                'ok'        => true,
                'parcel_id' => $forgottenParcel->id,
                'status'    => 'pending',
            ]);

        $forgottenParcel->refresh();
        $this->assertNull($forgottenParcel->shipment_id);
        $this->assertSame(OutgoingParcelStatus::PENDING, $forgottenParcel->status);
        $this->assertNull($forgottenParcel->dispatched_at);

        $shipment1->refresh();
        $this->assertSame(1, $shipment1->parcels_count);
        $this->assertEquals(2500.0, (float) $shipment1->total_amount);

        // 3. مسح الطرد فوراً بالباركود لضمّه إلى كشف جديد ينجح بدون أي عائق
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.lookup'), ['code' => $forgottenParcel->id])
            ->assertOk()
            ->assertJson([
                'ok'     => true,
                'parcel' => [
                    'id'             => $forgottenParcel->id,
                    'receipt_number' => '42',
                ],
            ]);

        // 4. إنشاء وشحن الكشف #2 بالطرد المخرَج
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الكشف الثاني',
                'driver_phone' => '771555222',
                'parcel_ids'   => [$forgottenParcel->id],
                'dispatch_now' => true,
            ])
            ->assertStatus(201)
            ->assertJson(['ok' => true]);

        $shipment2 = Shipment::query()
            ->where('office_id', $this->officeA->id)
            ->where('id', '!=', $shipment1->id)
            ->firstOrFail();

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment2->status);
        $this->assertNotNull($shipment2->dispatched_at);
        $this->assertSame(1, $shipment2->parcels_count);
        $this->assertEquals(4500.0, (float) $shipment2->total_deferred_amount);

        $forgottenParcel->refresh();
        $this->assertSame($shipment2->id, $forgottenParcel->shipment_id);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $forgottenParcel->status);
        $this->assertNotNull($forgottenParcel->dispatched_at);

        // 5. الفرع المستهدف يرى الكشف #2 ويستلم الطرد منه بنجاح
        $this->actingAs($this->officeB, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee($shipment2->shipment_number);

        $this->actingAs($this->officeB, 'office')
            ->postJson(route('office.shipments.received.receive-parcel', $shipment2), [
                'code'    => $forgottenParcel->id,
                'channel' => 'sms',
            ])
            ->assertOk()
            ->assertJson([
                'ok'              => true,
                'parcel_id'       => $forgottenParcel->id,
                'shipment_status' => 'received',
            ]);
    }
}
