<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <span class="text-sm font-bold text-gray-500">من تاريخ الأرشفة</span>
            <input wire:model.live="from" type="date"
                   class="mt-1 block w-44 rounded-lg border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
        </div>
        <div>
            <span class="text-sm font-bold text-gray-500">إلى تاريخ الأرشفة</span>
            <input wire:model.live="until" type="date"
                   class="mt-1 block w-44 rounded-lg border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
        </div>
        <x-filament::button color="gray" wire:click="$refresh" icon="heroicon-m-arrow-path">
            تحديث
        </x-filament::button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
