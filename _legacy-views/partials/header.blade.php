<header x-data="{ mobileMenu: false }" class="sticky top-0 z-50 border-b border-gray-100 bg-white/95 backdrop-blur">
    {{-- الشريط العلوي: اللغة والعملة --}}
    <div class="bg-navy-900 text-white">
        <div class="container-x flex h-9 items-center justify-between text-xs">
            <p class="hidden opacity-80 sm:block">{{ __('footer.tagline') }}</p>
            <div class="flex w-full items-center justify-end gap-4 sm:w-auto">
                <div class="flex items-center gap-1.5">
                    <x-shop-icon name="globe" class="h-3.5 w-3.5 opacity-60" />
                    <a href="{{ route('lang.switch', 'ar') }}" class="{{ app()->getLocale() === 'ar' ? 'font-bold text-gold-400' : 'opacity-70 hover:opacity-100' }}">عربي</a>
                    <span class="opacity-40">|</span>
                    <a href="{{ route('lang.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'font-bold text-gold-400' : 'opacity-70 hover:opacity-100' }}">EN</a>
                </div>
                <div class="flex items-center gap-1.5">
                    <a href="{{ route('currency.switch', 'usd') }}" class="{{ session('currency', 'usd') === 'usd' ? 'font-bold text-gold-400' : 'opacity-70 hover:opacity-100' }}">$</a>
                    <span class="opacity-40">|</span>
                    <a href="{{ route('currency.switch', 'syp') }}" class="{{ session('currency') === 'syp' ? 'font-bold text-gold-400' : 'opacity-70 hover:opacity-100' }}">{{ __('common.syp') }}</a>
                </div>
            </div>
        </div>
    </div>

    {{-- الهيدر الرئيسي --}}
    <div class="container-x flex h-16 items-center gap-3">
        <button class="rounded-lg p-2 hover:bg-navy-50 lg:hidden" @click="mobileMenu = !mobileMenu" aria-label="{{ __('nav.menu') }}">
            <x-shop-icon name="menu" class="h-6 w-6" />
        </button>

        @php
            // شعار المالك المرفوع من «الإعدادات ← الهوية البصرية» يتقدّم على
            // الافتراضي. وحقل الإعدادات يَعِد صراحةً بأنه «يُستخدم في الهيدر
            // والفوتر بدل الشعار الافتراضي» — والوعد كان غير منفَّذ في المتجر،
            // فلا يرى المالك شعاره بعد رفعه. هذا تنفيذه.
            // والشعار المرفوع يحمل الدرع والاسم معًا (كما ينصّ التلميح هناك)،
            // فيحلّ محلّ الصورة والنصّ معًا لا محلّ الصورة وحدها.
            $brandLogoPath = setting('logo_path');
            $brandLogo = $brandLogoPath
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandLogoPath)
                : null;
        @endphp

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
            @if ($brandLogo)
                <img src="{{ $brandLogo }}" alt="{{ setting('store_name_ar', 'نداف') }}" class="h-14 w-auto max-w-[200px] object-contain">
            @else
                <img src="{{ asset('images/logo-shield.svg') }}" alt="نداف" class="h-12 w-auto" width="40" height="48">
                <span class="flex flex-col items-start leading-none">
                    <span class="text-base font-extrabold tracking-wide text-navy-900">{{ setting('store_name_ar', 'نداف') }}</span>
                    <span class="mt-0.5 text-[10px] font-bold tracking-wide text-gold-600">أناقة تليق بك</span>
                </span>
            @endif
        </a>

        <form action="{{ route('search') }}" method="GET" class="relative hidden flex-1 md:block">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('nav.search_placeholder') }}"
                   class="input pe-4 ps-11 bg-cream">
            <x-shop-icon name="search" class="pointer-events-none absolute top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400 start-3.5" />
        </form>

        <div class="ms-auto flex items-center gap-1">
            @if (! inquiry_mode())
                <livewire:header-cart />
            @endif

            @auth
                <div x-data="{ open: false }" class="relative">
                    <button class="flex items-center gap-2 rounded-lg p-2 hover:bg-navy-50" @click="open = !open">
                        <x-shop-icon name="user" class="h-6 w-6 text-navy-900" />
                        <span class="hidden text-sm font-bold lg:block">{{ auth()->user()->name }}</span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak x-transition
                         class="absolute top-full mt-1 w-44 overflow-hidden rounded-lg border border-gray-100 bg-white py-1 shadow-lg end-0">
                        <a href="{{ route('account.profile') }}" class="block px-4 py-2.5 text-sm hover:bg-navy-50">{{ __('account.profile') }}</a>
                        <a href="{{ route('account.orders') }}" class="block px-4 py-2.5 text-sm hover:bg-navy-50">{{ __('account.my_orders') }}</a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ url('/admin') }}" class="block px-4 py-2.5 text-sm font-bold text-gold-600 hover:bg-navy-50">{{ __('nav.account') }} (Admin)</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="block w-full px-4 py-2.5 text-start text-sm text-red-600 hover:bg-red-50">{{ __('nav.logout') }}</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-outline hidden sm:inline-flex !px-4 !py-2">{{ __('nav.login') }}</a>
                <a href="{{ route('register') }}" class="btn-gold hidden sm:inline-flex !px-4 !py-2">{{ __('nav.register') }}</a>
                <a href="{{ route('login') }}" class="rounded-lg p-2 hover:bg-navy-50 sm:hidden">
                    <x-shop-icon name="user" class="h-6 w-6" />
                </a>
            @endauth
        </div>
    </div>

    {{-- شريط الأقسام (سطح المكتب) --}}
    <nav class="hidden border-t border-gray-100 lg:block">
        <div class="container-x flex h-11 items-center gap-6 text-sm font-bold">
            <a href="{{ route('home') }}" class="hover:text-gold-600">{{ __('nav.home') }}</a>
            @foreach ($headerCategories as $cat)
                <a href="{{ route('category.show', $cat->slug) }}" class="hover:text-gold-600">{{ $cat->name }}</a>
            @endforeach
            <a href="{{ route('contact') }}" class="hover:text-gold-600">{{ __('nav.contact') }}</a>
            @foreach ($footerPages as $page)
                <a href="{{ route('page.show', $page->slug) }}" class="opacity-70 hover:text-gold-600 hover:opacity-100">{{ $page->title }}</a>
            @endforeach
        </div>
    </nav>

    {{-- قائمة الموبايل --}}
    <div x-show="mobileMenu" x-cloak x-transition class="border-t border-gray-100 bg-white lg:hidden">
        <form action="{{ route('search') }}" method="GET" class="relative p-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('nav.search_placeholder') }}" class="input bg-cream pe-4 ps-11">
            <x-shop-icon name="search" class="pointer-events-none absolute top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400 start-6" />
        </form>
        <nav class="flex flex-col pb-3">
            <a href="{{ route('home') }}" class="px-4 py-2.5 text-sm font-bold hover:bg-navy-50">{{ __('nav.home') }}</a>
            @foreach ($headerCategories as $cat)
                <a href="{{ route('category.show', $cat->slug) }}" class="px-4 py-2.5 text-sm font-bold hover:bg-navy-50">{{ $cat->name }}</a>
            @endforeach
            <a href="{{ route('contact') }}" class="px-4 py-2.5 text-sm font-bold hover:bg-navy-50">{{ __('nav.contact') }}</a>
            @foreach ($footerPages as $page)
                <a href="{{ route('page.show', $page->slug) }}" class="px-4 py-2.5 text-sm font-bold opacity-70 hover:bg-navy-50 hover:opacity-100">{{ $page->title }}</a>
            @endforeach
            @auth
                <div class="my-2 border-t border-gray-100"></div>
                <a href="{{ route('account.profile') }}" class="px-4 py-2.5 text-sm font-bold hover:bg-navy-50">{{ __('account.profile') }}</a>
                <a href="{{ route('account.orders') }}" class="px-4 py-2.5 text-sm font-bold hover:bg-navy-50">{{ __('account.my_orders') }}</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ url('/admin') }}" class="px-4 py-2.5 text-sm font-bold text-gold-600 hover:bg-navy-50">{{ __('nav.account') }} (Admin)</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full px-4 py-2.5 text-start text-sm font-bold text-red-600 hover:bg-red-50">{{ __('nav.logout') }}</button>
                </form>
            @endauth
            @guest
                <div class="flex gap-2 px-4 pt-2">
                    <a href="{{ route('login') }}" class="btn-outline flex-1">{{ __('nav.login') }}</a>
                    <a href="{{ route('register') }}" class="btn-gold flex-1">{{ __('nav.register') }}</a>
                </div>
            @endguest
        </nav>
    </div>
</header>
