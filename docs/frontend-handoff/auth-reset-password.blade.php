@extends('layouts.app')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
    <div class="container-x flex justify-center py-12">
        <div class="card w-full max-w-md p-8">
            <h1 class="mb-2 text-center text-xl font-extrabold">تعيين كلمة مرور جديدة</h1>
            <p class="mb-6 text-center text-sm text-gray-500">
                اختر كلمة مرور جديدة لحسابك — 6 أحرف على الأقل.
            </p>

            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="label" for="email">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}"
                           required autocomplete="email" class="input">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="password">كلمة المرور الجديدة</label>
                    <input id="password" type="password" name="password" required
                           autocomplete="new-password" class="input">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="password_confirmation">تأكيد كلمة المرور</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                           autocomplete="new-password" class="input">
                </div>

                <button class="btn-gold w-full !py-3">تعيين كلمة المرور</button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                <a href="{{ route('login') }}" class="font-bold text-gold-600 hover:underline">العودة لتسجيل الدخول</a>
            </p>
        </div>
    </div>
@endsection
