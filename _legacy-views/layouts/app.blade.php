<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', app()->getLocale() === 'en' ? setting('store_name_en', 'NADAF') : setting('store_name_ar', 'نداف'))</title>
    <meta name="description" content="@yield('description', __('footer.tagline'))">
    {{-- وسوم المشاركة الاجتماعية — نفس منطق تخطيط نداف (هذا التخطيط يخدم البحث/التواصل/الدخول) --}}
    <meta property="og:site_name" content="{{ app()->getLocale() === 'en' ? setting('store_name_en', 'NADAF') : setting('store_name_ar', 'نداف') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', app()->getLocale() === 'en' ? setting('store_name_en', 'NADAF') : setting('store_name_ar', 'نداف'))">
    <meta property="og:description" content="@yield('description', __('footer.tagline'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', url('icons/icon-512.png'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#1A2A3A">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- خطوط نداف: نفس مصدر التخطيط الداكن. هذه الصفحات (الدخول · التسجيل · البحث ·
         التواصل · المحتوى · تفاصيل الطلب) كانت بلا أي خطوط إطلاقًا فتظهر بخط النظام --}}
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-white text-navy-900 antialiased">

    @include('partials.flash')
    @include('partials.header')
    <livewire:checkout-modal />

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

    {{-- الشات العائم — أجوبة جاهزة يديرها الأدمن --}}
    <livewire:floating-chat />

    {{-- تنبيه انقطاع الإنترنت --}}
    <div x-data="{ online: navigator.onLine }"
         @online.window="online = true"
         @offline.window="online = false"
         x-show="!online" x-cloak x-transition.opacity
         class="fixed inset-x-0 top-0 z-[100] bg-red-600 py-2.5 text-center text-sm font-extrabold text-white shadow-lg">
        ⚠️ {{ __('common.offline') }}
    </div>

    @livewireScripts
</body>
</html>
