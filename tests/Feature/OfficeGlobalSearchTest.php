<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ParcelStatus;
use App\Models\Office;
use App\Models\OutgoingParcel;
use App\Models\Parcel;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OfficeGlobalSearchTest extends TestCase
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
            'name'                  => 'مكتب البحث الرئيسي',
            'branch_name'           => 'صنعاء',
            'whatsapp_sender_phone' => '770881101',
            'httpsms_from_phone'    => '+967770881101',
            'httpsms_api_key'       => 'test-search-a',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeB = Office::create([
            'name'                  => 'مكتب عدن الشريك',
            'branch_name'           => 'كريتر',
            'whatsapp_sender_phone' => '770881102',
            'httpsms_from_phone'    => '+967770881102',
            'httpsms_api_key'       => 'test-search-b',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->officeC = Office::create([
            'name'                  => 'مكتب ثالث معزول',
            'branch_name'           => 'تعز',
            'whatsapp_sender_phone' => '770881103',
            'httpsms_from_phone'    => '+967770881103',
            'httpsms_api_key'       => 'test-search-c',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);
    }

    public function test_dashboard_renders_global_search_bar_in_header_and_empty_query_runs_zero_queries(): void
    {
        $this->actingAs($this->officeA, 'office')
            ->get(route('office.dashboard'))
            ->assertOk()
            ->assertSee('بحث شامل: رقم السند، اسم أو رقم المرسل والمستلم...')
            ->assertSee(route('office.global-search'), false);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $emptyRes = $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => '   ']))
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertJson([
                'ok'         => true,
                'query'      => '',
                'total'      => 0,
                'incoming'   => [],
                'in_transit' => [],
                'outgoing'   => [],
            ]);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queryCount);
        $this->assertStringContainsString('no-store', (string) $emptyRes->headers->get('Cache-Control'));
    }

    public function test_global_search_finds_matching_parcels_across_incoming_in_transit_and_outgoing(): void
    {
        $this->officeA->syncFeaturesStatus(['outgoing_parcels']);
        $this->officeA->refreshFeaturesCache();

        // 1. طرد وارد في مكتب A
        $incoming = Parcel::create([
            'office_id'          => $this->officeA->id,
            'recipient_name'     => 'وليد الحداد',
            'recipient_phone'    => '771223344',
            'package_type'       => 'كرتون وارد مميز',
            'receipt_number'     => '505',
            'source_office_name' => 'مكتب عدن الشريك',
            'status'             => ParcelStatus::InOffice,
        ]);

        // 2. طرد قادم في الطريق من مكتب B إلى مكتب A
        $transit = OutgoingParcel::create([
            'office_id'             => $this->officeB->id,
            'destination_office_id' => $this->officeA->id,
            'sender_name'           => 'وليد الحداد مرسل',
            'sender_phone'          => '773445566',
            'recipient_name'        => 'سامي القحطاني',
            'recipient_phone'       => '771223344',
            'package_type'          => 'كيس في الطريق',
            'receipt_number'        => '505',
            'cod_amount'            => 6000,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        // 3. طرد صادر من مكتب A نحو مكتب B
        $outgoing = OutgoingParcel::create([
            'office_id'             => $this->officeA->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'طارق المرسل',
            'sender_phone'          => '775667788',
            'recipient_name'        => 'وليد الحداد مستلم',
            'recipient_phone'       => '771223344',
            'package_type'          => 'ضرف صادر',
            'receipt_number'        => '505',
            'cod_amount'            => 2500,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        // البحث برقم السند يجد الثلاثة معاً
        $byReceipt = $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => '505']))
            ->assertOk()
            ->assertJson([
                'ok'    => true,
                'total' => 3,
            ]);

        $this->assertSame($incoming->id, $byReceipt->json('incoming.0.id'));
        $this->assertSame($transit->id, $byReceipt->json('in_transit.0.id'));
        $this->assertSame($outgoing->id, $byReceipt->json('outgoing.0.id'));

        // البحث برقم جوال المرسل يجد الطرد الصادر
        $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => '775667788']))
            ->assertOk()
            ->assertJson([
                'ok'    => true,
                'total' => 1,
            ])
            ->assertJsonPath('outgoing.0.id', $outgoing->id);

        // البحث باسم المستلم يجد الطرد القادم في الطريق
        $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => 'سامي القحطاني']))
            ->assertOk()
            ->assertJson([
                'ok'    => true,
                'total' => 1,
            ])
            ->assertJsonPath('in_transit.0.id', $transit->id);
    }

    public function test_global_search_strictly_isolates_unrelated_office_parcels_and_normalizes_arabic_digits(): void
    {
        $this->officeA->syncFeaturesStatus(['outgoing_parcels']);
        $this->officeA->refreshFeaturesCache();

        // طرد يخص مكتب A
        $ownParcel = Parcel::create([
            'office_id'       => $this->officeA->id,
            'recipient_name'  => 'عميل مكتبنا',
            'recipient_phone' => '778899001',
            'package_type'    => 'كرتون مكتبنا',
            'receipt_number'  => '77',
            'status'          => ParcelStatus::InOffice,
        ]);

        // طرود بنفس الرقم والسند والاسم لكنها تتبع مكتباً ثالثاً معزولاً (officeC)
        Parcel::create([
            'office_id'       => $this->officeC->id,
            'recipient_name'  => 'عميل مكتب ثالث',
            'recipient_phone' => '778899001',
            'package_type'    => 'كرتون سري معزول',
            'receipt_number'  => '77',
            'status'          => ParcelStatus::InOffice,
        ]);

        OutgoingParcel::create([
            'office_id'             => $this->officeC->id,
            'destination_office_id' => $this->officeB->id,
            'sender_name'           => 'مرسل معزول',
            'sender_phone'          => '778899001',
            'recipient_name'        => 'مستلم معزول',
            'recipient_phone'       => '778899001',
            'package_type'          => 'صادر معزول',
            'receipt_number'        => '77',
            'cod_amount'            => 1000,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::IN_TRANSIT,
            'dispatched_at'         => now(),
        ]);

        // 1. البحث بأرقام عربية مشرقية (٧٧٨٨٩٩٠٠١) يجد طرد مكتب A فقط ولا يسرّب أي طرد من مكتب C
        $arabicRes = $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => '٧٧٨٨٩٩٠٠١']))
            ->assertOk()
            ->assertJson([
                'ok'         => true,
                'query'      => '778899001',
                'total'      => 1,
                'in_transit' => [],
                'outgoing'   => [],
            ]);

        $this->assertSame($ownParcel->id, $arabicRes->json('incoming.0.id'));
        $this->assertStringNotContainsString('معزول', $arabicRes->getContent());

        // 2. البحث مع مفتاح الدولة (+967) والمسافات يتطابق مع 778899001 بنجاح
        $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => '+967 77 889 9001']))
            ->assertOk()
            ->assertJson([
                'ok'    => true,
                'total' => 1,
            ])
            ->assertJsonPath('incoming.0.id', $ownParcel->id);

        // 3. البحث برقم سند عربي مع علامة # (#٧٧) يجد الطرد
        $this->actingAs($this->officeA, 'office')
            ->getJson(route('office.global-search', ['q' => '#٧٧']))
            ->assertOk()
            ->assertJson([
                'ok'    => true,
                'total' => 1,
            ])
            ->assertJsonPath('incoming.0.id', $ownParcel->id);

        // 4. مكتب B بدون ميزة outgoing_parcels لا يُرجع قسم outgoing حتى لو وُجدت سجلات، لكنه يرى in_transit
        OutgoingParcel::create([
            'office_id'             => $this->officeB->id,
            'destination_office_id' => $this->officeA->id,
            'sender_name'           => 'مرسل مكتب ب',
            'sender_phone'          => '779112233',
            'recipient_name'        => 'مستلم أ',
            'recipient_phone'       => '779445566',
            'package_type'          => 'كيس ب',
            'receipt_number'        => '88',
            'cod_amount'            => 1500,
            'payment_type'          => OutgoingPaymentType::PAID,
            'status'                => OutgoingParcelStatus::PENDING,
        ]);

        $this->assertFalse($this->officeB->hasFeature('outgoing_parcels'));
        $this->actingAs($this->officeB, 'office')
            ->getJson(route('office.global-search', ['q' => '779112233']))
            ->assertOk()
            ->assertJson([
                'ok'       => true,
                'total'    => 0,
                'outgoing' => [],
            ]);
    }
}
