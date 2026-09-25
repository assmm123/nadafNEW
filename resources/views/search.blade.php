@extends('layouts.nad')

@section('title', __('search.title'))

@section('content')
    <div class="container-x mt-6">
        <h1 class="mb-6 text-2xl font-extrabold">
            @if ($q !== '')
                {{ __('search.results_for', ['q' => $q]) }}
            @else
                {{ __('search.title') }}
            @endif
        </h1>

        <form action="{{ route('search') }}" method="GET" class="relative mb-8 max-w-xl">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('nav.search_placeholder') }}" class="input bg-nad-bg ps-11" autofocus>
            <x-shop-icon name="search" class="pointer-events-none absolute top-1/2 h-5 w-5 -translate-y-1/2 text-nad-dim start-3.5" />
        </form>

        @if ($products->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('partials.nad-product-card', ['product' => $product])
                @endforeach
            </div>
            <div class="mt-8">{{ $products->links() }}</div>
        @else
            <div class="card flex flex-col items-center gap-3 p-12 text-center">
                <x-shop-icon name="search" class="h-10 w-10 text-nad-dim" />
                <p class="font-bold">{{ __('search.no_results') }}</p>
                <a href="{{ route('home') }}" class="btn-outline">{{ __('cart.continue_shopping') }}</a>
            </div>
        @endif
    </div>
@endsection
