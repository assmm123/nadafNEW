@extends('layouts.nad')

@section('title', __('auth.register_title'))

@section('content')
    <div class="container-x flex justify-center py-12">
        <div class="card w-full max-w-md p-8">
            <h1 class="mb-6 text-center text-xl font-extrabold">{{ __('auth.register_title') }}</h1>

            <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="name">{{ __('auth.name') }}</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus class="input">
                    @error('name') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="email">{{ __('auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required class="input">
                    @error('email') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="phone">{{ __('auth.phone') }}</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required dir="ltr" class="input">
                    @error('phone') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password">{{ __('auth.password') }}</label>
                    <input id="password" type="password" name="password" required class="input">
                    @error('password') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">{{ __('auth.confirm_password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required class="input">
                </div>

                <button class="btn-gold w-full !py-3">{{ __('auth.register_btn') }}</button>
            </form>

            <p class="mt-6 text-center text-sm text-nad-mut">
                {{ __('auth.have_account') }}
                <a href="{{ route('login') }}" class="font-bold text-nad-champ hover:underline">{{ __('nav.login') }}</a>
            </p>
        </div>
    </div>
@endsection
