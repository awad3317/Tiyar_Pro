<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SentShipmentsOfflineSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected Office $officeA;
    protected Office $officeB;
    protected Office $officeC;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->seed(FeatureSeeder::class);

        $this->officeA = Office::create([
            'name'                  => 'مكتب المزامنة الرئيسي',
            'branch_name'           => 'صنعاء',
            'whatsapp_sender_phone' => '770771101',
            'httpsms_from_phone'    => '+967770771101',
            'httpsms_api_key'       => 'test-sync-a',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeB = Office::create([
            'name'                  => 'مكتب عدن المستلم',
            'branch_name'           => 'المنصورة',
            'whatsapp_sender_phone' => '770771102',
            'httpsms_from_phone'    => '+967770771102',
            'httpsms_api_key'       => 'test-sync-b',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeC = Office::create([
            'name'                  => 'مكتب ثالث مستقل',
            'branch_name'           => 'تعز',
            'whatsapp_sender_phone' => '770771103',
            'httpsms_from_phone'    => '+967770771103',
            'httpsms_api_key'       => 'test-sync-c',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);
    }

    public function test_bootstrap_endpoint_returns_403_when_feature_disabled_and_returns_scoped_shipments_and_scannable_parcels(): void
    {
        // 1. بدون تفعيل ميزة sent_shipments يُرفض الوصول بـ 403
        $this->officeA->syncFeaturesStatus(['outgoing_parcels']);
        $this->officeA->refreshFeaturesCache();

        $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.shipments.sent.bootstrap'))
            ->assertForbidden();

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.sync'), ['operations' => []])
            ->assertForbidden();

        // 2. بعد تفعيل sent_shipments لمكتب A
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $shippedParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل كشف 1',
            'sender_phone'          => '771100201',
            'recipient_name'        => 'مستلم كشف 1',
            'recipient_phone'       => '772200201',
            'package_type'          => 'كرتون طباعة أوفلاين',
            'receipt_number'        => '101',
            'cod_amount'            => 3500,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $scannableParcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل جاهز للمسح',
            'sender_phone'          => '771100202',
            'recipient_name'        => 'مستلم جاهز للمسح',
            'recipient_phone'       => '772200202',
            'package_type'          => 'طرد جاهز للمسح',
            'receipt_number'        => '102',
            'cod_amount'            => 1500,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // بيانات تخص مكتب C (لا يجوز أن تظهر لمكتب A إطلاقاً)
        $otherOfficeParcel = OutgoingParcel::create([
            'office_id'             => $this->officeC->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل مكتب ثالث',
            'sender_phone'          => '773300303',
            'recipient_name'        => 'مستلم مكتب ثالث',
            'recipient_phone'       => '774400404',
            'package_type'          => 'طرد سري معزول',
            'receipt_number'        => '999',
            'cod_amount'            => 9000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $otherShipment = Shipment::create([
            'office_id'             => $this->officeC->id,
            'shipment_number'       => '261122999',
            'driver_name'           => 'سائق مكتب ثالث',
            'driver_phone'          => '775500505',
            'parcels_count'         => 1,
            'total_paid_amount'     => 9000,
            'total_deferred_amount' => 0,
            'total_amount'          => 9000,
            'destinations_summary'  => ['مكتب عدن المستلم - المنصورة'],
            'status'                => ShipmentStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        // إنشاء شحنة لمكتب A تحتوي على $shippedParcel
        $clientUuid = (string) Str::uuid();
        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), [
                'client_uuid'  => $clientUuid,
                'driver_name'  => 'سائق التمهيد',
                'driver_phone' => '776600606',
                'parcel_ids'   => [$shippedParcel->id],
                'dispatch_now' => true,
            ])
            ->assertCreated();

        $res = $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.shipments.sent.bootstrap'))
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertJson([
                'ok'     => true,
                'office' => [
                    'id'          => $this->officeA->id,
                    'name'        => 'مكتب المزامنة الرئيسي',
                    'branch_name' => 'صنعاء',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));

        // التحقق من الشحنة ولقطة الطرد المضمنة للطباعة الأوفلاين
        $shipments = $res->json('shipments');
        $this->assertCount(1, $shipments);
        $this->assertSame($clientUuid, $shipments[0]['client_uuid']);
        $this->assertSame($clientUuid, $shipments[0]['local_key']);
        $this->assertCount(1, $shipments[0]['parcels']);

        $embeddedParcel = $shipments[0]['parcels'][0];
        $this->assertSame($shippedParcel->id, $embeddedParcel['id']);
        $this->assertSame('101', $embeddedParcel['receipt_number']);
        $this->assertSame('مستلم كشف 1', $embeddedParcel['recipient_name']);
        $this->assertSame('772200201', $embeddedParcel['recipient_phone']);
        $this->assertSame('كرتون طباعة أوفلاين', $embeddedParcel['package_type']);
        $this->assertSame('مكتب عدن المستلم - المنصورة', $embeddedParcel['destination_label']);
        $this->assertSame('deferred', $embeddedParcel['payment_type']);
        $this->assertEquals(3500.0, (float) $embeddedParcel['cod_amount']);
        $this->assertSame('in_transit', $embeddedParcel['status']);
        $this->assertNotEmpty($embeddedParcel['status_label']);

        // التحقق من الطرود الجاهزة للمسح (فقط الطرد غير المضموم الخاص بمكتب A)
        $scannable = $res->json('scannable_parcels');
        $this->assertCount(1, $scannable);
        $this->assertSame($scannableParcel->id, $scannable[0]['id']);
        $this->assertSame('102', $scannable[0]['receipt_number']);

        // التأكد من عدم تسريب أي بيانات من مكتب C
        $rawContent = $res->getContent();
        $this->assertStringNotContainsString($otherOfficeParcel->id, $rawContent);
        $this->assertStringNotContainsString($otherShipment->shipment_number, $rawContent);

        // التحقق من تضمين محرك العرض والطباعة الأوفلاين في شاشة الشحنات المرسلة
        $sentPage = $this->actingAs($this->officeA, 'office')
            ->get(route('office.shipments.sent.index'))
            ->assertOk();

        $sentPage->assertSee('shipment-manifest-print.js?v=', false)
            ->assertSee('mursal-core.js?v=', false)
            ->assertSee('z-[80]', false)
            ->assertSee('data-offline-capable="true"', false)
            ->assertSee('printShipmentOffline', false)
            ->assertSee('MursalShipmentStore', false)
            ->assertSee('تم حفظ الكشف محلياً — ستتم المزامنة تلقائياً عند عودة الإنترنت.');
    }

    public function test_creating_shipment_twice_with_same_client_uuid_is_idempotent(): void
    {
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $parcel = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل عدم التكرار',
            'sender_phone'          => '771122331',
            'recipient_name'        => 'مستلم عدم التكرار',
            'recipient_phone'       => '772233441',
            'package_type'          => 'كرتون',
            'receipt_number'        => '205',
            'cod_amount'            => 4000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $clientUuid = (string) Str::uuid();
        $payload = [
            'client_uuid'  => $clientUuid,
            'driver_name'  => 'سائق الشبكة المتقطعة',
            'driver_phone' => '773344551',
            'parcel_ids'   => [$parcel->id],
            'dispatch_now' => true,
        ];

        // المحاولة الأولى: تنشئ الشحنة (201)
        $firstRes = $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), $payload)
            ->assertCreated()
            ->assertJson([
                'ok'                => true,
                'idempotent_replay' => false,
            ]);

        $firstShipmentId = $firstRes->json('shipment.id');
        $firstShipmentNumber = $firstRes->json('shipment.shipment_number');
        $this->assertNotEmpty($firstShipmentId);

        // المحاولة الثانية بنفس client_uuid (إعادة محاولة بعد انقطاع الشبكة): تُرجع نفس الشحنة بدون تكرار!
        $secondRes = $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.store'), $payload)
            ->assertOk()
            ->assertJson([
                'ok'                => true,
                'idempotent_replay' => true,
            ]);

        $this->assertSame($firstShipmentId, $secondRes->json('shipment.id'));
        $this->assertSame($firstShipmentNumber, $secondRes->json('shipment.shipment_number'));

        $this->assertSame(
            1,
            Shipment::query()->where('office_id', $this->officeA->id)->where('client_uuid', $clientUuid)->count()
        );
    }

    public function test_sync_endpoint_processes_queued_create_detach_and_cancel_operations_and_handles_duplicate_retries(): void
    {
        $this->officeA->syncPartnerOffices([$this->officeB->id]);
        $this->officeA->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->officeA->refreshFeaturesCache();

        $parcel1 = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل طابور 1',
            'sender_phone'          => '771199101',
            'recipient_name'        => 'مستلم طابور 1',
            'recipient_phone'       => '772299101',
            'package_type'          => 'طرد 1',
            'receipt_number'        => '301',
            'cod_amount'            => 2000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $parcel2 = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل طابور 2',
            'sender_phone'          => '771199102',
            'recipient_name'        => 'مستلم طابور 2',
            'recipient_phone'       => '772299102',
            'package_type'          => 'طرد 2',
            'receipt_number'        => '302',
            'cod_amount'            => 5000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $clientUuid = (string) Str::uuid();

        // 1. تنفيذ دفعة مرتبة: إنشاء شحنة بطردين ثم إخراج الطرد الثاني منها
        $batch1 = [
            'operations' => [
                [
                    'op_id'       => 'op_create_1',
                    'type'        => 'create_shipment',
                    'client_uuid' => $clientUuid,
                    'payload'     => [
                        'driver_name'  => 'سائق المزامنة',
                        'driver_phone' => '774499100',
                        'parcel_ids'   => [$parcel1->id, $parcel2->id],
                        'dispatch_now' => true,
                    ],
                ],
                [
                    'op_id'       => 'op_detach_1',
                    'type'        => 'detach_parcel',
                    'client_uuid' => $clientUuid,
                    'parcel_id'   => $parcel2->id,
                ],
            ],
        ];

        $syncRes1 = $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.sync'), $batch1)
            ->assertOk()
            ->assertJson([
                'ok'      => true,
                'results' => [
                    [
                        'op_id'             => 'op_create_1',
                        'type'              => 'create_shipment',
                        'client_uuid'       => $clientUuid,
                        'status'            => 'synced',
                        'idempotent_replay' => false,
                    ],
                    [
                        'op_id'             => 'op_detach_1',
                        'type'              => 'detach_parcel',
                        'client_uuid'       => $clientUuid,
                        'status'            => 'synced',
                        'idempotent_replay' => false,
                        'parcel_id'         => $parcel2->id,
                    ],
                ],
            ]);

        $shipment = Shipment::query()->where('client_uuid', $clientUuid)->firstOrFail();
        $this->assertSame(1, $shipment->parcels_count);
        $this->assertEquals(2000.0, (float) $shipment->total_paid_amount);
        $this->assertEquals(0.0, (float) $shipment->total_deferred_amount);

        // الطرد الثاني عاد إلى حالة الانتظار وأصبح ضمن scannable_parcels
        $parcel2->refresh();
        $this->assertNull($parcel2->shipment_id);
        $this->assertSame(OutgoingParcelStatus::PENDING, $parcel2->status);
        $this->assertSame($parcel2->id, $syncRes1->json('scannable_parcels.0.id'));

        // 2. إعادة إرسال نفس الدفعة (Duplicate Retry) + عملية إلغاء الكشف:
        // يجب أن تنجح الإعادتان كـ idempotent_replay = true ثم يُلغى الكشف بنجاح
        $batch2 = [
            'operations' => [
                [
                    'op_id'       => 'op_create_1_retry',
                    'type'        => 'create_shipment',
                    'client_uuid' => $clientUuid,
                    'payload'     => [
                        'driver_name'  => 'سائق المزامنة',
                        'driver_phone' => '774499100',
                        'parcel_ids'   => [$parcel1->id, $parcel2->id],
                        'dispatch_now' => true,
                    ],
                ],
                [
                    'op_id'       => 'op_detach_1_retry',
                    'type'        => 'detach_parcel',
                    'shipment_id' => $shipment->id,
                    'parcel_id'   => $parcel2->id,
                ],
                [
                    'op_id'       => 'op_cancel_1',
                    'type'        => 'cancel_shipment',
                    'shipment_id' => $shipment->id,
                ],
                [
                    'op_id'       => 'op_cancel_1_retry',
                    'type'        => 'cancel_shipment',
                    'client_uuid' => $clientUuid,
                ],
            ],
        ];

        $this->actingAs($this->officeA, 'office')
            ->postJson(route('office.shipments.sent.sync'), $batch2)
            ->assertOk()
            ->assertJson([
                'ok'      => true,
                'results' => [
                    [
                        'op_id'             => 'op_create_1_retry',
                        'status'            => 'synced',
                        'idempotent_replay' => true,
                    ],
                    [
                        'op_id'             => 'op_detach_1_retry',
                        'status'            => 'synced',
                        'idempotent_replay' => true,
                    ],
                    [
                        'op_id'             => 'op_cancel_1',
                        'status'            => 'synced',
                        'idempotent_replay' => false,
                    ],
                    [
                        'op_id'             => 'op_cancel_1_retry',
                        'status'            => 'synced',
                        'idempotent_replay' => true,
                    ],
                ],
            ]);

        $shipment->refresh();
        $parcel1->refresh();
        $this->assertSame(ShipmentStatus::CANCELLED, $shipment->status);
        $this->assertNull($parcel1->shipment_id);
        $this->assertSame(OutgoingParcelStatus::PENDING, $parcel1->status);
        $this->assertSame(1, Shipment::query()->where('client_uuid', $clientUuid)->count());
    }
}
