{{-- `container-x` يوفّر الحاوية (عرض أقصى وحشو). و`nad-portal` كان صنفًا
     ميتًا غير معرَّف في أي ملف CSS — أُزيل فلا فائدة من صنف لا يفعل شيئًا. --}}
<div class="container-x py-6" wire:key="my-products-root">

    {{-- ═══ البوابة: كرت لكل قسم (نمط nad-gcard كامل) ═══ --}}
    @if (! $openCategory)
        <div class="nad-sec nad-sec-sm !mb-4">
            <h2 class="!text-2xl"><span class="dia">◆</span>منتجاتي</h2>
        </div>

        {{-- ثلاثة أعمدة لا أربعة: محتوى اللوحة أضيق من المتجر (الشريط الجانبي
             يأخذ عرضه)، وفي أربعة تتضاغط البطاقة فيتراكب عنوانها مع أيقونتها. --}}
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" wire:key="gate">
            @forelse ($categories as $cat)
                <button type="button" wire:click="openCategory({{ $cat['id'] }})"
                        class="nad-gcard" wire:key="pcat-{{ $cat['id'] }}">
                    @if ($cat['image'])
                        <img class="ph" src="{{ $cat['image'] }}" alt="{{ $cat['name'] }}" loading="lazy">
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-[#1B222D] to-[#0F1319]"></div>
                        <x-heroicon-o-cube class="absolute left-1/2 top-1/2 h-14 w-14 -translate-x-1/2 -translate-y-1/2 text-nad-brass/40" />
                    @endif
                    <span class="nad-shade"></span>
                    <span class="nad-gicon"><x-heroicon-o-cube class="h-5 w-5" /></span>
                    @if ($cat['out'] > 0)
                        <span class="nad-gbadge">{{ $cat['out'] }} نافد</span>
                    @endif
                    <div class="nad-gmeta">
                        <div>
                            <h3 class="font-display">{{ $cat['name'] }}</h3>
                            <p>{{ $cat['count'] }} منتج</p>
                        </div>
                        <span class="nad-gpill"><b>{{ $cat['count'] }}</b><small>منتج</small></span>
                    </div>
                </button>
            @empty
                <div class="col-span-full nad-empty">
                    <svg viewBox="0 0 24 24"><path d="M6.5 8h11l-1 12.5a1.5 1.5 0 0 1-1.5 1.4H9a1.5 1.5 0 0 1-1.5-1.4L6.5 8Z"/><path d="M9 10V6.8a3 3 0 0 1 6 0V10"/></svg>
                    <h3>لا أقسام بعد</h3>
                    <p>أضف أول قسم من صفحة الأقسام</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6 text-center">
            <a href="{{ \App\Filament\Resources\ProductResource::getUrl('index') }}"
               class="nad-btn-ghost">
                <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M3.5 7h17M3.5 12h17M3.5 17h17"/></svg>
                الجرد الكلي — جدول كل المنتجات ({{ $totalProducts }})
            </a>
        </div>

    {{-- ═══ منتجات القسم المعزولة — كروت nad ═══ --}}
    @else
        <div class="nad-room-head">
            <div class="container-x nad-room-row">
                <button type="button" wire:click="closeCategory" aria-label="رجوع للأقسام" class="nad-room-back">
                    <svg viewBox="0 0 24 24" class="h-4 w-4"><path d="M14.5 6l-6 6 6 6"/></svg>
                    رجوع للأقسام
                </button>
                @if ($openCategory['image'])
                    <img src="{{ $openCategory['image'] }}" alt="" class="h-12 w-12 object-cover" style="border-radius:10px">
                @else
                    <span class="flex h-12 w-12 items-center justify-center bg-nad-surface2 text-nad-brass" style="border-radius:10px">
                        <x-heroicon-o-cube class="h-6 w-6" />
                    </span>
                @endif
                <div class="nad-room-title">
                    <h2>{{ $openCategory['name'] }}</h2>
                    <small>{{ $openProducts->count() }} منتج — {{ $openCategory['out'] }} نافد</small>
                </div>
                <span class="nad-room-count"><i class="dot"></i><b>{{ $openProducts->count() }}</b></span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 px-4 pt-4">
            <a href="{{ \App\Filament\Resources\ProductResource::getUrl('create') }}" class="nad-btn-brass !py-2 !px-4 !text-xs">
                ＋ منتج جديد في هذا القسم
            </a>
            <a href="{{ \App\Filament\Resources\ProductResource::getUrl('index', ['category' => $openCategory['id']]) }}"
               class="nad-btn-ghost !py-2 !px-4 !text-xs">جدول هذا القسم</a>
        </div>

        <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($openProducts as $p)
                @php $m = $p['model']; @endphp
                <article class="nad-ocard overflow-hidden" wire:key="pp-{{ $m->id }}">
                    <div class="relative aspect-square overflow-hidden bg-nad-surface2">
                        @if ($p['image'])
                            <img src="{{ $p['image'] }}" alt="{{ $m->name_ar }}" loading="lazy"
                                 class="h-full w-full object-cover transition duration-500 hover:scale-105"
                                 style="filter:sepia(.28) saturate(1.15) contrast(1.04) brightness(.94)">
                        @else
                            <div class="flex h-full items-center justify-center text-nad-dim"><x-heroicon-o-cube class="h-12 w-12" /></div>
                        @endif

                        {{-- حالة المخزون — من stockInfo() نفسها التي يعرضها المتجر --}}
                        @if ($p['stock']['kind'] === 'out')
                            <span class="nad-chip nad-chip-ox absolute top-2 start-2">نفدت</span>
                        @elseif ($p['stock']['kind'] === 'low')
                            <span class="nad-chip nad-chip-wr absolute top-2 start-2">كمية محدودة</span>
                        @endif

                        @if (! $m->is_active)
                            <span class="nad-chip nad-chip-mut absolute top-2 end-2">مخفي عن المتجر</span>
                        @elseif ($m->old_price_usd)
                            <span class="nad-chip nad-chip-ox absolute top-2 end-2">خصم</span>
                        @endif

                        {{-- شرائح الألوان: أي ألوان لهذا المنتج — بنظرة واحدة بلا فتح النموذج --}}
                        @if ($p['colors']->isNotEmpty())
                            <div class="absolute bottom-2 start-2 flex items-center gap-1 rounded-full bg-black/45 px-2 py-1 backdrop-blur-sm">
                                @foreach ($p['colors']->take(5) as $c)
                                    <span class="inline-block h-4 w-4 rounded-full ring-1 ring-white/50"
                                          style="background:{{ $c['hex'] }}" title="{{ $c['label'] }}"></span>
                                @endforeach
                                @if ($p['colors']->count() > 5)
                                    <span class="text-[10px] font-bold text-white/80">+{{ $p['colors']->count() - 5 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="line-clamp-1 font-bold text-nad-ivory">{{ $m->name_ar }}</h3>
                            @if ($m->variants->count() > 1)
                                <span class="nad-chip nad-chip-br shrink-0 !text-[10px]">{{ $m->variants->count() }} متغيرات</span>
                            @else
                                <span class="nad-chip nad-chip-mut shrink-0 !text-[10px]">قطعة واحدة</span>
                            @endif
                        </div>

                        <code class="nad-ocode !text-[10px] !mt-1">{{ $p['skus'] ?: '—' }}</code>

                        <div class="mt-2.5 flex items-baseline justify-between">
                            <span class="nad-price !text-lg">{{ fmt_usd($m->price_usd) }}</span>
                            @if ($m->old_price_usd)
                                <del class="text-[11px] text-nad-dim">{{ fmt_usd($m->old_price_usd) }}</del>
                            @endif
                            <span class="text-[11px] {{ $p['stock']['kind'] === 'out' ? 'font-bold text-red-300' : ($p['stock']['kind'] === 'low' ? 'font-bold text-amber-300' : 'text-nad-mut') }}">
                                {{ $p['stock']['label'] }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <a href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $m]) }}"
                               class="nad-btn-brass !py-2 !text-xs">✏️ تعديل</a>
                            <button type="button" wire:click="toggleDetails({{ $m->id }})"
                                    class="nad-btn-ghost !py-2 !text-xs">
                                {{ $openProductId === $m->id ? '▲ إغلاق' : '▼ تفاصيل' }}
                            </button>
                            <a href="{{ route('product.show', $m->slug) }}" target="_blank" rel="noopener"
                               class="nad-btn-ghost !py-2 !text-xs">👁️ عرض</a>
                        </div>
                    </div>

                    {{-- ═══ التفاصيل — تُفتح داخل الكرت بلا مغادرة الصفحة ═══ --}}
                    @if ($openProductId === $m->id)
                        <div class="border-t border-white/10 bg-nad-surface2/60 p-4" wire:key="det-{{ $m->id }}">
                            <h4 class="mb-2 text-[11px] font-bold uppercase tracking-widest text-nad-brass">المتغيرات والمخزون</h4>

                            <div class="space-y-1.5">
                                @forelse ($m->variants as $v)
                                    <div class="flex items-center gap-2 text-[11.5px]">
                                        @if ($v->color_hex)
                                            <span class="inline-block h-3.5 w-3.5 shrink-0 rounded-full ring-1 ring-white/30"
                                                  style="background:{{ $v->color_hex }}"></span>
                                        @endif
                                        <span class="text-nad-ivory">{{ $v->label() ?: 'بلا لون أو مقاس' }}</span>
                                        <code class="text-[10px] text-nad-dim">{{ $v->sku ?: '—' }}</code>
                                        <span class="ms-auto font-bold {{ $v->quantity <= 0 ? 'text-red-300' : ($v->quantity <= $v->low_stock_threshold ? 'text-amber-300' : 'text-emerald-300') }}">
                                            {{ $v->quantity }} قطعة
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-[11.5px] text-nad-mut">لا متغيرات — المنتج متوفر بلا حدّ.</p>
                                @endforelse
                            </div>

                            <h4 class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-widest text-nad-brass">الأسعار</h4>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-[11.5px]">
                                <span class="text-nad-mut">سعر القطعة</span>
                                <b class="text-end text-nad-ivory">{{ fmt_usd($m->price_usd) }}</b>
                                @if ($m->wholesale_price_usd)
                                    <span class="text-nad-mut">سعر الجملة</span>
                                    <b class="text-end text-nad-ivory">{{ fmt_usd($m->wholesale_price_usd) }}</b>
                                @endif
                                @if ($m->cost_usd)
                                    <span class="text-nad-mut">التكلفة</span>
                                    <b class="text-end text-nad-ivory">{{ fmt_usd($m->cost_usd) }}</b>
                                @endif
                                <span class="text-nad-mut">بالليرة</span>
                                <b class="text-end text-nad-ivory">{{ fmt_syp($m->priceSyp()) }}</b>
                            </div>

                            <h4 class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-widest text-nad-brass">ما يراه العميل</h4>
                            <div class="flex flex-wrap gap-1.5 text-[10.5px]">
                                @foreach ([
                                    'السعر' => ! product_price_hidden($m),
                                    'سعر القطعة' => product_shows_unit_price($m),
                                    'سعر الجملة' => product_shows_wholesale($m),
                                    'الألوان' => product_shows_colors($m),
                                ] as $label => $shown)
                                    <span class="nad-chip {{ $shown ? 'nad-chip-ok' : 'nad-chip-mut' }} !text-[10.5px]">
                                        {{ $shown ? '✓' : '✕' }} {{ $label }}
                                    </span>
                                @endforeach
                            </div>

                            <h4 class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-widest text-nad-brass">الملف</h4>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-[11.5px]">
                                <span class="text-nad-mut">الكود الداخلي</span>
                                <b class="text-end text-nad-ivory" style="direction:ltr">{{ $m->internal_code ?: '—' }}</b>
                                <span class="text-nad-mut">النوع</span>
                                <b class="text-end text-nad-ivory">{{ $m->typeLabel() }}</b>
                                <span class="text-nad-mut">الصور والفيديو</span>
                                <b class="text-end text-nad-ivory">{{ $m->media->count() }}</b>
                                <span class="text-nad-mut">أُضيف في</span>
                                <b class="text-end text-nad-ivory">{{ $m->created_at?->format('Y/m/d') }}</b>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="col-span-full nad-empty">
                    <svg viewBox="0 0 24 24"><path d="M6.5 8h11l-1 12.5a1.5 1.5 0 0 1-1.5 1.4H9a1.5 1.5 0 0 1-1.5-1.4L6.5 8Z"/><path d="M9 10V6.8a3 3 0 0 1 6 0V10"/></svg>
                    <h3>لا منتجات في هذا القسم</h3>
                    <p>أضف أول منتج وسيظهر هنا مباشرة</p>
                    <a href="{{ \App\Filament\Resources\ProductResource::getUrl('create') }}" class="nad-btn-brass">＋ إضافة منتج</a>
                </div>
            @endforelse
        </div>

        <div class="pb-10 text-center">
            <button type="button" wire:click="closeCategory" class="nad-btn-ghost">رجوع للأقسام</button>
        </div>
    @endif
</div>
