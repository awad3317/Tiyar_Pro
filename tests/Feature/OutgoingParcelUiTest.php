<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\ParcelStatus;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        // 1. استعراض صفحة الصادر
        $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->assertSee('الطرود الصادرة')
            ->assertSee('+ إضافة طرد صادر');

        // 2. إنشاء طرد موجّه إلى فرع في النظام → يأخذ رقم السند التلقائي '1'
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
                'notes'                 => 'يسلم يداً بيد',
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'));

        $internalParcel = OutgoingParcel::query()
            ->where('office_id', $this->senderOffice->id)
            ->where('recipient_phone', '777654321')
            ->firstOrFail();

        $this->assertSame('1', $internalParcel->receipt_number);
        $this->assertSame(OutgoingParcelStatus::PENDING, $internalParcel->status);
        $this->assertFalse($internalParcel->isExternal());
        $this->assertEquals($this->targetOffice->id, $internalParcel->destination_office_id);

        // 3. إنشاء طرد ثانٍ موجّه إلى مكتب خارجي → يأخذ رقم السند التلقائي '2'
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.store'), [
                'destination_type'     => 'external',
                'external_office_name' => 'مكتب الأمانة الخارجي - مأرب',
                'sender_name'          => 'ياسر المرسل',
                'sender_phone'         => '773111222',
                'recipient_name'       => 'نبيل المستلم',
                'recipient_phone'      => '773999888',
                'package_type'         => 'ضرف أبيض صغير',
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'));

        $externalParcel = OutgoingParcel::query()
            ->where('office_id', $this->senderOffice->id)
            ->where('recipient_phone', '773999888')
            ->firstOrFail();

        $this->assertSame('2', $externalParcel->receipt_number);
        $this->assertSame(OutgoingParcelStatus::PENDING, $externalParcel->status);
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
            ])
            ->assertRedirect(route('office.outgoing-parcels.index'));

        $targetOfficeOutgoing = OutgoingParcel::query()
            ->where('office_id', $this->targetOffice->id)
            ->firstOrFail();
        $this->assertSame('1', $targetOfficeOutgoing->receipt_number);

        // 4. شحن الطردين (تحويلهما إلى in_transit)
        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.dispatch', $internalParcel))
            ->assertRedirect();

        $this->actingAs($this->senderOffice, 'office')
            ->post(route('office.outgoing-parcels.dispatch', $externalParcel))
            ->assertRedirect();

        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $internalParcel->fresh()->status);
        $this->assertSame(OutgoingParcelStatus::IN_TRANSIT, $externalParcel->fresh()->status);

        // 5. تأكيد تسليم الطرد الخارجي
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
            ->assertSee('لديك 1 طرد قادم في الطريق — اضغط للاستلام');

        // المكتب الثالث غير المعني لا يرى الطرد ولا يرى البانر
        $this->actingAs($this->thirdOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertDontSee('ماجد المستلم')
            ->assertSee('لا توجد طرود قادمة في الطريق حالياً');

        $this->actingAs($this->thirdOffice, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertDontSee('طرد قادم في الطريق — اضغط للاستلام');

        // محاولة المكتب الثالث استلام الطرد تُرفض بـ 403
        $this->actingAs($this->thirdOffice, 'office')
            ->post(route('office.transit-parcels.receive', $outgoing))
            ->assertStatus(403);
    }

    public function test_target_office_can_receive_transit_parcel_creating_incoming_parcel(): void
    {
        $outgoing = OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'عمر المرسل',
            'sender_phone'          => '774111222',
            'recipient_name'        => 'وليد المستلم',
            'recipient_phone'       => '775333444',
            'package_type'          => 'كرتون قطع غيار',
            'receipt_number'        => 'RCV-777',
            'cod_amount'            => 7500,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        $this->actingAs($this->targetOffice, 'office')
            ->post(route('office.transit-parcels.receive', $outgoing))
            ->assertRedirect(route('office.transit-parcels.index'))
            ->assertSessionHas('success', 'تم استلام الطرد بنجاح، تم تحويله إلى قائمة الطرود وإرسال الإشعار للعميل.');

        $outgoing->refresh();
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $outgoing->status);
        $this->assertNotNull($outgoing->linked_incoming_parcel_id);

        $incomingParcel = Parcel::findOrFail($outgoing->linked_incoming_parcel_id);
        $this->assertEquals($this->targetOffice->id, $incomingParcel->office_id);
        $this->assertEquals($this->senderOffice->id, $incomingParcel->source_office_id);
        $this->assertSame('وليد المستلم', $incomingParcel->recipient_name);
        $this->assertSame('775333444', $incomingParcel->recipient_phone);
        $this->assertSame('كرتون قطع غيار', $incomingParcel->package_type);
        $this->assertSame('RCV-777', $incomingParcel->receipt_number);
        $this->assertSame(ParcelStatus::InOffice, $incomingParcel->status);
        $this->assertEquals(7500.0, (float) $incomingParcel->cod_amount);
    }
}
