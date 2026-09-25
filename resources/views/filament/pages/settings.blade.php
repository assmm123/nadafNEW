<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="fi-btn rounded-lg bg-primary-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600">
                حفظ الإعدادات
            </button>

            <button type="button" wire:click="testTelegram"
                    class="fi-btn fi-btn-color-gray rounded-lg px-5 py-2.5 text-sm font-bold">
                إرسال رسالة اختبار تيليجرام
            </button>
        </div>
    </form>
</x-filament-panels::page>
