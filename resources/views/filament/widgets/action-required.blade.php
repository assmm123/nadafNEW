<div class="nad-ocard !p-0 overflow-hidden">
    <div class="pt-3 px-4">
        <span class="nad-chip nad-chip-br">◆ شو عليك هلق؟</span>
    </div>
    <div class="p-3 pt-2 space-y-2">
        @foreach ($actions as $a)
            <a href="{{ $a['url'] }}"
               class="flex items-center justify-between gap-3 border border-nad-line2 bg-nad-surface2 px-4 py-3 transition hover:border-nad-brass group">
                <span class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $a['color'] === 'red' ? 'bg-nad-ox/20 text-red-300' : ($a['color'] === 'blue' ? 'bg-nad-brass/10 text-nad-champ' : 'bg-nad-brass/20 text-nad-brass') }}">
                        <x-shop-icon name="{{ $a['icon'] }}" class="h-4 w-4" />
                    </span>
                    <b class="text-sm font-extrabold text-nad-ivory">{{ $a['label'] }}</b>
                </span>
                <span class="text-nad-dim group-hover:text-nad-champ transition rtl:rotate-180">
                    <svg viewBox="0 0 24 24" class="w-4 h-4"><path d="m14.5 6-6 6 6 6"/></svg>
                </span>
            </a>
        @endforeach
    </div>
</div>
