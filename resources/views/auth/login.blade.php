@extends('layouts.nad')

@section('title', __('auth.login_title'))

@section('content')
    <div class="container-x flex justify-center py-12">
        <div class="card w-full max-w-md p-8">
            <h1 class="mb-6 text-center text-xl font-extrabold">{{ __('auth.login_title') }}</h1>

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">{{ __('auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
                    @error('email') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password">{{ __('auth.password') }}</label>
                    <input id="password" type="password" name="password" required class="input">
                    @error('password') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>

                {{-- كان لا يوجد أي رابط للاستعادة، وقوالب الاستعادة نفسها غير
                     موجودة — فمن نسي كلمته لا سبيل له إطلاقًا. --}}
                <div class="flex justify-end">
                    <a href="{{ route('password.request') }}" class="text-xs font-bold text-nad-champ hover:underline">
                        {{ __('auth.forgot_link') }}
                    </a>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-nad-mut select-none">
                    <input type="checkbox" name="remember" value="1"
                           class="h-4 w-4 rounded border-gray-300 accent-nad-brass">
                    تذكرني على هذا الجهاز
                </label>

                <button class="btn-gold w-full !py-3">{{ __('auth.login_btn') }}</button>
            </form>

            <p class="mt-6 text-center text-sm text-nad-mut">
                {{ __('auth.no_account') }}
                <a href="{{ route('register') }}" class="font-bold text-nad-champ hover:underline">{{ __('nav.register') }}</a>
            </p>
        </div>
    </div>
@endsection
