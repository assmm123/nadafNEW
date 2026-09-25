@extends('layouts.nad')

@section('title', __('auth.reset_title'))

@section('content')
    <div class="container-x flex justify-center py-12">
        <div class="card w-full max-w-md p-8">
            <h1 class="mb-2 text-center text-xl font-extrabold">{{ __('auth.reset_title') }}</h1>
            <p class="mb-6 text-center text-sm text-nad-mut">{{ __('auth.reset_hint') }}</p>

            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf
                {{-- الرمز يُرسل مخفيًا: يصل في رابط البريد ولا يُعاد كتابته يدويًا --}}
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="label" for="email">{{ __('auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required class="input">
                    @error('email') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="password">{{ __('auth.new_password') }}</label>
                    <input id="password" type="password" name="password" required autofocus class="input">
                    @error('password') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="password_confirmation">{{ __('auth.confirm_password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required class="input">
                </div>

                <button class="btn-gold w-full !py-3">{{ __('auth.reset_btn') }}</button>
            </form>

            <p class="mt-6 text-center text-sm text-nad-mut">
                <a href="{{ route('login') }}" class="font-bold text-nad-champ hover:underline">{{ __('auth.back_to_login') }}</a>
            </p>
        </div>
    </div>
@endsection
