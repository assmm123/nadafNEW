@extends('layouts.nad')

@section('title', __('cart.title'))

@section('content')
    <div class="container-x">
        {{-- ترويسة الغرفة بنمط nad.html (room-head) --}}
        <header class="nad-sec" style="padding-top:22px">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2><span class="dia">◆</span>{{ __('cart.title') }}</h2>
                    <p class="!mb-0 pb-1 text-sm text-nad-mut">{{ __('cart.review_note') }}</p>
                </div>
                <a href="{{ route('home') }}" class="nad-btn-ghost !px-4 !py-2 !text-xs">
                    {{ __('cart.continue_shopping') }}
                </a>
            </div>
        </header>

        {{-- جدول السلة — منطق Livewire cart-table كما هو مع غلافة nad فقط --}}
        <div class="nad-shell">
            <livewire:cart-table />
        </div>
    </div>
@endsection
