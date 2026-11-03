<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Office\DashboardController;
use App\Http\Controllers\Office\ParcelController;
use App\Http\Controllers\Office\WhatsAppSettingsController;
use App\Http\Controllers\Office\FavoriteCustomerController;
use App\Http\Controllers\Office\Auth\LoginController;

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OfficeController as AdminOfficeController;
use App\Http\Controllers\Admin\ParcelController as AdminParcelController;
use App\Http\Controllers\Admin\WhatsappInstanceController as AdminWhatsappInstanceController;
use App\Http\Controllers\Admin\FeatureController as AdminFeatureController;

Route::get('/', [PageController::class, 'index'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/portfolio', [PageController::class, 'portfolio'])->name('portfolio');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/stats', [PageController::class, 'stats'])->name('stats');

Route::get('/login', fn () => redirect()->route('office.login'))->name('login');

/*
|--------------------------------------------------------------------------
| مسارات لوحة تحكم الإدارة المركزية (Super Admin)
|--------------------------------------------------------------------------
| معزولة تماماً وتعتمد على حارس auth:web وجدول users
*/
Route::prefix('admin')->name('admin.')->group(function () {

    // مسارات الزوار (غير المسجلين)
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');
    });

    // مسارات المسؤولين المسجلين (auth:web)
    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // لوحة التحكم الرئيسية والإحصائيات
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // إدارة المكاتب
        Route::resource('offices', AdminOfficeController::class);
        Route::get('/offices/{office}/features', [AdminOfficeController::class, 'features'])->name('offices.features');
        Route::put('/offices/{office}/features', [AdminOfficeController::class, 'updateFeatures'])->name('offices.features.update');
        Route::post('/offices/{office}/toggle-status', [AdminOfficeController::class, 'toggleStatus'])->name('offices.toggle_status');
        Route::post('/offices/{office}/toggle-daily-report', [AdminOfficeController::class, 'toggleDailyReport'])->name('offices.toggle_daily_report');
        Route::post('/offices/{office}/send-test-report', [AdminOfficeController::class, 'sendTestDailyReport'])->name('offices.send_test_daily_report');
        Route::post('/offices/{office}/update-password', [AdminOfficeController::class, 'updatePassword'])->name('offices.update_password');

        // إدارة كتالوج المميزات العامة
        Route::get('/features', [AdminFeatureController::class, 'index'])->name('features.index');
        Route::post('/features', [AdminFeatureController::class, 'store'])->name('features.store');
        Route::put('/features/{feature}', [AdminFeatureController::class, 'update'])->name('features.update');
        Route::post('/features/sync-all', [AdminFeatureController::class, 'syncAll'])->name('features.sync_all');

        // أدوات الواتساب المباشرة للمكتب (AJAX)
        Route::prefix('offices/{office}/whatsapp')->name('offices.whatsapp.')->group(function () {
            Route::get('/status', [AdminOfficeController::class, 'whatsappStatus'])->name('status');
            Route::post('/connect', [AdminOfficeController::class, 'whatsappConnect'])->name('connect');
            Route::get('/qr', [AdminOfficeController::class, 'whatsappQr'])->name('qr');
            Route::post('/pairing-code', [AdminOfficeController::class, 'whatsappPairingCode'])->name('pairing_code');
            Route::post('/disconnect', [AdminOfficeController::class, 'whatsappDisconnect'])->name('disconnect');
        });

        // مراقبة الطرود الشاملة
        Route::get('/parcels', [AdminParcelController::class, 'index'])->name('parcels.index');
        Route::patch('/parcels/{parcel}/status', [AdminParcelController::class, 'updateStatus'])->name('parcels.update_status');
        Route::delete('/parcels/{parcel}', [AdminParcelController::class, 'destroy'])->name('parcels.destroy');

        // إدارة الـ Instances للواتساب
        Route::resource('whatsapp-instances', AdminWhatsappInstanceController::class)->names('whatsapp_instances');
        Route::post('/whatsapp-instances/{whatsapp_instance}/toggle-status', [AdminWhatsappInstanceController::class, 'toggleStatus'])->name('whatsapp_instances.toggle_status');
        Route::post('/whatsapp-instances/{whatsapp_instance}/assign-office', [AdminWhatsappInstanceController::class, 'assignToOffice'])->name('whatsapp_instances.assign_office');
    });
});


Route::prefix('office')->name('office.')->group(function () {
    
    // مسارات الزوار (غير المسجلين)
    Route::middleware('guest:office')->group(function () {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    });

    // مسارات المسجلين (داخل جلسة المكتب)
    Route::middleware('auth:office')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
        
        // لوحة التحكم وتطبيق الطرود الرئيسي
        Route::get('/parcels', [ParcelController::class, 'index'])->name('parcels.index');
        Route::post('/parcels/quick-send-sms', [ParcelController::class, 'quickSendSms'])->name('parcels.quick_send_sms');
        Route::post('/parcels/quick-send-single', [ParcelController::class, 'quickSendSingle'])->name('parcels.quick_send_single');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // صفحة العملاء المميزين (PWA)
        Route::get('/favorite-customers', [FavoriteCustomerController::class, 'page'])->name('favorite_customers.index');

        // إعدادات الواتساب (Evolution API)
        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/', [WhatsAppSettingsController::class, 'index'])->name('index');
            Route::get('/status', [WhatsAppSettingsController::class, 'status'])->name('status');
            Route::post('/connect', [WhatsAppSettingsController::class, 'connect'])->name('connect');
            Route::get('/qr', [WhatsAppSettingsController::class, 'qrCode'])->name('qr');
            Route::post('/pairing-code', [WhatsAppSettingsController::class, 'pairingCode'])->name('pairing_code');
            Route::post('/disconnect', [WhatsAppSettingsController::class, 'disconnect'])->name('disconnect');
            Route::post('/save', [WhatsAppSettingsController::class, 'saveSettings'])->name('save');
        });

        // مسارات الـ API الخاصة بالمزامنة وقراءة البيانات للـ PWA بدون نت
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/parcels', [ParcelController::class, 'getParcelsData'])->name('parcels');
            Route::post('/parcels/sync', [ParcelController::class, 'syncUpdates'])->name('parcels.sync');
            Route::post('/parcels/{parcel}/resend-sms', [ParcelController::class, 'resendSms'])->name('parcels.resend_sms');
            Route::post('/parcels/{parcel}/resend-whatsapp', [ParcelController::class, 'resendWhatsapp'])->name('parcels.resend_whatsapp');

            // العملاء المميزون (لكل مكتب قائمته الخاصة)
            Route::get('/favorite-customers', [FavoriteCustomerController::class, 'index'])->name('favorite_customers.index');
            Route::post('/favorite-customers', [FavoriteCustomerController::class, 'store'])->name('favorite_customers.store');
            Route::match(['put', 'patch'], '/favorite-customers/{id}', [FavoriteCustomerController::class, 'update'])->name('favorite_customers.update');
            Route::delete('/favorite-customers/{id}', [FavoriteCustomerController::class, 'destroy'])->name('favorite_customers.destroy');
        });
    });
});



