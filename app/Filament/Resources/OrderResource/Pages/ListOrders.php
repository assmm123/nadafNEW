<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Pages\OrderArchive;
use App\Filament\Pages\Settings;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * قائمة الطلبات — تبويبات بالحالة مع عدّادات.
 *
 * الفائدة: الملغاة لا تزحم النشطة، والمالك يبدأ صباحه من «جديدة».
 * والتعديل يقع على استعلام القائمة نفسه (لا فلتر إضافي يحتاج نقرات).
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $count = fn (array $statuses = []) => Order::query()
            ->whereNull('archived_at')
            ->when($statuses !== [], fn ($q) => $q->whereIn('status', $statuses))
            ->count();

        // ⚠️ اسم الوسيط **يجب** أن يكون `$query` حرفيًا.
        //
        // Tab::modifyQuery يمرّر الوسيط باسم 'query' — وإن اختلف الاسم لا يجده
        // Filament، فيحلّه بـ**النوع** عبر الحاوية: app(Builder::class) يُنشئ
        // Eloquent Builder جديدًا **بلا موديل**. فيصير موديل الجدول null، ويسقط
        // الاستعلام في HasRecords::getModel() بالخطأ:
        //     Cannot use "::class" on null
        // وهو خطأ يُعطّل صفحة الطلبات كاملة. كان الاسم `$q` هنا فوقع الخطأ فعلًا.
        $tab = fn (string $label, array $statuses = []) => Tab::make($label)
            ->badge($count($statuses))
            ->modifyQueryUsing(fn (Builder $query) => $statuses === []
                ? $query
                : $query->whereIn('status', $statuses));

        return [
            'all' => $tab('الكل'),
            'pending' => $tab('جديدة', ['pending']),
            'preparing' => $tab('قيد التحضير', ['preparing']),
            'shipped' => $tab('مشحون', ['shipped']),
            'delivered' => $tab('مسلَّم', ['delivered']),
            'cancelled' => $tab('ملغى', ['cancelled']),
        ];
    }

    /**
     * شريط «مرحلة قبض الدفع» أعلى القائمة — الإعداد في مكانه الطبيعي
     * (قسم الطلبات) لا مدفونًا في صفحة الإعدادات.
     */
    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.orders.collect-stage', [
            'stage' => Order::collectStage(),
            'label' => match (Order::collectStage()) {
                'on_confirm' => 'عند التأكيد',
                'on_ship' => 'عند الشحن',
                'optional' => 'غير مشروط',
                default => 'عند التسليم',
            },
            'settingsUrl' => Settings::getUrl(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // الزر الأساسي — مطابق لنموذج «قسم الطلبات»: أبرز زر في شريط الأدوات
            OrderResource::manualOrderAction(),

            Action::make('export')
                ->label('تصدير Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.reports.export', ['period' => 'this_month', 'type' => 'xlsx'])),

            Action::make('archive')
                ->label('الأرشيف')
                ->icon('heroicon-m-archive-box')
                ->color('gray')
                ->url(OrderArchive::getUrl()),
        ];
    }
}
