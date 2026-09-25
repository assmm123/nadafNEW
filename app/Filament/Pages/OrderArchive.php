<?php

namespace App\Filament\Pages;

use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderArchive extends Page implements HasTable
{
    /** تحتاج صلاحية عرض الطلبات — تُخفى من القائمة وتمنع فتحها بالرابط */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('orders.view');
    }

    use InteractsWithTable;

    /**
     * مُخفى من القائمة لا مُلغى: النموذج المعتمد يعرض «الأرشيف» كزرّ في شريط
     * أدوات قسم الطلبات، فلا داعي لمدخل ثانٍ. والصفحة تبقى تعمل من الزر.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.order-archive';

    protected static ?string $navigationGroup = null;

    public ?string $from = null;

    public ?string $until = null;

    public function mount(): void
    {
        $this->from = now()->subDays(30)->format('Y-m-d');
        $this->until = now()->format('Y-m-d');
    }

    public static function getNavigationLabel(): string
    {
        return 'أرشيف الطلبات';
    }

    public function getTitle(): string
    {
        return 'أرشيف الطلبات';
    }

    /** فلترة زمنية يدوية فوق استعلام الجدول */
    protected function applyQueryFilters(Builder $query): Builder
    {
        return $query
            ->when($this->from, fn ($q, $d) => $q->whereDate('archived_at', '>=', $d))
            ->when($this->until, fn ($q, $d) => $q->whereDate('archived_at', '<=', $d));
    }

    protected function getTableQuery(): ?Builder
    {
        return $this->applyQueryFilters(
            Order::query()->whereNotNull('archived_at')
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                TextColumn::make('order_code')->label('الكود')->searchable()->weight('bold')->copyable(),
                TextColumn::make('user.name')->label('العميل')->searchable(),
                TextColumn::make('status')
                    ->label('الحالة النهائية')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Order::statusLabel($state))
                    ->color(fn ($state) => match ($state) {
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_usd')->label('الإجمالي')->money('USD'),
                TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime('Y/m/d')->sortable(),
                TextColumn::make('archived_at')->label('أُرشف في')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([])
            ->actions([
                \Filament\Tables\Actions\Action::make('restore')
                    ->label('إعادة للنشطة')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('warning')
                    ->action(function (Order $record) {
                        $record->update(['archived_at' => null]);
                        Notification::make()->title('أُعيد الطلب '.$record->order_code.' للقائمة النشطة')->success()->send();
                    }),
                \Filament\Tables\Actions\Action::make('invoice')
                    ->label('الفاتورة')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (Order $record) => route('admin.invoice', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->defaultSort('archived_at', 'desc');
    }

    /** إعادة تعيين الجدول بعد الفلترة الزمنية اليدوية */
    public function updatedFrom(): void
    {
        $this->resetTable();
    }

    public function updatedUntil(): void
    {
        $this->resetTable();
    }
}
