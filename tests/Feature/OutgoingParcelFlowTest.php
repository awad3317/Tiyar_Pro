<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ParcelStatus;
use App\Enums\ShipmentStatus;
use App\Models\Feature;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use App\Models\Shipment;
use App\Services\OutgoingParcelService;
use App\Services\ShipmentService;
use Database\Seeders\FeatureSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OutgoingParcelFlowTest extends TestCase
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

        // تشغيل FeatureSeeder لضمان وجود ميزة outgoing_parcels
        $this->seed(FeatureSeeder::class);

        $this->officeA = Office::create([
            'name'                  => 'مكتب صنعاء الرئيسي (اختبار)',
            'branch_name'           => 'صنعاء - حدة',
            'whatsapp_sender_phone' => '770100201',
            'httpsms_from_phone'    => '+967770100201',
            'httpsms_api_key'       => 'test-api-key-a',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeB = Office::create([
            'name'                  => 'مكتب عدن (اختبار)',
            'branch_name'           => 'عدن - المنصورة',
            'whatsapp_sender_phone' => '770100202',
            'httpsms_from_phone'    => '+967770100202',
            'httpsms_api_key'       => 'test-api-key-b',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        // ربط المكتبين ثنائياً للسماح بالإرسال الداخلي بينهما
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
    }

    public function test_outgoing_parcels_feature_is_seeded_as_disabled_by_default(): void
    {
        $feature = Feature::query()->where('code', 'outgoing_parcels')->first();

        $this->assertNotNull($feature);
        $this->assertSame('الطرود الصادرة', $feature->name);
        $this->assertSame('general', $feature->category);

        // يجب أن تكون معطلة افتراضياً لجميع المكاتب
        $this->assertFalse($this->officeA->hasFeature('outgoing_parcels'));
        $this->assertFalse($this->officeB->hasFeature('outgoing_parcels'));

        $this->assertDatabaseHas('office_features', [
            'office_id'  => $this->officeA->id,
            'feature_id' => $feature->id,
            'is_enabled' => false,
        ]);
    }

    public function test_office_feature_middleware_blocks_when_disabled_and_allows_when_enabled(): void
    {
        Route::middleware(['web', 'auth:office', 'office.feature:outgoing_parcels'])
            ->get('/_test/outgoing-parcels-gate', fn () => response()->json(['ok' => true]));

        // 1. الميزة معطلة → 403 لطلبات JSON و HTML
        $this->actingAs($this->officeA, 'office')
            ->getJson('/_test/outgoing-parcels-gate')
            ->assertStatus(403)
            ->assertJson(['message' => 'هذه الميزة غير مفعّلة لهذا المكتب.']);

        $this->actingAs($this->officeA, 'office')
            ->get('/_test/outgoing-parcels-gate')
            ->assertStatus(403);

        // 2. تفعيل الميزة للمكتب → 200 OK
        $this->officeA->syncFeaturesStatus(['outgoing_parcels']);
        $this->officeA->refreshFeaturesCache();

        $this->assertTrue($this->officeA->hasFeature('outgoing_parcels'));

        $this->actingAs($this->officeA, 'office')
            ->getJson('/_test/outgoing-parcels-gate')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_full_outgoing_to_incoming_parcel_conversion_flow(): void
    {
        $service = app(OutgoingParcelService::class);

        // 1. المكتب أ ينشئ طرداً صادراً (آجل - deferred) → يأخذ رقم السند التلقائي '1'
        $outgoing = $service->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'أحمد المرسل',
            'sender_phone'          => '771111111',
            'recipient_name'        => 'خالد المستلم',
            'recipient_phone'       => '772222222',
            'package_type'          => 'كرتون أبيض وسط',
            'cod_amount'            => 5500,
            'payment_type'          => 'deferred',
            'notes'                 => 'قابل للكسر',
        ]);

        $this->assertSame('1', $outgoing->receipt_number);
        $this->assertSame(OutgoingParcelStatus::PENDING, $outgoing->status);
        $this->assertSame(OutgoingPaymentType::DEFERRED, $outgoing->payment_type);
        $this->assertFalse($outgoing->isExternal());
        $this->assertSame('مكتب عدن (اختبار) - عدن - المنصورة', $outgoing->destinationLabel());
        $this->assertNull($outgoing->linked_incoming_parcel_id);

        // إنشاء طرد صادر ثانٍ للمكتب أ (محاسب - paid) → يأخذ رقم السند التلقائي '2'
        $outgoingSecond = $service->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل ثانٍ',
            'sender_phone'          => '771999999',
            'recipient_phone'       => '772999999',
            'package_type'          => 'كيس أبيض صغير',
            'cod_amount'            => 3000,
            'payment_type'          => 'paid',
        ]);
        $this->assertSame('2', $outgoingSecond->receipt_number);
        $this->assertSame(OutgoingPaymentType::PAID, $outgoingSecond->payment_type);

        // إنشاء طرد صادر للمكتب ب → يبدأ ترقيمه المستقل من '1'
        $outgoingOfficeB = $service->create($this->officeB, [
            'destination_office_id' => $this->officeA->id,
            'sender_name'           => 'مرسل من عدن',
            'sender_phone'          => '773888888',
            'recipient_phone'       => '774888888',
            'package_type'          => 'ضرف كاكي وسط',
            'cod_amount'            => 1500,
            'payment_type'          => 'paid',
        ]);
        $this->assertSame('1', $outgoingOfficeB->receipt_number);

        // 2. يُمنع تحويل الطرد بشكل فردي إلى (في الطريق)؛ يجب ضمه إلى شحنة وإرسال الشحنة بالكامل
        try {
            $service->markInTransit($outgoing, $this->officeA);
            $this->fail('Expected DomainException when dispatching an outgoing parcel individually.');
        } catch (DomainException $e) {
            $this->assertSame(
                'لا يمكن تحويل الطرد إلى "في الطريق" بشكل فردي؛ يجب ضمه إلى شحنة وإرسال الشحنة بالكامل.',
                $e->getMessage()
            );
        }
        $this->assertSame(OutgoingParcelStatus::PENDING, $outgoing->fresh()->status);
        $this->assertNull($outgoing->fresh()->dispatched_at);

        // 3. ضم الطردين في كشف شحنة (مسودة أولاً) ثم إرسال الشحنة بالكامل
        $shipmentService = app(ShipmentService::class);
        $shipment = $shipmentService->create($this->officeA, [
            'driver_name'  => 'سائق الخط السريع',
            'driver_phone' => '771555666',
            'parcel_ids'   => [$outgoing->id, $outgoingSecond->id],
            'dispatch_now' => false,
        ]);

        $this->assertSame(ShipmentStatus::DRAFT, $shipment->status);
        $this->assertSame(OutgoingParcelStatus::PENDING, $outgoing->fresh()->status);
        $this->assertSame(OutgoingParcelStatus::PENDING, $outgoingSecond->fresh()->status);
        $this->assertSame($shipment->id, $outgoing->fresh()->shipment_id);

        // إرسال الشحنة يحوّل جميع طرودها دفعة واحدة إلى in_transit مع dispatched_at
        $shipment = $shipmentService->dispatch($shipment, $this->officeA);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->status);
        $this->assertNotNull($shipment->dispatched_at);

        $outgoing->refresh();
        $outgoingSecond->refresh();
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $outgoing->status);
        $this->assertNotNull($outgoing->dispatched_at);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $outgoingSecond->status);
        $this->assertNotNull($outgoingSecond->dispatched_at);

        // 4. المكتب ب يستلم الطرد الآجل → ينتقل المبلغ (5500) إلى parcels.cod_amount لتحصيله عند التسليم
        $incomingParcel = $service->receiveAtDestination($outgoing, $this->officeB);

        $this->assertInstanceOf(Parcel::class, $incomingParcel);
        $this->assertDatabaseHas('parcels', [
            'id'                 => $incomingParcel->id,
            'office_id'          => $this->officeB->id,
            'recipient_name'     => 'خالد المستلم',
            'recipient_phone'    => '772222222',
            'package_type'       => 'كرتون أبيض وسط',
            'receipt_number'     => '1',
            'source_office_id'   => $this->officeA->id,
            'source_office_name' => 'مكتب صنعاء الرئيسي (اختبار) - صنعاء - حدة',
            'source_region'      => 'صنعاء - حدة',
            'status'             => ParcelStatus::InOffice->value,
        ]);
        $this->assertEquals(5500.0, (float) $incomingParcel->cod_amount);

        // التحقق من تحديث الطرد الصادر وربطه بالطرد الوارد وحالة الشحنة (استلام جزئي)
        $outgoing->refresh();
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $outgoing->status);
        $this->assertNotNull($outgoing->received_at);
        $this->assertSame($incomingParcel->id, $outgoing->linked_incoming_parcel_id);
        $this->assertSame($incomingParcel->id, $outgoing->linkedIncomingParcel->id);
        $this->assertSame(ShipmentStatus::PARTIALLY_RECEIVED, $shipment->fresh()->status);

        // 5. استلام الطرد الثاني (محاسب - paid) → يجب أن يصبح parcels.cod_amount = null وتكتمل الشحنة
        $paidIncomingParcel = $service->receiveAtDestination($outgoingSecond, $this->officeB);

        $this->assertNull($paidIncomingParcel->cod_amount);
        $this->assertDatabaseHas('parcels', [
            'id'             => $paidIncomingParcel->id,
            'office_id'      => $this->officeB->id,
            'receipt_number' => '2',
            'cod_amount'     => null,
        ]);
        $this->assertSame(ShipmentStatus::RECEIVED, $shipment->fresh()->status);

        // التحقق من تشغيل مسار إشعار الوصول (httpSMS)
        $this->assertSame('pending', $incomingParcel->sms_status);
        Http::assertSentCount(2);
    }

    public function test_receiving_same_outgoing_parcel_twice_fails_without_creating_duplicate(): void
    {
        $service = app(OutgoingParcelService::class);
        $shipmentService = app(ShipmentService::class);

        $outgoing = $service->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'سالم المرسل',
            'sender_phone'          => '773333333',
            'recipient_name'        => 'علي المستلم',
            'recipient_phone'       => '774444444',
            'package_type'          => 'ضرف أبيض صغير',
            'cod_amount'            => 2000,
            'payment_type'          => 'paid',
        ]);

        $this->assertSame('1', $outgoing->receipt_number);

        $shipmentService->create($this->officeA, [
            'driver_name'  => 'سائق الاستلام',
            'driver_phone' => '771666777',
            'parcel_ids'   => [$outgoing->id],
            'dispatch_now' => true,
        ]);
        $outgoing->refresh();

        $firstParcel = $service->receiveAtDestination($outgoing, $this->officeB);
        $countAfterFirst = Parcel::query()
            ->where('office_id', $this->officeB->id)
            ->where('source_office_id', $this->officeA->id)
            ->where('receipt_number', '1')
            ->count();

        $this->assertSame(1, $countAfterFirst);

        // محاولة الاستلام مرة ثانية يجب أن تفشل بدون إنشاء طرد مكرر
        try {
            $service->receiveAtDestination($outgoing, $this->officeB);
            $this->fail('Expected DomainException when receiving the same outgoing parcel twice.');
        } catch (DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $countAfterSecond = Parcel::query()
            ->where('office_id', $this->officeB->id)
            ->where('source_office_id', $this->officeA->id)
            ->where('receipt_number', '1')
            ->count();

        $this->assertSame(1, $countAfterSecond);
        $this->assertSame($firstParcel->id, $outgoing->fresh()->linked_incoming_parcel_id);
    }

    public function test_cancelling_outgoing_parcel_after_received_is_rejected(): void
    {
        $service = app(OutgoingParcelService::class);
        $shipmentService = app(ShipmentService::class);

        $outgoing = $service->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'فهد المرسل',
            'sender_phone'          => '775555555',
            'recipient_phone'       => '776666666',
            'package_type'          => 'كيس ملابس',
            'cod_amount'            => 1000,
            'payment_type'          => 'paid',
        ]);

        $shipmentService->create($this->officeA, [
            'driver_name'  => 'سائق الإلغاء',
            'driver_phone' => '771777888',
            'parcel_ids'   => [$outgoing->id],
            'dispatch_now' => true,
        ]);
        $outgoing->refresh();

        $service->receiveAtDestination($outgoing, $this->officeB);

        $this->expectException(DomainException::class);

        try {
            $service->cancel($outgoing, $this->officeA);
        } finally {
            $outgoing->refresh();
            $this->assertSame(OutgoingParcelStatus::RECEIVED, $outgoing->status);
            $this->assertNull($outgoing->cancelled_at);
        }
    }

    public function test_empty_draft_shipment_cannot_be_dispatched(): void
    {
        $shipment = Shipment::create([
            'office_id'       => $this->officeA->id,
            'shipment_number' => '999900001',
            'driver_name'     => 'سائق شحنة فارغة',
            'driver_phone'    => '771888999',
            'parcels_count'   => 0,
            'status'          => ShipmentStatus::DRAFT,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لا يمكن إرسال شحنة فارغة');

        try {
            app(ShipmentService::class)->dispatch($shipment, $this->officeA);
        } finally {
            $this->assertSame(ShipmentStatus::DRAFT, $shipment->fresh()->status);
            $this->assertNull($shipment->fresh()->dispatched_at);
        }
    }

    public function test_cannot_cancel_parcel_while_attached_to_shipment_and_must_detach_first_before_receipt(): void
    {
        $outgoingService = app(OutgoingParcelService::class);
        $shipmentService = app(ShipmentService::class);

        $p1 = $outgoingService->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل 1',
            'sender_phone'          => '771100111',
            'recipient_name'        => 'مستلم 1',
            'recipient_phone'       => '772100111',
            'package_type'          => 'كرتون وسط',
            'cod_amount'            => 3000,
            'payment_type'          => 'paid',
        ]);

        $p2 = $outgoingService->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل 2',
            'sender_phone'          => '771200222',
            'recipient_name'        => 'مستلم 2',
            'recipient_phone'       => '772200222',
            'package_type'          => 'كيس كبير',
            'cod_amount'            => 5000,
            'payment_type'          => 'deferred',
        ]);

        // 1. ضم الطردين في شحنة مسودة (draft) → يُرفض الإلغاء المباشر
        $shipment = $shipmentService->create($this->officeA, [
            'driver_name'  => 'سائق القواعد',
            'driver_phone' => '771300333',
            'parcel_ids'   => [$p1->id, $p2->id],
            'dispatch_now' => false,
        ]);

        try {
            $outgoingService->cancel($p1->fresh(), $this->officeA);
            $this->fail('Expected DomainException when cancelling parcel attached to draft shipment.');
        } catch (DomainException $e) {
            $this->assertSame('لا يمكن إلغاء الطرد وهو ضمن شحنة؛ يرجى إخراجه من الشحنة أولاً.', $e->getMessage());
        }

        // 2. إرسال الشحنة (in_transit) → ما زال الإلغاء المباشر مرفوضاً
        $shipment = $shipmentService->dispatch($shipment, $this->officeA);

        try {
            $outgoingService->cancel($p1->fresh(), $this->officeA);
            $this->fail('Expected DomainException when cancelling parcel attached to in_transit shipment.');
        } catch (DomainException $e) {
            $this->assertSame('لا يمكن إلغاء الطرد وهو ضمن شحنة؛ يرجى إخراجه من الشحنة أولاً.', $e->getMessage());
        }

        // 3. إخراج الطرد الأول من الشحنة يعيده إلى pending ويفك ارتباطه ويحدث مجاميع الشحنة
        $detached = $shipmentService->detachParcel($shipment, $p1->fresh(), $this->officeA);
        $this->assertNull($detached->shipment_id);
        $this->assertSame(OutgoingParcelStatus::PENDING, $detached->status);
        $this->assertNull($detached->dispatched_at);

        $shipment->refresh();
        $this->assertSame(1, $shipment->parcels_count);
        $this->assertEquals(0.0, (float) $shipment->total_paid_amount);
        $this->assertEquals(5000.0, (float) $shipment->total_deferred_amount);
        $this->assertEquals(5000.0, (float) $shipment->total_amount);

        // الآن يُسمح بإلغاء الطرد الأول بعد إخراجه
        $cancelled = $outgoingService->cancel($detached, $this->officeA);
        $this->assertSame(OutgoingParcelStatus::CANCELLED, $cancelled->status);

        // 4. استلام الطرد الثاني بالفرع المستلم → يُمنع إخراجه أو إلغاؤه بشكل نهائي
        $outgoingService->receiveAtDestination($p2->fresh(), $this->officeB);

        try {
            $shipmentService->detachParcel($shipment->fresh(), $p2->fresh(), $this->officeA);
            $this->fail('Expected DomainException when detaching an already received parcel.');
        } catch (DomainException $e) {
            $this->assertSame('لا يمكن إخراج الطرد لأنه تم استلامه بالفعل من الفرع المستلم.', $e->getMessage());
        }

        try {
            $outgoingService->cancel($p2->fresh(), $this->officeA);
            $this->fail('Expected DomainException when cancelling an already received parcel.');
        } catch (DomainException $e) {
            $this->assertSame('لا يمكن إلغاء طرد تم استلامه وتحويله إلى طرد وارد.', $e->getMessage());
        }
    }
}
