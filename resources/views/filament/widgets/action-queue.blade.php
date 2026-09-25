{{-- الطبقة الأولى: يحتاج إجراءك الآن — بطاقات قابلة للنقر --}}
@php $cards = $this->getCards(); @endphp

<div class="space-y-3">
    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-[#D2A24E]">يحتاج إجراءك الآن</p>

    @if ($cards === [])
        <div class="rounded-xl border border-white/10 bg-[#171D26] p-5 text-sm text-[#A79F8F]">
            لا مهام معلّقة في نطاق صلاحياتك.
        </div>
    @else
        <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            @foreach ($cards as $card)
                @php
                    $zero = $card['count'] === 0;
                    $tone = [
                        'warn' => 'text-[#EAC97F] border-[#D2A24E]/40',
                        'brass' => 'text-[#EAC97F] border-[#D2A24E]/40',
                        'danger' => 'text-[#E9A3B2] border-[#8E3B4E]/45',
                        'info' => 'text-[#9DC0DC] border-[#6C93B4]/45',
                        'muted' => 'text-[#8A93A3] border-white/10',
                    ][$card['tone']] ?? 'text-[#EAC97F] border-[#D2A24E]/40';
                @endphp

                @if ($card['url'] && ! $zero)
                    <a href="{{ $card['url'] }}"
                       class="block rounded-xl border {{ $tone }} bg-[#171D26] p-4 transition hover:bg-[#1B222D]">
                        <b class="block text-[26px] font-medium leading-tight">{{ $card['count'] }}</b>
                        <span class="text-[11.5px] text-[#A79F8F]">{{ $card['label'] }}</span>
                    </a>
                @else
                    <div class="rounded-xl border border-white/10 bg-[#171D26] p-4 {{ $zero ? 'opacity-45' : '' }}">
                        <b class="block text-[26px] font-medium leading-tight {{ $zero ? 'text-[#8A93A3]' : $tone }}">{{ $card['count'] }}</b>
                        <span class="text-[11.5px] {{ $zero ? 'text-[#8A93A3]' : 'text-[#A79F8F]' }}">{{ $card['label'] }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
