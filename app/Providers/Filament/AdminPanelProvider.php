<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureUserIsAdmin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('نداف | NADAF')
            // لوغو الأدمن: الصورة المرفوعة من الإعدادات أولًا، وإلا الشعار الافتراضي
            ->brandLogo(fn () => \App\Models\Setting::get('logo_path')
                ? (\Illuminate\Support\Facades\Storage::disk('public')->url(\App\Models\Setting::get('logo_path')))
                : asset('images/logo-admin.svg'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                // النحاسي = نفس لون المتجر. كان #C9A84C (ذهبي قديم) فهويّتان لمتجر واحد.
                'primary' => Color::hex('#D2A24E'),
                'gray' => Color::Slate,
            ])
            // ترتيب المجموعات معرَّف صراحةً — بدونه كان ترتيب المجموعات نفسه
            // غير مضمون (Filament يحسمه بترتيب تسجيل الموارد).
            ->navigationGroups([
                'الطلبات',
                'الكتالوج',
                'المخزون والمشتريات',
                'المحتوى',
                // قسم موحّد: الأسئلة والأجوبة + سجل المحادثات + دليل البوت التفاعلي
                'الدردشة والبوت',
                'التقارير',
                'الإعدادات',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // لوحة تحكم مخصّصة بدل Filament\Pages\Dashboard: ودجات Filament
                // تُغلَّف بهيكلها فتظهر بغير التصميم المطلوب، والصفحة المخصّصة
                // تمنح تحكمًا كاملًا بالترتيب والمقاسات والنصوص.
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->viteTheme('resources/css/admin.css')
            ->darkMode(true)
            // الوضع يتبع نفس كوكي المتجر (nad_theme) — فيتّحد الوضعان في المنصة كلها.
            // وFilament يضع الكلاس على <html> من الخادم فلا يوجد وميض.
            ->defaultThemeMode(
                request()->cookie('nad_theme') === 'light'
                    ? \Filament\Enums\ThemeMode::Light
                    : \Filament\Enums\ThemeMode::Dark
            )
            // خطوط المتجر نفسها (Tajawal + El Messiri) عبر نفس مصدر Vite
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_START,
                fn () => \Illuminate\Support\Facades\Vite::fonts(),
            )
            // بلا ودجات إضافية: كل اللوحة تُبنى من App\Filament\Widgets بالترتيب
            // المطلوب (يحتاج إجراءك ← المحاسبة ← المركز المالي ← أحدث الطلبات).
            // أُزيل AccountWidget — بطاقة ترحيب افتراضية بلا قيمة تشغيلية.
            ->widgets([])
            ->middleware([
                // ⚠️ هنا لا في `authMiddleware`: تلك لا تُطبَّق على صفحة الدخول
                // (فيجب أن تُفتح بلا مصادقة)، فكانت الحماية تتجاوزها ويبقى
                // `/admin/login` مكشوفًا على النفق العام.
                \App\Http\Middleware\BlockRemoteAdmin::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // isPersistent: true → تُنفَّذ على كل طلب Livewire وليس أول تحميل فقط.
            // بدونها يبقى EnsureUserIsAdmin بلا أثر على نداءات AJAX، فيستطيع من
            // سُحبت منه صلاحية الأدمن أثناء فتح اللوحة تنفيذ الأزرار (تغيير حالة طلب،
            // تأكيد فاتورة شراء، تسوية جرد) لأن فحص الدخول وحده يعمل حينها.
            ->authMiddleware([
                Authenticate::class,
                EnsureUserIsAdmin::class,
            ], isPersistent: true);
    }
}
