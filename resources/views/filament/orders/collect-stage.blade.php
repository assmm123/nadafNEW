{{--
    رأس صفحة قسم الطلبات = الرأس القياسي + شريط «مرحلة قبض الدفع».

    لماذا نستدعي الرأس القياسي صراحةً هنا بدل الاكتفاء بالشريط؟
    لأن قالب الصفحة في Filament يبني الرأس بشرط إما/أو:

        إذا أعاد getHeader() عرضًا  → يُعرض وحده
        وإلا إذا وُجد عنوان         → يُعرض الرأس القياسي ومعه أزرار getHeaderActions()

    أي أن أي getHeader() مخصّص **يحل محل** الرأس القياسي — ومعها تختفي أزرار
    شريط الأدوات كليًا: «+ طلب يدوي» · «تصدير Excel» · «الأرشيف».
    لذلك نستدعي الرأس القياسي هنا أولًا، ثم نضيف الشريط تحته.

    تنبيه: لا تُدرِج مُغلِق تعليق Blade داخل تعليق Blade — أول مُغلِق يُنهي
    التعليق ويحوّل بقيّته إلى نص ظاهر على الصفحة.
--}}
<x-filament-panels::header
    :actions="$this->getCachedHeaderActions()"
    :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
    :heading="$this->getHeading()"
    :subheading="$this->getSubheading()"
/>

{{-- شريط مرحلة قبض الدفع — الإعداد في مكانه الطبيعي داخل قسم الطلبات --}}
<div class="flex flex-wrap items-center gap-3 rounded-xl border bg-[#171D26] px-4 py-3"
     style="border-color:rgba(210,162,78,.28)">
    <span class="text-[11px] font-bold uppercase tracking-[.14em] text-[#D2A24E]">مرحلة قبض الدفع</span>
    <span class="text-[13px] font-bold text-[#EAC97F]">{{ $label }}</span>
    <span class="text-[11.5px] text-[#8A93A3]">
        @if ($stage === 'optional')
            يُسمح بالتسليم بلا قبض — للدفع عند الاستلام
        @else
            لا يُسلَّم طلب قبل قبض ماله (الختم الذهبي)
        @endif
    </span>
    <a href="{{ $settingsUrl }}"
       class="ms-auto rounded-full border border-white/15 px-3.5 py-1.5 text-[11.5px] font-bold text-[#A79F8F] transition hover:border-[#D2A24E] hover:text-[#EAC97F]">
        تغيير الإعداد
    </a>
</div>
