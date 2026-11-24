<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\ExternalPartnerLink;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalPartnerPhase1Test extends TestCase
{
    use DatabaseTransactions;

    protected Office $receivingOffice;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['status' => 'ok', 'data' => ['id' => 'fake-msg']], 200),
        ]);

        $this->seed(FeatureSeeder::class);

        $this->receivingOffice = Office::create([
            'name'                  => 'مكتب النجم السريع',
            'branch_name'           => 'سيئون',
            'whatsapp_sender_phone' => '770910001',
            'httpsms_from_phone'    => '+967770910001',
            'httpsms_api_key'       => 'test-key-910001',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->receivingOffice->syncFeaturesStatus([
            'outgoing_parcels',
            'sent_shipments',
            'external_partner_links',
        ]);
        $this->receivingOffice->refreshFeaturesCache();

        $this->adminUser = User::create([
            'name'     => 'مدير النظام الاختباري',
            'email'    => 'admin-ext-phase1-' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_feature_flag_and_external_partner_link_model_with_soft_deletes_and_duplicate_prefix_after_delete(): void
    {
        $this->assertTrue($this->receivingOffice->hasFeature('external_partner_links'));

        $link1 = ExternalPartnerLink::create([
            'office_id'       => $this->receivingOffice->id,
            'partner_name'    => 'مكتب البراق',
            'partner_city'    => 'مأرب',
            'partner_phone'   => '771234567',
            'token'           => Str::random(64),
            'access_pin'      => '4829',
            'receipt_prefix'  => 'MRB-',
            'is_active'       => true,
            'shipments_count' => 0,
        ]);

        $this->assertSame('4829', $link1->fresh()->access_pin);
        $this->assertCount(1, $this->receivingOffice->externalPartnerLinks);

        // حذف ناعم ثم إنشاء رابط جديد بنفس البادئة لنفس المكتب (مسموح في قاعدة البيانات لعدم وجود قيد unique صلب)
        $link1->delete();
        $this->assertSoftDeleted('external_partner_links', ['id' => $link1->id]);

        $link2 = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب البراق المحدث',
            'partner_city'   => 'مأرب',
            'token'          => Str::random(64),
            'receipt_prefix' => 'MRB-',
            'is_active'      => true,
        ]);

        $this->assertNotNull($link2->id);
        $this->assertCount(1, $this->receivingOffice->fresh()->externalPartnerLinks);
    }

    public function test_external_shipment_and_parcel_null_safety_across_views_search_admin_and_soft_deleted_link(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب الصقر الخارجي',
            'partner_city'   => 'عتق',
            'partner_phone'  => '773334445',
            'token'          => Str::random(64),
            'access_pin'     => '1234',
            'receipt_prefix' => 'ATQ-',
            'is_active'      => true,
        ]);

        $shipment = Shipment::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'idempotency_key'          => (string) Str::uuid(),
            'shipment_number'          => 'EXT-26112401',
            'driver_name'              => 'سالم السائق',
            'driver_phone'             => '775556667',
            'parcels_count'            => 1,
            'total_paid_amount'        => 0,
            'total_deferred_amount'    => 3500,
            'total_amount'             => 3500,
            'destinations_summary'     => [$this->receivingOffice->branch_name],
            'status'                   => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        $parcel = OutgoingParcel::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_id'              => $shipment->id,
            'destination_office_id'    => $this->receivingOffice->id,
            'receipt_number'           => 'ATQ-501',
            'sender_name'              => 'خالد المرسل',
            'sender_phone'             => '771112223',
            'recipient_name'           => 'صالح المستلم',
            'recipient_phone'          => '778889990',
            'package_type'             => 'كرتون قطع غيار',
            'payment_type'             => OutgoingPaymentType::DEFERRED,
            'cod_amount'               => 3500,
            'status'                   => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        $this->assertSame('مكتب الصقر الخارجي - عتق', $shipment->fresh()->senderOfficeLabel());
        $this->assertSame('مكتب الصقر الخارجي - عتق', $parcel->fresh()->senderOfficeLabel());

        // التحقق من عداد الشحنات القادمة في الخدمة والداشبورد
        $this->assertGreaterThanOrEqual(1, ShipmentService::countPendingReceivedShipments($this->receivingOffice));

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.dashboard'))
            ->assertOk();

        // شاشة الشحنات المستلمة وشاشة الفحص
        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertSee('EXT-26112401')
            ->assertSee('مكتب الصقر الخارجي - عتق');

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.show', $shipment))
            ->assertOk()
            ->assertSee('EXT-26112401')
            ->assertSee('مكتب الصقر الخارجي - عتق')
            ->assertSee('ATQ-501');

        // شاشة في الطريق إلينا (LEFT JOIN offices + LEFT JOIN shipments)
        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertSee('ATQ-501')
            ->assertSee('مكتب الصقر الخارجي - عتق');

        // البحث الشامل
        $this->actingAs($this->receivingOffice, 'office')
            ->getJson(route('office.global-search', ['q' => 'ATQ-501']))
            ->assertOk()
            ->assertJsonFragment([
                'receipt_number' => 'ATQ-501',
                'from_office'    => 'مكتب الصقر الخارجي - عتق',
            ]);

        // شاشة مراقبة الطرود في لوحة الأدمن (المرسلة)
        $this->actingAs($this->adminUser, 'web')
            ->get(route('admin.parcels.index', ['direction' => 'sent', 'search' => 'ATQ-501']))
            ->assertOk()
            ->assertSee('ATQ-501')
            ->assertSee('مكتب الصقر الخارجي');

        // حتى بعد الحذف الناعم للرابط يظل اسم المكتب الخارجي محفوظاً عبر withTrashed()
        $link->delete();
        $this->assertSame('مكتب الصقر الخارجي - عتق', $shipment->fresh()->senderOfficeLabel());
        $this->assertSame('مكتب الصقر الخارجي - عتق', $parcel->fresh()->senderOfficeLabel());
    }

    public function test_receiving_external_parcel_copies_partner_city_to_source_region_automatically(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب البراق',
            'partner_city'   => 'مأرب',
            'token'          => Str::random(64),
            'receipt_prefix' => 'MRB-',
            'is_active'      => true,
        ]);

        $shipment = Shipment::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_number'          => 'EXT-26112402',
            'driver_name'              => 'أحمد السائق',
            'driver_phone'             => '774445556',
            'parcels_count'            => 1,
            'total_paid_amount'        => 1500,
            'total_deferred_amount'    => 0,
            'total_amount'             => 1500,
            'status'                   => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        $outgoing = OutgoingParcel::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_id'              => $shipment->id,
            'destination_office_id'    => $this->receivingOffice->id,
            'receipt_number'           => 'MRB-777',
            'sender_name'              => 'عمر المرسل',
            'sender_phone'             => '771002003',
            'recipient_name'           => 'ياسر المستلم',
            'recipient_phone'          => '779008007',
            'package_type'             => 'طرد ملابس',
            'payment_type'             => OutgoingPaymentType::PAID,
            'cod_amount'               => 1500,
            'status'                   => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        /** @var OutgoingParcelService $service */
        $service = app(OutgoingParcelService::class);
        $incomingParcel = $service->receiveAtDestination($outgoing, $this->receivingOffice, 'sms', false);

        $this->assertInstanceOf(Parcel::class, $incomingParcel);
        $this->assertSame('مأرب', $incomingParcel->source_region);
        $this->assertSame('مكتب البراق - مأرب', $incomingParcel->source_office_name);
        $this->assertSame('MRB-777', $incomingParcel->receipt_number);
        $this->assertSame(OutgoingParcelStatus::RECEIVED, $outgoing->fresh()->status);
    }

    public function test_rejected_shipment_is_excluded_from_all_incoming_queries_and_blocks_receiving(): void
    {
        $link = ExternalPartnerLink::create([
            'office_id'      => $this->receivingOffice->id,
            'partner_name'   => 'مكتب الوادي',
            'partner_city'   => 'بيحان',
            'token'          => Str::random(64),
            'receipt_prefix' => 'BHN-',
            'is_active'      => true,
        ]);

        $shipment = Shipment::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_number'          => 'EXT-REJ-99',
            'driver_name'              => 'فهد السائق',
            'driver_phone'             => '776665554',
            'parcels_count'            => 1,
            'total_paid_amount'        => 2000,
            'total_deferred_amount'    => 0,
            'total_amount'             => 2000,
            'status'                   => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
            'rejected_at'              => now(),
            'rejection_reason'         => 'كشف مرسل بالخطأ',
        ]);

        $parcel = OutgoingParcel::create([
            'office_id'                => null,
            'external_partner_link_id' => $link->id,
            'shipment_id'              => $shipment->id,
            'destination_office_id'    => $this->receivingOffice->id,
            'receipt_number'           => 'BHN-999',
            'sender_name'              => 'ناصر',
            'sender_phone'             => '771223344',
            'recipient_name'           => 'ماجد',
            'recipient_phone'          => '772334455',
            'package_type'             => 'أمانة',
            'payment_type'             => OutgoingPaymentType::PAID,
            'cod_amount'               => 2000,
            'status'                   => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'            => now(),
        ]);

        $this->assertSame(0, ShipmentService::countPendingReceivedShipments($this->receivingOffice));

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.index'))
            ->assertOk()
            ->assertDontSee('EXT-REJ-99');

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.shipments.received.show', $shipment))
            ->assertStatus(404);

        $this->actingAs($this->receivingOffice, 'office')
            ->get(route('office.transit-parcels.index'))
            ->assertOk()
            ->assertDontSee('BHN-999');

        $this->actingAs($this->receivingOffice, 'office')
            ->getJson(route('office.global-search', ['q' => 'BHN-999']))
            ->assertOk()
            ->assertJsonMissing(['receipt_number' => 'BHN-999']);

        // منع الاستلام الفردي أو بالباركود من شحنة مرفوضة
        try {
            app(ShipmentService::class)->receiveParcelByScan($shipment, $this->receivingOffice, 'BHN-999', 'sms', false);
            $this->fail('Expected DomainException when scanning a parcel from a rejected shipment.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('مرفوض', $e->getMessage());
        }

        try {
            app(OutgoingParcelService::class)->receiveAtDestination($parcel, $this->receivingOffice, 'sms', false);
            $this->fail('Expected DomainException when receiving a parcel belonging to a rejected shipment.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('مرفوض', $e->getMessage());
        }
    }
}
