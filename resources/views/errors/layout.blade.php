@php
    // صفحات الأخطاء قد تُرسم بلا وسيط اللغة ومع قاعدة بيانات متعطلة، فلا نصدر
    // استثناءً ثانيًا هنا: للاسم بديل ثابت إن فشلت قراءة الإعدادات.
    $nadLoc = app()->getLocale() === 'en' ? 'en' : 'ar';
    try {
        $nadStore = $nadLoc === 'en' ? setting('store_name_en', 'NADAF') : setting('store_name_ar', 'نداف');
        $nadPhone = setting('store_phone');
        // شعار المالك المرفوع يتقدّم على الافتراضي هنا أيضًا — وإلا ظهر شعار
        // غير شعار المتجر في أحرج لحظة (صفحة خطأ).
        $nadLogoPath = setting('logo_path');
        $nadLogo = $nadLogoPath
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($nadLogoPath)
            : asset('images/logo-shield.svg');
    } catch (\Throwable) {
        $nadStore = $nadLoc === 'en' ? 'NADAF' : 'نداف';
        $nadPhone = null;
        $nadLogo = asset('images/logo-shield.svg');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $nadLoc }}" dir="{{ $nadLoc === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} — {{ $nadStore }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Tajawal, 'Segoe UI', Tahoma, Arial, sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            background: linear-gradient(150deg, #101c2c 0%, #1a2a3a 55%, #22354a 100%);
            color: #fff;
        }
        .card {
            max-width: 460px; width: 100%;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(201,168,76,.35);
            border-radius: 22px;
            padding: 44px 36px;
            text-align: center;
            box-shadow: 0 18px 60px rgba(0,0,0,.45);
        }
        .logo { height: 64px; width: auto; margin-bottom: 8px; }
        .code {
            font-size: 96px; font-weight: 800; line-height: 1.1;
            background: linear-gradient(120deg, #c9a84c, #eedb9e 55%, #c9a84c);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            letter-spacing: 4px;
        }
        h1 { font-size: 24px; font-weight: 800; margin: 6px 0 12px; }
        p { font-size: 14.5px; line-height: 2; color: #b9c4d0; margin-bottom: 26px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: 11px 26px; border-radius: 10px;
            font-size: 14px; font-weight: 800; text-decoration: none; transition: all .2s;
            /* الخلفية الشفافة ضرورية لعنصر <button>: بدونه يأخذ خلفية الزر
               الافتراضية (بيضاء) فيبدو غريبًا بين روابط شفافة. */
            background: transparent; font-family: inherit; cursor: pointer;
        }
        .btn-gold { background: linear-gradient(120deg, #c9a84c, #e2c578); color: #1a2a3a; }
        .btn-gold:hover { filter: brightness(1.08); box-shadow: 0 6px 18px rgba(201,168,76,.35); }
        .btn-ghost { border: 1.5px solid rgba(201,168,76,.5); color: #e2c578; background: transparent; }
        .btn-ghost:hover { background: rgba(201,168,76,.12); }
        .hint { margin-top: 22px; font-size: 12px; color: #7a8a9a; }
    </style>
</head>
<body>
    <main class="card">
        <img src="{{ $nadLogo }}" alt="{{ $nadStore }}" class="logo">
        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-gold">{{ __('common.back_home') }}</a>
            <a href="javascript:history.back()" class="btn btn-ghost">{{ __('common.back_previous') }}</a>
            @if (! empty($logout))
                {{-- مخرج حقيقي من حالة 403: الحساب الحالي ليس حساب إدارة،
                     فيلزم الخروج منه للدخول بحساب صحيح. --}}
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-ghost">{{ __('auth.logout') }}</button>
                </form>
            @endif
        </div>
        <p class="hint">{{ $nadStore }} @if ($nadPhone) — {{ $nadPhone }} @endif</p>
    </main>
</body>
</html>
