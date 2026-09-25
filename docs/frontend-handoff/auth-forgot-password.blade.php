@extends('layouts.app')

@section('title', 'استعادة كلمة المرور')

@section('content')
    <div class="container-x flex justify-center py-12">
        <div class="card w-full max-w-md p-8">
            <h1 class="mb-2 text-center text-xl font-extrabold">استعادة كلمة المرور</h1>
            <p class="mb-6 text-center text-sm text-gray-500">
                أدخل بريدك الإلكتروني وسنرسل لك رابطًا لتعيين كلمة مرور جديدة.
            </p>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           required autofocus autocomplete="email" class="input">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button class="btn-gold w-full !py-3">إرسال رابط الاستعادة</button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                <a href="{{ route('login') }}" class="font-bold text-gold-600 hover:underline">العودة لتسجيل الدخول</a>
            </p>
        </div>
    </div>
@endsection
