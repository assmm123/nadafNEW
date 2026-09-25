{{-- ختم التوثيق — صورة مرفوعة من الأدمن إن وجدت، وإلا رسم SVG تلقائي (ذهبي | أخضر) --}}
@props([
    'topText' => 'نداف',
    'bottomText' => now()->format('Y/m/d — H:i'),
    'size' => 180,
    'variant' => 'gold', // gold | green
    'image' => null,     // مسار صورة مرفوعة على disk public
])

@php
    if ($image) {
        $src = str_starts_with($image, 'images/')
            ? asset($image)
            : \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    }

    $c = match ($variant) {
        'green' => [
            'g1' => '#1E7A44', 'g2' => '#4CAF7D', 'g3' => '#2E8B57', 'g4' => '#14572F',
            'l1' => '#D6F2E1', 'l2' => '#4CAF7D', 'd1' => '#2E8B57', 'd2' => '#14572F',
            'hl' => '#E8FAF0',
        ],
        default => [
            'g1' => '#A8823C', 'g2' => '#DDBE72', 'g3' => '#C9A24B', 'g4' => '#8F6B25',
            'l1' => '#F3E0AC', 'l2' => '#C39A4A', 'd1' => '#C39A4A', 'd2' => '#8A671F',
            'hl' => '#F8ECC8',
        ],
    };

    $uid = uniqid('st2');
@endphp

@if ($image)
    <img src="{{ $src }}" alt="ختم التوثيق" width="{{ $size }}" height="{{ $size }}"
         class="select-none object-contain {{ $attributes->class('') }}"
         style="max-width: {{ $size }}px; max-height: {{ $size }}px;">
@else
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="{{ $size }}" height="{{ $size }}"
         {{ $attributes->merge(['class' => '']) }} aria-label="ختم التوثيق">
        <defs>
            <linearGradient id="{{ $uid }}-g" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="{{ $c['g1'] }}"/>
                <stop offset="0.35" stop-color="{{ $c['g2'] }}"/>
                <stop offset="0.6" stop-color="{{ $c['g3'] }}"/>
                <stop offset="1" stop-color="{{ $c['g4'] }}"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-l" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="{{ $c['l1'] }}"/>
                <stop offset="1" stop-color="{{ $c['l2'] }}"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-d" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="{{ $c['d1'] }}"/>
                <stop offset="1" stop-color="{{ $c['d2'] }}"/>
            </linearGradient>
            <path id="{{ $uid }}-top" d="M 26,120 A 94,94 0 0 1 214,120" fill="none"/>
            <path id="{{ $uid }}-bottom" d="M 30,120 A 90,90 0 0 0 210,120" fill="none"/>
        </defs>

        <g transform="rotate(-7 120 120)" opacity="0.95">
            <!-- الحلقات الخارجية -->
            <circle cx="120" cy="120" r="116" fill="none" stroke="url(#{{ $uid }}-g)" stroke-width="5"/>
            <circle cx="120" cy="120" r="108" fill="none" stroke="url(#{{ $uid }}-g)" stroke-width="1.4"/>
            <!-- الحلقة المنقطة الزخرفية -->
            <circle cx="120" cy="120" r="99" fill="none" stroke="url(#{{ $uid }}-g)" stroke-width="2"
                    stroke-dasharray="1.5 5" stroke-linecap="round"/>
            <!-- دائرة المركز -->
            <circle cx="120" cy="120" r="72" fill="none" stroke="url(#{{ $uid }}-g)" stroke-width="1.6"/>
            <circle cx="120" cy="120" r="68" fill="none" stroke="url(#{{ $uid }}-g)" stroke-width="0.8" opacity="0.7"/>

            <!-- الاسم أعلى الإطار -->
            <text font-family="Tajawal, Arial, sans-serif" font-size="21" font-weight="800"
                  fill="url(#{{ $uid }}-g)" letter-spacing="1.5" text-anchor="middle">
                <textPath href="#{{ $uid }}-top" startOffset="50%">{{ $topText }}</textPath>
            </text>

            <!-- التاريخ والوقت أسفل الإطار -->
            <text font-family="Tajawal, Arial, sans-serif" font-size="15" font-weight="700"
                  fill="url(#{{ $uid }}-g)" text-anchor="middle" direction="ltr">
                <textPath href="#{{ $uid }}-bottom" startOffset="50%">{{ $bottomText }}</textPath>
            </text>

            <!-- نجمتا الفصل -->
            <g fill="url(#{{ $uid }}-g)">
                <path d="M22,120 L25.5,113.5 L29,120 L25.5,126.5 Z"/>
                <path d="M218,120 L221.5,113.5 L225,120 L221.5,126.5 Z"/>
                <circle cx="25.5" cy="120" r="2.2"/>
                <circle cx="221.5" cy="120" r="2.2"/>
            </g>

            <!-- ربطة العنق المركزية الكبيرة -->
            <g>
                <!-- الياقة -->
                <polygon points="94,64 120,88 102,102" fill="url(#{{ $uid }}-l)"/>
                <polygon points="146,64 120,88 138,102" fill="url(#{{ $uid }}-d)"/>
                <!-- العقدة -->
                <polygon points="120,84 103,106 120,128" fill="url(#{{ $uid }}-l)"/>
                <polygon points="120,84 137,106 120,128" fill="url(#{{ $uid }}-d)"/>
                <!-- النصل الطويل بطية -->
                <polygon points="108,130 120,136 120,172 112,160 104,150" fill="url(#{{ $uid }}-l)"/>
                <polygon points="132,130 120,136 120,172 128,160 136,150" fill="url(#{{ $uid }}-d)"/>
                <polygon points="112,160 120,172 120,196 108,178" fill="url(#{{ $uid }}-l)"/>
                <polygon points="128,160 120,172 120,196 132,178" fill="url(#{{ $uid }}-d)"/>
                <!-- طية منتصف النصل -->
                <polygon points="104,150 120,158 136,150 128,160 120,164 112,160" fill="{{ $c['hl'] }}" opacity="0.55"/>
                <!-- لمعة -->
                <polyline points="108,130 104,150 112,160 108,178" fill="none" stroke="{{ $c['hl'] }}" stroke-width="2" opacity="0.7"/>
            </g>
        </g>
    </svg>
@endif
