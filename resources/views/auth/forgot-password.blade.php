@extends('layouts.nad')

@section('title', __('auth.forgot_title'))

@section('content')
    <div class="container-x flex justify-center py-12">
        <div class="card w-full max-w-md p-8">
            <h1 class="mb-2 text-center text-xl font-extrabold">{{ __('auth.forgot_title') }}</h1>
            <p class="mb-6 text-center text-sm text-nad-mut">{{ __('auth.forgot_hint') }}</p>

            {{-- رسالة الحالة تُعرض هنا لا في الإشعار المنبثق: الإشعار المنبثق
                 يقرأ `success` و`error` فقط، وهذه الرسالة تُرسل بـ`status`. --}}
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">{{ __('auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
                    @error('email') <p class="mt-1 text-xs text-[#E9A3B2]">{{ $message }}</p> @enderror
                </div>

                <button class="btn-gold w-full !py-3">{{ __('auth.send_link') }}</button>
            </form>

            <p class="mt-6 text-center text-sm text-nad-mut">
                <a href="{{ route('login') }}" class="font-bold text-nad-champ hover:underline">{{ __('auth.back_to_login') }}</a>
            </p>
        </div>
    </div>
@endsection
