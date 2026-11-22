<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Office;
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
}
