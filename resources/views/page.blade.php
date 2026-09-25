@extends('layouts.nad')

@section('title', $page->title)

@section('content')
    <div class="container-x mt-6 max-w-4xl">
        <h1 class="mb-6 text-2xl font-extrabold">{{ $page->title }}</h1>

        {{-- النص العام إن وجد --}}
        @if ($page->content)
            <div class="card prose prose-sm max-w-none p-6 leading-8 sm:p-8 [&_h3]:font-extrabold [&_strong]:text-nad-ivory">
                {!! \Illuminate\Support\Str::markdown($page->content ?? '') !!}
            </div>
        @endif

        {{-- الأقسام الديناميكية — من نحن الغنية --}}
        @forelse ($page->sections as $section)
            <section class="card mt-6 overflow-hidden p-6 sm:p-8" wire:key="section-{{ $section->id }}">
                @if ($section->heading)
                    <h2 class="mb-4 flex items-center gap-3 text-xl font-extrabold">
                        <span class="h-6 w-1.5 rounded-full bg-nad-brass"></span>
                        {{ $section->heading }}
                    </h2>
                @endif

                @php $images = $section->mediaUrls($section->images); $videos = $section->mediaUrls($section->videos); @endphp

                {{-- تخطيط نص + صور جانبًا لجانب --}}
                @if ($section->layout === 'split' && $section->body && count($images))
                    <div class="grid items-center gap-6 md:grid-cols-2">
                        <div class="whitespace-pre-line text-[15px] leading-8 text-nad-mut">{{ $section->body }}</div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($images as $img)
                                <img src="{{ $img }}" alt="{{ $section->heading }}" loading="lazy"
                                     class="aspect-square w-full rounded-xl object-cover shadow-sm transition hover:scale-[1.02]">
                            @endforeach
                        </div>
                    </div>
                @else
                    @if ($section->body)
                        <div class="whitespace-pre-line text-[15px] leading-8 text-nad-mut">{{ $section->body }}</div>
                    @endif

                    @if (count($images))
                        <div class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                            @foreach ($images as $img)
                                <img src="{{ $img }}" alt="{{ $section->heading }}" loading="lazy"
                                     class="aspect-square w-full rounded-xl object-cover shadow-sm transition hover:scale-[1.02]">
                            @endforeach
                        </div>
                    @endif

                    @if (count($videos))
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach ($videos as $vid)
                                <video src="{{ $vid }}" controls preload="metadata"
                                       class="w-full max-h-80 rounded-xl bg-nad-bg shadow-sm"></video>
                            @endforeach
                        </div>
                    @endif
                @endif
            </section>
        @empty
        @endforelse
    </div>
@endsection
