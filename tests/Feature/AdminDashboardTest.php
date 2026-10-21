<?php

namespace Tests\Feature;

use App\Enums\ParcelStatus;
use App\Models\Office;
use App\Models\Parcel;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    protected User $admin;
    protected Office $office;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@mursal.pro'],
            [
                'name' => 'المدير العام',
                'password' => Hash::make('password'),
            ]
        );

        $this->office = Office::firstOrCreate(
            ['whatsapp_sender_phone' => '771122334'],
            [
                'name' => 'مكتب صنعاء التجريبي',
                'branch_name' => 'حدة',
                'httpsms_from_phone' => '+967771122334',
                'httpsms_api_key' => 'test-key',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_login_page(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertSee('تسجيل دخول الإدارة المركزية');
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@mursal.pro',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin, 'web');
    }

    public function test_admin_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@mursal.pro',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_admin_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('لوحة التحكم والإحصائيات');
        $response->assertSee('المراقبة المباشرة للمكاتب');
    }

    public function test_admin_can_view_offices_index(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/offices');
        $response->assertStatus(200);
        $response->assertSee($this->office->name);
    }

    public function test_admin_can_view_office_edit_tabs(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get("/admin/offices/{$this->office->id}/edit");
        $response->assertStatus(200);
        $response->assertSee('إعدادات وقالب SMS');
        $response->assertSee('إعدادات وجلسة الواتساب');
        $response->assertSee('الأمان وكلمة المرور');
    }

    public function test_admin_can_toggle_office_status(): void
    {
        $initialStatus = $this->office->is_active;

        $response = $this->actingAs($this->admin, 'web')
            ->postJson("/admin/offices/{$this->office->id}/toggle-status");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->office->refresh();
        $this->assertEquals(!$initialStatus, $this->office->is_active);
    }

    public function test_admin_can_update_office_password(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->postJson("/admin/offices/{$this->office->id}/update-password", [
                'password' => 'newsecretpassword123',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->office->refresh();
        $this->assertTrue(Hash::check('newsecretpassword123', $this->office->password));
    }

    public function test_admin_can_view_parcels_monitor(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/parcels');
        $response->assertStatus(200);
        $response->assertSee('مراقبة وتتبع الطرود');
    }

    public function test_admin_can_view_whatsapp_instances(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/whatsapp-instances');
        $response->assertStatus(200);
        $response->assertSee('سيرفرات وحسابات الواتساب');
    }

    public function test_admin_can_create_whatsapp_instance(): void
    {
        $response = $this->actingAs($this->admin, 'web')->post('/admin/whatsapp-instances', [
            'name' => 'Instance Test',
            'instance_id' => 'test_inst_' . uniqid(),
            'pin_code' => '998877',
            'phone_number' => '770001122',
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/whatsapp-instances');
        $this->assertDatabaseHas('whatsapp_instances', ['name' => 'Instance Test']);
    }

    public function test_admin_can_logout(): void
    {
        $response = $this->actingAs($this->admin, 'web')->post('/admin/logout');
        $response->assertRedirect('/admin/login');
        $this->assertGuest('web');
    }
}
