@extends('layouts.nad')

@section('title', __('contact.title'))

@section('content')
    <div class="container-x mt-6 max-w-4xl pb-10">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-extrabold">{{ __('contact.title') }}</h1>
            <p class="mt-2 text-sm text-nad-mut">{{ __('contact.subtitle') }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($contactMethods as $method)
                <a href="{{ $method->link() }}" target="_blank" rel="noopener"
                   class="card flex items-center gap-4 p-5 transition hover:border-nad-brass hover:shadow-md" wire:key="cm-{{ $method->id }}">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-nad-surface2 text-nad-brass">
                        <x-shop-icon name="{{ \App\Models\CommunicationMethod::TYPES[$method->type]['icon'] ?? 'globe' }}" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-extrabold">{{ $method->label ?? $method->typeLabel() }}</p>
                        <p class="mt-0.5 truncate text-sm text-nad-mut" dir="ltr">{{ $method->value }}</p>
                    </div>
                    <x-shop-icon name="chevron" class="ms-auto h-4 w-4 shrink-0 text-nad-dim rtl:rotate-180" />
                </a>
            @endforeach
        </div>
    </div>
@endsection
