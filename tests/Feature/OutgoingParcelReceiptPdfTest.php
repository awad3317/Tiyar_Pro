<?php

namespace Tests\Feature;

use App\Enums\OutgoingParcelStatus;
use App\Enums\OutgoingPaymentType;
use App\Enums\ShipmentStatus;
use App\Models\Office;
use App\Models\OfficeProfile;
use App\Models\OutgoingParcel;
use App\Models\Shipment;
use Database\Seeders\FeatureSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutgoingParcelReceiptPdfTest extends TestCase
{
    use DatabaseTransactions;

    protected Office $senderOffice;
    protected Office $targetOffice;
    protected Office $otherOffice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);

        $this->senderOffice = Office::create([
            'name'                  => 'مكتب النجم السريع',
            'branch_name'           => 'صنعاء - الستين',
            'whatsapp_sender_phone' => '770300401',
            'manager_phone'         => '770300400',
            'httpsms_from_phone'    => '+967770300401',
            'httpsms_api_key'       => 'test-key-pdf-sender',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->targetOffice = Office::create([
            'name'                  => 'مكتب النجم السريع',
            'branch_name'           => 'عدن - كريتر',
            'whatsapp_sender_phone' => '770300402',
            'manager_phone'         => '770300402',
            'httpsms_from_phone'    => '+967770300402',
            'httpsms_api_key'       => 'test-key-pdf-target',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        $this->otherOffice = Office::create([
            'name'                  => 'مكتب آخر مستقل',
            'branch_name'           => 'تعز',
            'whatsapp_sender_phone' => '770300403',
            'httpsms_from_phone'    => '+967770300403',
            'httpsms_api_key'       => 'test-key-pdf-other',
            'password'              => Hash::make('password123'),
            'is_active'             => true,
        ]);

        OfficeProfile::create([
            'office_id'         => $this->senderOffice->id,
            'primary_color'     => '#041627',
            'secondary_color'   => '#fe9d20',
            'tagline_primary'   => 'للشحن السريع والطرود بين المحافظات',
            'tagline_secondary' => 'خدمة يومية مضمونة وسريعة',
            'branch_address'    => 'صنعاء - شارع الستين جوار جولة عصر',
            'complaints_phone'  => '770300400',
            'dispatch_phone_1'  => '770300401',
            'receipt_title'     => 'سند إرسال',
            'slogan'            => 'رسالتك بأيدٍ أمينة',
            'footer_text'       => 'نظام مُرسَل للطرود',
        ]);

        OfficeProfile::create([
            'office_id'      => $this->targetOffice->id,
            'branch_address' => 'عدن - كريتر السوق الطويل',
            'delivery_phone' => '770300402',
        ]);

        Cache::forget('whatsapp_status_office_' . $this->senderOffice->id);
        Cache::forget('whatsapp_status_office_' . $this->otherOffice->id);
    }

    protected function createSampleParcel(string $receiptNumber = '777'): OutgoingParcel
    {
        return OutgoingParcel::create([
            'office_id'             => $this->senderOffice->id,
            'destination_office_id' => $this->targetOffice->id,
            'sender_name'           => 'عبدالله المرسل',
            'sender_phone'          => '771234567',
            'recipient_name'        => 'خالد المستلم',
            'recipient_phone'       => '777654321',
            'package_type'          => 'كرتون متوسط أبيض',
            'receipt_number'        => $receiptNumber,
            'cod_amount'            => 3500,
            'payment_type'          => OutgoingPaymentType::DEFERRED,
            'notes'                 => 'قابل للكسر يرجى العناية',
            'status'                => OutgoingParcelStatus::PENDING,
        ]);
    }

    public function test_pdf_endpoint_returns_valid_single_page_pdf_and_enforces_tenant_and_feature_guards(): void
    {
        $parcel = $this->createSampleParcel('777');

        // 1. بدون ميزة outgoing_parcels يُمنع بـ 403
        $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.receipt-pdf', $parcel))
            ->assertStatus(403);

        // 2. بعد تفعيل الميزة يعيد PDF عربي RTL سليم بصفحة واحدة 1/1
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        $response = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.receipt-pdf', $parcel))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline; filename="receipt-777.pdf"', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $bytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(5000, strlen($bytes));

        // التأكد أن الملف يتكون من صفحة واحدة فقط (1/1)
        preg_match_all('/\/Type\s*\/Page\b/', $bytes, $pageMatches);
        $this->assertCount(1, $pageMatches[0]);

        // 3. مكتب آخر (حتى مع تفعيل الميزة) يحصل على 403
        $this->otherOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->otherOffice->refreshFeaturesCache();

        $this->actingAs($this->otherOffice, 'office')
            ->get(route('office.outgoing-parcels.receipt-pdf', $parcel))
            ->assertStatus(403);
    }

    public function test_send_receipt_whatsapp_dispatches_pdf_to_recipient_via_evolution_go(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        $this->senderOffice->update([
            'whatsapp_base_url' => 'http://evolution-go.test:4000',
            'whatsapp_api_key'  => 'office-evo-key-999',
        ]);

        Http::fake([
            'http://evolution-go.test:4000/instance/status' => Http::response([
                'data' => [
                    'LoggedIn'  => true,
                    'Connected' => true,
                ],
            ], 200),
            'http://evolution-go.test:4000/send/media' => Http::response([
                'status' => 'ok',
                'data'   => ['id' => 'evo-msg-pdf-01'],
            ], 200),
        ]);

        $parcel = $this->createSampleParcel('888');

        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.send-receipt-whatsapp', $parcel))
            ->assertOk()
            ->assertJson([
                'ok'      => true,
                'message' => 'تم إرسال السند كملف PDF للمستلم عبر واتساب بنجاح.',
            ]);

        Http::assertSent(function (HttpClientRequest $request): bool {
            if ($request->url() !== 'http://evolution-go.test:4000/send/media') {
                return false;
            }

            $body = $request->data();
            $urlValue = (string) ($body['url'] ?? '');
            $prefix = 'data:application/pdf;base64,';
            if (! str_starts_with($urlValue, $prefix)) {
                return false;
            }

            $decoded = base64_decode(substr($urlValue, strlen($prefix)), true);
            if (! is_string($decoded) || ! str_starts_with($decoded, '%PDF-')) {
                return false;
            }

            return $request->hasHeader('Apikey', 'office-evo-key-999')
                && ($body['number'] ?? null) === '967777654321'
                && ($body['type'] ?? null) === 'document'
                && ($body['mimetype'] ?? null) === 'application/pdf'
                && ($body['filename'] ?? null) === 'receipt-888.pdf'
                && str_contains((string) ($body['caption'] ?? ''), '#888')
                && str_contains((string) ($body['caption'] ?? ''), 'مكتب النجم السريع');
        });
    }

    public function test_send_receipt_whatsapp_returns_arabic_error_when_not_configured_or_disconnected(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->senderOffice->refreshFeaturesCache();

        Http::fake();
        $parcel = $this->createSampleParcel('901');

        // 1. المكتب لم يضبط إعدادات واتساب بعد
        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.send-receipt-whatsapp', $parcel))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'يرجى ربط وتفعيل واتساب المكتب أولاً من إعدادات الواتساب.',
            ]);

        Http::assertNothingSent();

        // 2. المكتب مضبوط لكن جلسة Evolution Go غير متصلة
        $this->senderOffice->update([
            'whatsapp_base_url' => 'http://evolution-go.test:4000',
            'whatsapp_api_key'  => 'office-evo-key-disconnected',
        ]);
        Cache::forget('whatsapp_status_office_' . $this->senderOffice->id);

        Http::fake([
            'http://evolution-go.test:4000/instance/status' => Http::response([
                'data' => [
                    'LoggedIn'  => false,
                    'Connected' => false,
                ],
            ], 200),
        ]);

        $this->actingAs($this->senderOffice, 'office')
            ->postJson(route('office.outgoing-parcels.send-receipt-whatsapp', $parcel))
            ->assertStatus(422)
            ->assertJson([
                'ok'      => false,
                'message' => 'يرجى ربط وتفعيل واتساب المكتب أولاً من إعدادات الواتساب.',
            ]);

        Http::assertNotSent(fn (HttpClientRequest $r) => str_contains($r->url(), '/send/media'));

        // 3. مكتب آخر لا يستطيع إرسال سند طرد لا يتبعه (403)
        $this->otherOffice->syncFeaturesStatus(['outgoing_parcels']);
        $this->otherOffice->refreshFeaturesCache();

        $this->actingAs($this->otherOffice, 'office')
            ->postJson(route('office.outgoing-parcels.send-receipt-whatsapp', $parcel))
            ->assertStatus(403);
    }

    public function test_ui_views_and_sw_expose_whatsapp_and_share_pdf_actions(): void
    {
        $this->senderOffice->syncFeaturesStatus(['outgoing_parcels', 'sent_shipments']);
        $this->senderOffice->refreshFeaturesCache();

        $parcel = $this->createSampleParcel('505');
        $shipment = Shipment::create([
            'office_id'             => $this->senderOffice->id,
            'shipment_number'       => 'SHP-PDF-505',
            'driver_name'           => 'سائق تجريبي',
            'driver_phone'          => '771000222',
            'parcels_count'         => 1,
            'total_paid_amount'     => 0,
            'total_deferred_amount' => 3500,
            'total_amount'          => 3500,
            'destinations_summary'  => ['عدن - كريتر'],
            'status'                => ShipmentStatus::IN_TRANSIT,
        ]);
        $parcel->update([
            'shipment_id' => $shipment->id,
            'status'      => OutgoingParcelStatus::IN_TRANSIT,
        ]);

        $pdfUrl = route('office.outgoing-parcels.receipt-pdf', $parcel);
        $waUrl = route('office.outgoing-parcels.send-receipt-whatsapp', $parcel);

        // 1. صفحة الطرود الصادرة
        $indexHtml = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.outgoing-parcels.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-card-action="send-receipt-whatsapp"', $indexHtml);
        $this->assertStringContainsString('data-whatsapp-url="' . $waUrl . '"', $indexHtml);
        $this->assertStringContainsString('data-card-action="share-receipt-pdf"', $indexHtml);
        $this->assertStringContainsString('data-pdf-url="' . $pdfUrl . '"', $indexHtml);
        $this->assertStringContainsString('navigator.canShare', $indexHtml);

        // 2. صفحة تفاصيل الشحنة المرسلة
        $showHtml = $this->actingAs($this->senderOffice, 'office')
            ->get(route('office.shipments.sent.show', $shipment))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sendReceiptWhatsApp(', $showHtml);
        $this->assertStringContainsString('shareReceiptPdf(', $showHtml);
        $this->assertStringContainsString('printReceipt(', $showHtml);

        // 3. Service Worker يستثني مسارات سند PDF/واتساب صراحةً
        $swContent = file_get_contents(public_path('sw.js'));
        $this->assertIsString($swContent);
        $this->assertStringContainsString('outgoing-parcels', $swContent);
        $this->assertStringContainsString('receipt', $swContent);
    }
}
