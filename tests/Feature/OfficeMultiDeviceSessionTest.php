<?php

namespace Tests\Feature;

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficeMultiDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveOffice(string $plainPassword = 'secret-password'): Office
    {
        return Office::create([
            'name'                  => 'مكتب الاختبار',
            'branch_name'           => 'الفرع الرئيسي',
            'whatsapp_sender_phone' => '771234567',
            'httpsms_from_phone'    => '731234567',
            'httpsms_api_key'       => 'test-api-key',
            'password'              => $plainPassword,
            'is_active'             => true,
        ]);
    }

    public function test_same_office_can_stay_logged_in_on_mobile_and_desktop_simultaneously(): void
    {
        $office = $this->createActiveOffice('pass12345');

        // 1. الجهاز الأول (الجوال) يسجل الدخول ويحصل على remember_token
        $mobileLogin = $this->post(route('office.login.submit'), [
            'phone'    => '771234567',
            'password' => 'pass12345',
        ]);
        $mobileLogin->assertRedirect(route('office.dashboard'));

        $office->refresh();
        $mobileRememberToken = $office->getRememberToken();
        $this->assertNotEmpty($mobileRememberToken);
        $mobilePasswordHash = $office->getAuthPassword();

        // محاكاة فتح جلسة جديدة مستقلة للجهاز الثاني (الكمبيوتر)
        $this->flushSession();
        Auth::forgetGuards();

        // 2. الجهاز الثاني (الكمبيوتر) يسجل الدخول بنفس الحساب
        $desktopLogin = $this->post(route('office.login.submit'), [
            'phone'    => '771234567',
            'password' => 'pass12345',
        ]);
        $desktopLogin->assertRedirect(route('office.dashboard'));
        $this->assertAuthenticatedAs($office, 'office');

        // التأكد من عدم تغير remember_token أو تجزئة كلمة المرور الخاصة بالجوال
        $office->refresh();
        $this->assertSame($mobileRememberToken, $office->getRememberToken());
        $this->assertSame($mobilePasswordHash, $office->getAuthPassword());

        // 3. تسجيل الخروج اليدوي من الكمبيوتر يخرج الكمبيوتر فقط دون إبطال remember_token للجوال
        $desktopLogout = $this->post(route('office.logout'));
        $desktopLogout->assertRedirect(route('office.login'));
        $this->assertGuest('office');

        $office->refresh();
        $this->assertSame($mobileRememberToken, $office->getRememberToken());
    }
}
