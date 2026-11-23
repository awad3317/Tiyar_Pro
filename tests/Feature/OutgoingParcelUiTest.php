<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ParcelStatus;
use App\Enums\ShipmentStatus;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use App\Models\Shipment;
use App\Models\User;
use App\Services\OutgoingParcelService;
use App\Services\ShipmentService;
use Database\Seeders\FeatureSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutgoingParcelUiTest extends TestCase
{
    use DatabaseTransactions;

    protected Office $senderOffice;
    protected Office $targetOffice;
    protected Office $thirdOffice;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['status' => 'ok', 'data' => ['id' => 'fake-msg']], 200),
        ]);

        $this->seed(FeatureSeeder::class);

        $this->senderOffice = Office::create([
            'name'                  => 'مكتب الإرسال (صنعاء)',
            'branch_name'           => 'الستين',
            'whatsapp_sender_phone' => '770200301',
            'httpsms_from_phone'    => '+967770200301',
            'httpsms_api_key'       => 'test-key-sender',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->targetOffice = Office::create([
            'name'                  => 'مكتب الاستقبال (عدن)',
            'branch_name'           => 'كريتر',
            'whatsapp_sender_phone' => '770200302',
            'httpsms_from_phone'    => '+967770200302',
            'httpsms_api_key'       => 'test-key-target',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->thirdOffice = Office::create([
            'name'                  => 'مكتب ثالث (تعز)',
            'branch_name'           => 'الحوبان',
            'whatsapp_sender_phone' => '770200303',
            'httpsms_from_phone'    => '+967770200303',
            'httpsms_api_key'       => 'test-key-third',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        // ربط مكتب الإرسال بمكتب الاستقبال ثنائياً
        $this->senderOffice->syncPartnerOffices([$this->targetOffice->id]);
    }

    public function test_office_without_outgoing_parcels_feature_gets_403_on_get_and_post(): void
    {
        $this->assertFalse($this->senderOffice->hasFeature('outgoing_parcels'));

        $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertStatus(403);

        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $this->targetOffice->id,
                'sender_name'           => 'مرسل تجريبي',
                'sender_phone'          => '771111111',
                'recipient_phone'       => '772222222',
                'package_type'          => 'كرتون',
            ])
            ->assertStatus(403);
    }

    public function test_office_with_feature_can_view_page_create_internal_and_external_parcels_and_dispatch_them(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->senderOffice->refreshFeaturesCache();

        // 1. استعراض صفحة الصادر بشريط التبويبات العلوي الموحّد وزر الإضافة السريع بالهيدر
        $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertSee('الطرود الصادرة')
            ->assertSee('الكل')
            ->assertSee('في الانتظار')
            ->assertSee('في الطريق')
            ->assertSee('وصل الفرع')
            ->assertSee('ملغي')
            ->assertSee('open-outgoing-parcel-modal');

        // التحقق من إلزامية المبلغ وحالة الدفع (محاسب / آجل)
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $this->targetOffice->id,
                'sender_name'           => 'محمد المرسل',
                'sender_phone'          => '771234567',
                'recipient_phone'       => '777654321',
                'package_type'          => 'كرتون كاكي كبير',
            ])
            ->assertSessionHasErrors(['cod_amount', 'payment_type']);

        // 2. إنشاء طرد موجّه إلى فرع في النظام (آجل) → يأخذ رقم السند التلقائي '1'
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $this->targetOffice->id,
                'sender_name'           => 'محمد المرسل',
                'sender_phone'          => '771234567',
                'recipient_name'        => 'سعيد المستلم',
                'recipient_phone'       => '777654321',
                'package_type'          => 'كرتون كاكي كبير',
                'cod_amount'            => 4000,
                'payment_type'          => 'deferred',
                'notes'                 => 'يسلم يداً بيد',
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'));

        $internalParcel = OutgoingParcel::query()
            ->where('office_id', $this->senderOffice->id)
            ->where('recipient_phone', '777654321')
            ->firstOrFail();

        $this->assertSame('1', $internalParcel->receipt_number);
        $this->assertSame(OutgoingParcelStatus::PENDING, $internalParcel->status);
        $this->assertSame(OutgoingPaymentType::DEFERRED, $internalParcel->payment_type);
        $this->assertFalse($internalParcel->isExternal());
        $this->assertEquals($this->targetOffice->id, $internalParcel->destination_office_id);

        // 3. إنشاء طرد ثانٍ موجّه إلى مكتب خارجي (محاسب) → يأخذ رقم السند التلقائي '2'
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'     => 'external',
                'external_office_name' => 'مكتب الأمانة الخارجي - مأرب',
                'sender_name'          => 'ياسر المرسل',
                'sender_phone'         => '773111222',
                'recipient_name'       => 'نبيل المستلم',
                'recipient_phone'      => '773999888',
                'package_type'         => 'ضرف أبيض صغير',
                'cod_amount'           => 2500,
                'payment_type'         => 'paid',
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'));

        $externalParcel = OutgoingParcel::query()
            ->where('office_id', $this->senderOffice->id)
            ->where('recipient_phone', '773999888')
            ->firstOrFail();

        $this->assertSame('2', $externalParcel->receipt_number);
        $this->assertSame(OutgoingParcelStatus::PENDING, $externalParcel->status);
        $this->assertSame(OutgoingPaymentType::PAID, $externalParcel->payment_type);
        $this->assertTrue($externalParcel->isExternal());
        $this->assertSame('مكتب الأمانة الخارجي - مأرب', $externalParcel->destinationLabel());

        // التحقق من أن مكتب آخر يبدأ ترقيمه المستقل من '1'
        $this->targetOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->targetOffice->refreshFeaturesCache();

        $this->actingAs($this->targetOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $this->senderOffice->id,
                'sender_name'           => 'مرسل عدن',
                'sender_phone'          => '778111222',
                'recipient_phone'       => '778333444',
                'package_type'          => 'كيس أزرق وسط',
                'cod_amount'            => 1500,
                'payment_type'          => 'paid',
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'));

        $targetOfficeOutgoing = OutgoingParcel::query()
            ->where('office_id', $this->targetOffice->id)
            ->firstOrFail();
        $this->assertSame('1', $targetOfficeOutgoing->receipt_number);

        // التحقق من تصميم البطاقة الجديد: زر "ضم إلى شحنة" بدون زر الشحن الفردي وبدون تكرار شارة الحالة
        $listHtml = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertSee('ضم إلى شحنة')
            ->assertDontSee('شحن (في الطريق)')
            ->getContent();
        $this->assertStringContainsString('border-amber-300', $listHtml);

        // 4. رفض الشحن الفردي المباشر عبر POST /outgoing-parcels/{id}/dispatch
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.dispatch', $internalParcel))
            ->assertRedirect()
            ->assertSessionHas('error', 'لا يمكن تحويل الطرد إلى "في الطريق" بشكل فردي؛ يجب ضمه إلى شحنة وإرسال الشحنة بالكامل.');

        $this->assertSame(OutgoingParcelStatus::PENDING, $internalParcel->fresh()->status);

        // 5. ضم الطردين في شحنة (مسودة) ثم إرسال الشحنة بالكامل
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق النقليات',
                'driver_phone' => '771444555',
                'parcel_ids'   => [$internalParcel->id, $externalParcel->id],
                'dispatch_now' => false,
            ])
            ->assertRedirect();

        $shipment = Shipment::query()->where('office_id', $this->senderOffice->id)->latest('created_at')->firstOrFail();
        $this->assertSame(ShipmentStatus::DRAFT, $shipment->status);

        // بطاقة الطرد المضاف لمسودة الشحنة تعرض شارة الشحنة وحالة انتظار الإرسال
        $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertSee("شحنة #{$shipment->shipment_number}")
            ->assertSee("مُضاف للشحنة #{$shipment->shipment_number} (بانتظار الإرسال)");

        // إرسال الشحنة يحوّل الطردين معاً إلى in_transit
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.shipments.sent.dispatch', $shipment))
            ->assertRedirect();

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $internalParcel->fresh()->status);
        $this->assertNotNull($internalParcel->fresh()->dispatched_at);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $externalParcel->fresh()->status);
        $this->assertNotNull($externalParcel->fresh()->dispatched_at);

        // 6. تأكيد تسليم الطرد الخارجي
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.deliver-external', $externalParcel))
            ->assertRedirect();

        $this->assertSame(OutgoingParcelStatus::DELIVERED, $externalParcel->fresh()->status);
    }

    public function test_target_office_sees_transit_parcel_and_unrelated_third_office_does_not_and_gets_403(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        $outgoing = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'طارق المرسل',
            'sender_phone'          => '771000111',
            'recipient_name'        => 'ماجد المستلم',
            'recipient_phone'       => '772000222',
            'package_type'          => 'كيس ملابس كبير',
            'receipt_number'        => 'TR-900',
            'cod_amount'            => 3000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        // المكتب المستهدف يرى الطرد في صفحة الطرود القادمة وفي بانر لوحة التحكم
        $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertSee('ماجد المستلم')
            ->assertSee('كيس ملابس كبير')
            ->assertSee('مكتب الإرسال (صنعاء) - الستين');

        $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertSee('شحنات قادمة')
            ->assertSee('مراجعة واستلام');

        // المكتب الثالث غير المعني لا يرى الطرد ولا يرى البانر
        $this->actingAs($this->thirdOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertDontSee('ماجد المستلم')
            ->assertSee('لا توجد طرود قادمة في الطريق حالياً');

        $this->actingAs($this->thirdOffice, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertDontSee('مراجعة واستلام');

        // محاولة المكتب الثالث استلام الطرد تُرفض بـ 403
        $this->actingAs($this->thirdOffice, 'office')
            ->post(route('office.transit-parcels.receive', $outgoing))
            ->assertStatus(403);
    }

    public function test_target_office_can_receive_transit_parcel_creating_incoming_parcel(): void
    {
        // 1. طرد آجل (deferred) → ينسخ المبلغ إلى parcels.cod_amount
        $deferredOutgoing = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'عمر المرسل',
            'sender_phone'          => '774111222',
            'recipient_name'        => 'وليد المستلم',
            'recipient_phone'       => '775333444',
            'package_type'          => 'كرتون قطع غيار',
            'receipt_number'        => '1',
            'cod_amount'            => 7500,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        $this->actingAs($this->targetOffice, 'office')
            ->post(route('office.transit-parcels.receive', $deferredOutgoing))
            ->assertRedirect(route('office.transit-parcels.index'))
            ->assertSessionHas('success', 'تم استلام الطرد بنجاح، تم تحويله إلى قائمة الطرود وإرسال الإشعار للعميل.');

        $deferredOutgoing->refresh();
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $deferredOutgoing->status);
        $this->assertNotNull($deferredOutgoing->linked_incoming_parcel_id);

        $incomingDeferred = Parcel::findOrFail($deferredOutgoing->linked_incoming_parcel_id);
        $this->assertEquals($this->targetOffice->id, $incomingDeferred->office_id);
        $this->assertEquals($this->senderOffice->id, $incomingDeferred->source_office_id);
        $this->assertSame('وليد المستلم', $incomingDeferred->recipient_name);
        $this->assertSame('775333444', $incomingDeferred->recipient_phone);
        $this->assertSame('كرتون قطع غيار', $incomingDeferred->package_type);
        $this->assertSame('1', $incomingDeferred->receipt_number);
        $this->assertSame(ParcelStatus::InOffice, $incomingDeferred->status);
        $this->assertEquals(7500.0, (float) $incomingDeferred->cod_amount);

        // 2. طرد محاسب (paid) → يصبح parcels.cod_amount = null لعدم التحصيل مرة أخرى
        $paidOutgoing = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'عمر المرسل',
            'sender_phone'          => '774111222',
            'recipient_name'        => 'سامي المستلم',
            'recipient_phone'       => '775666777',
            'package_type'          => 'كيس أبيض وسط',
            'receipt_number'        => '2',
            'cod_amount'            => 4000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        $this->actingAs($this->targetOffice, 'office')
            ->post(route('office.transit-parcels.receive', $paidOutgoing))
            ->assertRedirect(route('office.transit-parcels.index'));

        $paidOutgoing->refresh();
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $paidOutgoing->status);
        $incomingPaid = Parcel::findOrFail($paidOutgoing->linked_incoming_parcel_id);
        $this->assertNull($incomingPaid->cod_amount);
    }

    public function test_outgoing_parcel_phone_validation_normalizes_valid_numbers_and_rejects_invalid_ones(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        // 1. أرقام غير صالحة (بادئة غير يمنية أو طول ناقص) يجب رفضها بأخطاء تحقق
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $this->targetOffice->id,
                'sender_name'           => 'مرسل خطأ',
                'sender_phone'          => '791234567',
                'recipient_phone'       => '7712345',
                'package_type'          => 'كيس أبيض وسط',
                'cod_amount'            => 2000,
                'payment_type'          => 'paid',
            ])
            ->assertSessionHasErrors(['sender_phone', 'recipient_phone']);

        // 2. أرقام يمنية صالحة بأرقام عربية مشرقية ومع مفتاح الدولة (+967) تُقبل وتُوحّد إلى 9 أرقام
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $this->targetOffice->id,
                'sender_name'           => 'مرسل أرقام عربية',
                'sender_phone'          => '٧٧١٢٣٤٥٦٧',
                'recipient_phone'       => '+967 73 987 6543',
                'package_type'          => 'كرتون كاكي وسط',
                'cod_amount'            => 3500,
                'payment_type'          => 'deferred',
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('outgoing_parcels', [
            'office_id'       => $this->senderOffice->id,
            'sender_name'     => 'مرسل أرقام عربية',
            'sender_phone'    => '771234567',
            'recipient_phone' => '739876543',
        ]);
    }

    public function test_bidirectional_partner_office_linking_isolates_destinations_correctly(): void
    {
        $qatn = Office::create([
            'name'                  => 'مكتب الجعيدي',
            'branch_name'           => 'القطن',
            'whatsapp_sender_phone' => '770400501',
            'httpsms_from_phone'    => '+967770400501',
            'httpsms_api_key'       => 'test-qatn',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $mukalla = Office::create([
            'name'                  => 'مكتب الجعيدي',
            'branch_name'           => 'المكلا',
            'whatsapp_sender_phone' => '770400502',
            'httpsms_from_phone'    => '+967770400502',
            'httpsms_api_key'       => 'test-mukalla',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $zajil = Office::create([
            'name'                  => 'مكتب زاجل',
            'branch_name'           => 'سيئون',
            'whatsapp_sender_phone' => '770400503',
            'httpsms_from_phone'    => '+967770400503',
            'httpsms_api_key'       => 'test-zajil',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        foreach ([$qatn, $mukalla, $zajil] as $off) {
            $off->syncFeaturesStatus(['outgoing_parcels']);
            $off->refreshFeaturesCache();
        }

        // 1. ربط القطن مع المكلا وزاجل عبر لوحة تحكم الأدمن
        $admin = User::firstOrCreate(
            ['email' => 'admin@mursal.pro'],
            ['name' => 'المدير العام', 'password' => Hash::make('password')]
        );

        $this->actingAs($admin, 'web')
            ->put(route('admin.offices.update', $qatn), [
                'name'                    => $qatn->name,
                'branch_name'             => $qatn->branch_name,
                'whatsapp_sender_phone'   => $qatn->whatsapp_sender_phone,
                'httpsms_from_phone'      => $qatn->httpsms_from_phone,
                'is_active'               => 1,
                'partner_links_submitted' => 1,
                'partner_office_ids'      => [$mukalla->id, $zajil->id],
            ])
            ->assertRedirect();

        // 2. مكتب القطن يرى كلاً من المكلا وزاجل في صفحة الطرود الصادرة
        $this->actingAs($qatn, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertSee('مكتب الجعيدي - المكلا')
            ->assertSee('مكتب زاجل - سيئون');

        // 3. مكتب المكلا يرى القطن فقط ولا يرى زاجل
        $this->actingAs($mukalla, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertSee('مكتب الجعيدي - القطن')
            ->assertDontSee('مكتب زاجل - سيئون');

        // 4. محاولة المكلا الإرسال إلى زاجل كفرع في النظام تُرفض في الـ FormRequest وفي الـ Service
        $this->actingAs($mukalla, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'      => 'office',
                'destination_office_id' => $zajil->id,
                'sender_name'           => 'مرسل المكلا',
                'sender_phone'          => '771222333',
                'recipient_phone'       => '774555666',
                'package_type'          => 'كرتون كاكي وسط',
                'cod_amount'            => 3000,
                'payment_type'          => 'paid',
            ])
            ->assertSessionHasErrors(['destination_office_id']);

        try {
            app(OutgoingParcelService::class)->create($mukalla, [
                'destination_office_id' => $zajil->id,
                'sender_name'           => 'مرسل المكلا',
                'sender_phone'          => '771222333',
                'recipient_phone'       => '774555666',
                'package_type'          => 'كرتون كاكي وسط',
                'cod_amount'            => 3000,
                'payment_type'          => 'paid',
            ]);
            $this->fail('Expected DomainException when sending to an unlinked office.');
        } catch (DomainException $e) {
            $this->assertSame('المكتب المستلم غير مرتبط بهذا المكتب.', $e->getMessage());
        }

        // 5. إلغاء ربط المكلا من القطن يحذف الربط من الطرفين
        $qatn->syncPartnerOffices([$zajil->id]);

        $this->assertSame([$zajil->id], array_map('intval', $qatn->partnerOffices()->pluck('offices.id')->all()));
        $this->assertEmpty($mukalla->partnerOffices()->pluck('offices.id')->all());
    }

    public function test_json_ajax_endpoints_and_query_counts_are_optimized(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->senderOffice->refreshFeaturesCache();

        $p1 = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'مرسل أجاكس 1',
            'sender_phone'          => '771111222',
            'recipient_name'        => 'مستلم أجاكس 1',
            'recipient_phone'       => '773333444',
            'package_type'          => 'كرتون كاكي كبير',
            'receipt_number'        => '1',
            'cod_amount'            => 5000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $p2 = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'مرسل أجاكس 2',
            'sender_phone'          => '775555666',
            'recipient_name'        => 'مستلم أجاكس 2',
            'recipient_phone'       => '778888999',
            'package_type'          => 'كيس أبيض صغير',
            'receipt_number'        => '2',
            'cod_amount'            => 2000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // 1. فحص عدد استعلامات SQL في بحث وتصفية AJAX على GET /office/outgoing-parcels
        DB::flushQueryLog();
        DB::enableQueryLog();

        $ajaxFilterResponse = $this->actingAs($this->senderOffice, 'office')
            ->getJson(route('office.outgoing-parcels.index', ['status' => 'pending', 'q' => '773333444']));

        $outgoingAjaxQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $ajaxFilterResponse->assertOk()
            ->assertJson([
                'ok'            => true,
                'status_filter' => 'pending',
                'counts'        => [
                    'all'        => 2,
                    'pending'    => 2,
                    'in_transit' => 0,
                    'received'   => 0,
                ],
            ]);
        $this->assertStringContainsString('مستلم أجاكس 1', $ajaxFilterResponse->json('html'));
        $this->assertLessThanOrEqual(5, $outgoingAjaxQueries);

        // 2. رفض الشحن الفردي المباشر عبر AJAX (POST dispatch) بـ 422
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.dispatch', $p1))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'لا يمكن تحويل الطرد إلى "في الطريق" بشكل فردي؛ يجب ضمه إلى شحنة وإرسال الشحنة بالكامل.',
            ]);
        $this->assertSame(OutgoingParcelStatus::PENDING, $p1->fresh()->status);

        // إرسال الطرد الأول عبر كشف شحنة (AJAX POST shipments.sent.store)
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق أجاكس',
                'driver_phone' => '771222333',
                'parcel_ids'   => [$p1->id],
                'dispatch_now' => true,
            ])
            ->assertStatus(201)
            ->assertJson(['ok' => true]);

        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $p1->fresh()->status);
        $this->assertNotNull($p1->fresh()->dispatched_at);

        // 3. إلغاء الطرد الثاني عبر AJAX (POST cancel) بدون إعادة تحميل الصفحة
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.cancel', $p2))
            ->assertOk()
            ->assertJson([
                'ok'     => true,
                'counts' => [
                    'pending'    => 0,
                    'in_transit' => 1,
                    'cancelled'  => 1,
                ],
                'parcel' => [
                    'id'     => $p2->id,
                    'status' => 'cancelled',
                ],
            ]);

        // 4. فحص عدد استعلامات GET /office/transit-parcels (تشمل استعلام office_profiles المخبأ لأول مرة)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk();

        $transitQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(4, $transitQueries);

        // 5. استلام الطرد في الفرع المستهدف عبر AJAX (POST receive)
        $this->actingAs($this->targetOffice, 'office')
            ->postJson(route('office.transit-parcels.receive', $p1))
            ->assertOk()
            ->assertJson([
                'ok'              => true,
                'parcel_id'       => $p1->id,
                'remaining_count' => 0,
            ]);

        $this->assertSame(OutgoingParcelStatus::RECEIVED, $p1->fresh()->status);
    }

    public function test_filtered_ajax_index_keeps_unfiltered_kpi_counts_and_cancel_returns_fresh_card_html(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->senderOffice->refreshFeaturesCache();

        $pendingParcel = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'مرسل منتظر',
            'sender_phone'          => '771111111',
            'recipient_name'        => 'مستلم منتظر',
            'recipient_phone'       => '772222222',
            'package_type'          => 'كرتون منتظر',
            'receipt_number'        => '10',
            'cod_amount'            => 3000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $inTransitParcel = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => null,
            'external_office_name'  => 'مكتب مأرب الخارجي',
            'sender_name'           => 'مرسل في الطريق',
            'sender_phone'          => '773333333',
            'recipient_name'        => 'مستلم في الطريق',
            'recipient_phone'       => '774444444',
            'package_type'          => 'كيس في الطريق',
            'receipt_number'        => '11',
            'cod_amount'            => 5000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        // 1. التصفية على in_transit تُرجع فقط طرد in_transit لكن عدادات KPI تبقى غير مفلترة (pending = 1 و in_transit = 1)
        $filterRes = $this->actingAs($this->senderOffice, 'office')
            ->getJson(route('office.outgoing-parcels.index', ['status' => 'in_transit', '_t' => '1700000000000']))
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertJson([
                'ok'            => true,
                'status_filter' => 'in_transit',
                'counts'        => [
                    'all'        => 2,
                    'pending'    => 1,
                    'in_transit' => 1,
                    'received'   => 0,
                    'delivered'  => 0,
                    'cancelled'  => 0,
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $filterRes->headers->get('Cache-Control'));
        $this->assertStringContainsString('Accept', (string) $filterRes->headers->get('Vary'));
        $this->assertStringContainsString('X-Requested-With', (string) $filterRes->headers->get('Vary'));

        // التحقق من ترويسات منع الكاش أيضاً على استجابات HTML للصادر وفي الطريق
        $htmlRes = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $htmlRes->headers->get('Cache-Control'));

        $transitHtmlRes = $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $transitHtmlRes->headers->get('Cache-Control'));

        $filterHtml = (string) $filterRes->json('html');
        $this->assertStringContainsString('مستلم في الطريق', $filterHtml);
        $this->assertStringNotContainsString('مستلم منتظر', $filterHtml);
        $this->assertStringContainsString('data-outgoing-parcel-id="' . $inTransitParcel->id . '"', $filterHtml);

        // 2. إلغاء الطرد المنتظر عبر AJAX يُرجع card_html محدثة بحالة (ملغي) و data-outgoing-parcel-id ويُنقِص pending بواحد
        $cancelRes = $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.cancel', $pendingParcel))
            ->assertOk()
            ->assertJson([
                'ok'        => true,
                'parcel_id' => $pendingParcel->id,
                'status'    => 'cancelled',
                'counts'    => [
                    'all'        => 2,
                    'pending'    => 0,
                    'in_transit' => 1,
                    'received'   => 0,
                    'delivered'  => 0,
                    'cancelled'  => 1,
                ],
            ]);

        $cancelledCardHtml = (string) $cancelRes->json('card_html');
        $this->assertStringContainsString('data-outgoing-parcel-id="' . $pendingParcel->id . '"', $cancelledCardHtml);
        $this->assertStringContainsString('ملغي', $cancelledCardHtml);
        $this->assertStringContainsString('border-rose-300', $cancelledCardHtml);
        $this->assertStringNotContainsString('data-card-action="cancel"', $cancelledCardHtml);

        // 3. تسليم الطرد الخارجي عبر AJAX يُرجع card_html محدثة بحالة (تم التسليم) وعدادات محدثة
        $deliverRes = $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.deliver-external', $inTransitParcel))
            ->assertOk()
            ->assertJson([
                'ok'        => true,
                'parcel_id' => $inTransitParcel->id,
                'status'    => 'delivered',
                'counts'    => [
                    'all'        => 2,
                    'pending'    => 0,
                    'in_transit' => 0,
                    'received'   => 0,
                    'delivered'  => 1,
                    'cancelled'  => 1,
                ],
            ]);

        $deliveredCardHtml = (string) $deliverRes->json('card_html');
        $this->assertStringContainsString('data-outgoing-parcel-id="' . $inTransitParcel->id . '"', $deliveredCardHtml);
        $this->assertStringContainsString('تم التسليم', $deliveredCardHtml);
        $this->assertStringContainsString('border-emerald-300', $deliveredCardHtml);
        $this->assertStringNotContainsString('data-card-action="cancel"', $deliveredCardHtml);
    }

    public function test_detach_before_cancel_ajax_ui_flow_and_immutability_after_receipt(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->senderOffice->refreshFeaturesCache();

        $p1 = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'مرسل فك 1',
            'sender_phone'          => '771111001',
            'recipient_name'        => 'مستلم فك 1',
            'recipient_phone'       => '772222001',
            'package_type'          => 'كرتون فك 1',
            'receipt_number'        => '21',
            'cod_amount'            => 4000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $p2 = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'مرسل فك 2',
            'sender_phone'          => '771111002',
            'recipient_name'        => 'مستلم فك 2',
            'recipient_phone'       => '772222002',
            'package_type'          => 'كيس فك 2',
            'receipt_number'        => '22',
            'cod_amount'            => 6000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // 1. ضم الطردين في شحنة مسودة (draft)
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'driver_name'  => 'سائق الفك',
                'driver_phone' => '771888777',
                'parcel_ids'   => [$p1->id, $p2->id],
                'dispatch_now' => false,
            ])
            ->assertStatus(201);

        $shipment = Shipment::query()->where('office_id', $this->senderOffice->id)->latest('created_at')->firstOrFail();

        // البطاقة تُخفي زر الإلغاء وتعرض زر «إخراج من الشحنة»
        $draftListHtml = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-card-action="detach"', $draftListHtml);
        $this->assertStringContainsString('إخراج من الشحنة', $draftListHtml);
        $this->assertStringNotContainsString('data-card-action="cancel"', $draftListHtml);

        // محاولة الإلغاء عبر AJAX أثناء الارتباط بالشحنة تُرفض بـ 422
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.cancel', $p1))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'لا يمكن إلغاء الطرد وهو ضمن شحنة؛ يرجى إخراجه من الشحنة أولاً.',
            ]);

        // 2. إرسال الشحنة → يظهر الطردان لدى الفرع المستهدف في «في الطريق»
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.shipments.sent.dispatch', $shipment))
            ->assertOk();

        $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertSee('مستلم فك 1')
            ->assertSee('مستلم فك 2');

        // 3. إخراج الطرد الأول من الشحنة عبر AJAX (وهو في الطريق قبل الاستلام)
        $detachRes = $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.detach-shipment', $p1))
            ->assertOk()
            ->assertJson([
                'ok'        => true,
                'parcel_id' => $p1->id,
                'status'    => 'pending',
                'counts'    => [
                    'all'        => 2,
                    'pending'    => 1,
                    'in_transit' => 1,
                    'received'   => 0,
                ],
            ]);

        $detachedHtml = (string) $detachRes->json('card_html');
        $this->assertStringContainsString('data-outgoing-parcel-id="' . $p1->id . '"', $detachedHtml);
        $this->assertStringContainsString('ضم إلى شحنة', $detachedHtml);
        $this->assertStringContainsString('data-card-action="cancel"', $detachedHtml);
        $this->assertStringNotContainsString('data-card-action="detach"', $detachedHtml);

        $p1->refresh();
        $this->assertNull($p1->shipment_id);
        $this->assertSame(OutgoingParcelStatus::PENDING, $p1->status);
        $this->assertNull($p1->dispatched_at);

        // يختفي الطرد الأول فوراً من قائمة «في الطريق» لدى الفرع المستهدف
        $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertDontSee('مستلم فك 1')
            ->assertSee('مستلم فك 2');

        // الآن ينجح إلغاء الطرد الأول بعد إخراجه من الشحنة
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.cancel', $p1))
            ->assertOk()
            ->assertJson([
                'ok'        => true,
                'parcel_id' => $p1->id,
                'status'    => 'cancelled',
                'counts'    => [
                    'pending'   => 0,
                    'cancelled' => 1,
                ],
            ]);

        // 4. استلام الطرد الثاني بالفرع المستهدف → يُمنع إخراجه أو إلغاؤه وتختفي كل الأزرار من بطاقته
        $this->actingAs($this->targetOffice, 'office')
            ->postJson(route('office.transit-parcels.receive', $p2))
            ->assertOk();

        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.detach-shipment', $p2))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'لا يمكن إخراج الطرد لأنه تم استلامه بالفعل من الفرع المستلم.',
            ]);

        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.cancel', $p2))
            ->assertStatus(422);

        $afterReceiptHtml = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index', ['status' => 'received']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('مستلم فك 2', $afterReceiptHtml);
        $this->assertStringNotContainsString('data-card-action="detach"', $afterReceiptHtml);
        $this->assertStringNotContainsString('data-card-action="cancel"', $afterReceiptHtml);
    }

    public function test_session_flash_messages_bridge_into_unified_floating_toast_without_inline_banners(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->senderOffice->refreshFeaturesCache();

        $res = $this->actingAs($this->senderOffice, 'office')
            ->withSession(['success' => 'تم تسجيل الطرد الصادر بنجاح.'])
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk();

        $html = $res->getContent();
        $this->assertStringContainsString('showFeedback(', $html);
        $this->assertStringContainsString('تم تسجيل الطرد الصادر بنجاح.', $html);
        $this->assertStringContainsString('@office-toast.window', $html);
        // لا يوجد بانر ثابت مكرر بداخل جسم الصفحة
        $this->assertStringNotContainsString('<span class="flex-1">تم تسجيل الطرد الصادر بنجاح.</span>', $html);
    }

    public function test_outgoing_parcel_receipt_supports_direct_iframe_print_auto_print_and_proportional_mobile_preview(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        $parcel = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'مرسل سند مباشر',
            'sender_phone'          => '771234001',
            'recipient_name'        => 'مستلم سند مباشر',
            'recipient_phone'       => '777888999',
            'package_type'          => 'كرتون طباعة مباشرة',
            'receipt_number'        => '404',
            'cod_amount'            => 5000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // 1. بطاقة الطرد الصادر تتضمن زر الطباعة المباشرة عبر iframe + زري واتساب ومشاركة PDF (بدون زر المعاينة المحذوف)
        $indexHtml = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->getContent();

        $receiptUrl = route('office.outgoing-parcels.receipt', $parcel);
        $this->assertStringContainsString('data-card-action="print-receipt"', $indexHtml);
        $this->assertStringContainsString('data-receipt-url="' . $receiptUrl . '"', $indexHtml);
        $this->assertStringContainsString('printReceipt(url)', $indexHtml);
        $this->assertStringContainsString('data-card-action="send-receipt-whatsapp"', $indexHtml);
        $this->assertStringContainsString('data-card-action="share-receipt-pdf"', $indexHtml);

        // 2. صفحة السند تدعم التحجيم التناسبي للجوال + طباعة 1/1 بدون صفحة فارغة + معامل auto_print=1
        $receiptRes = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.receipt', [$parcel, 'auto_print' => 1]))
            ->assertOk();

        $receiptRes->assertSee('404')
            ->assertSee('مستلم سند مباشر')
            ->assertSee('777888999')
            ->assertSee('preview-scale-shell', false)
            ->assertSee('receiptPreviewPage()', false)
            ->assertSee('transform: none !important;', false)
            ->assertSee('min-height: 0 !important;', false)
            ->assertSee('size: A4 portrait;', false)
            ->assertSee("get('auto_print') === '1'", false);

        // 3. مكتب آخر لا يستطيع الوصول لسند طرد لا يتبعه (403)
        $this->targetOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->targetOffice->refreshFeaturesCache();

        $this->actingAs($this->targetOffice, 'office')
            ->get(route('office.outgoing-parcels.receipt', $parcel))
            ->assertStatus(403);
    }
}
