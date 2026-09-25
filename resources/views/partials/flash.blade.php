{{-- إشعارات منبثقة: من جلسة Laravel أو حدث Livewire --}}
<div
    x-data="{ show: false, message: '', type: 'success', t: null,
              fire(message, type) { this.message = message; this.type = type; this.show = true; clearTimeout(this.t); this.t = setTimeout(() => this.show = false, type === 'error' ? 3500 : 3000) } }"
    @flash.window="fire($event.detail.message, $event.detail.type ?? 'success')"
    @if(session('success')) x-init="fire(@js(session('success')), 'success')" @elseif(session('error')) x-init="fire(@js(session('error')), 'error')" @endif
    x-show="show" x-cloak
    role="status" aria-live="polite"
    class="pointer-events-none fixed inset-x-0 top-4 z-[80] flex justify-center px-4"
>
    <div class="pointer-events-auto flex items-center gap-2 rounded-lg bg-nad-surface2 px-5 py-3 text-sm font-semibold text-white shadow-xl">
        {{-- الأيقونة تتبع نوع الإشعار: كان الخطأ يُعرض بعلامة صحّ --}}
        <x-shop-icon name="check" class="h-4 w-4 text-nad-brass" x-show="type !== 'error'" x-cloak />
        <x-shop-icon name="x" class="h-4 w-4 text-nad-brass" x-show="type === 'error'" x-cloak />
        <span x-text="message"></span>
    </div>
</div>
