<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ParcelStatus;
use App\Models\Feature;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use App\Services\OutgoingParcelService;
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

        // 2. المكتب أ يحوّل الطرد الأول (آجل) إلى في الطريق (in_transit)
        $outgoing = $service->markInTransit($outgoing, $this->officeA);

        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $outgoing->status);
        $this->assertNotNull($outgoing->dispatched_at);

        // 3. المكتب ب يستلم الطرد الآجل → ينتقل المبلغ (5500) إلى parcels.cod_amount لتحصيله عند التسليم
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

        // التحقق من تحديث الطرد الصادر وربطه بالطرد الوارد
        $outgoing->refresh();
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $outgoing->status);
        $this->assertNotNull($outgoing->received_at);
        $this->assertSame($incomingParcel->id, $outgoing->linked_incoming_parcel_id);
        $this->assertSame($incomingParcel->id, $outgoing->linkedIncomingParcel->id);

        // 4. استلام الطرد الثاني (محاسب - paid) → يجب أن يصبح parcels.cod_amount = null لعدم التحصيل مرتين
        $service->markInTransit($outgoingSecond, $this->officeA);
        $paidIncomingParcel = $service->receiveAtDestination($outgoingSecond, $this->officeB);

        $this->assertNull($paidIncomingParcel->cod_amount);
        $this->assertDatabaseHas('parcels', [
            'id'             => $paidIncomingParcel->id,
            'office_id'      => $this->officeB->id,
            'receipt_number' => '2',
            'cod_amount'     => null,
        ]);

        // التحقق من تشغيل مسار إشعار الوصول (httpSMS)
        $this->assertSame('pending', $incomingParcel->sms_status);
        Http::assertSentCount(2);
    }

    public function test_receiving_same_outgoing_parcel_twice_fails_without_creating_duplicate(): void
    {
        $service = app(OutgoingParcelService::class);

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

        $service->markInTransit($outgoing, $this->officeA);

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

        $outgoing = $service->create($this->officeA, [
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'فهد المرسل',
            'sender_phone'          => '775555555',
            'recipient_phone'       => '776666666',
            'package_type'          => 'كيس ملابس',
            'cod_amount'            => 1000,
            'payment_type'          => 'paid',
        ]);

        $service->markInTransit($outgoing, $this->officeA);
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
}
