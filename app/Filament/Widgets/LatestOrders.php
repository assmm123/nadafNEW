<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * الطبقة الرابعة: أحدث الطلبات — جدول مضغوط.
 *
 * عمودان منفصلان مقصودان: **الحالة** (أين وصل الطلب) و**الدفع** (أقُبض المال؟).
 * فطلب «قيد التحضير» قد يكون «غير مقبوض» — ودمجهما في عمود واحد يضيّع المعلومة
 * التي تُسأل عنها أكثر من غيرها.
 */
class LatestOrders extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('orders.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest()->limit(6))
            ->heading('أحدث الطلبات')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('order_code')
                    ->label('الكود')
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('العميل')
                    ->limit(20),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Order::statusLabel($state))
                    // shipped لم تبقَ رمادية: الرمادي يعني «معطّل» والشحن حالة نشطة
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'preparing' => 'primary',
                        'shipped' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_confirmed_at')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'مقبوض' : 'غير مقبوض')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('total_usd')
                    ->label('الإجمالي')
                    ->money('USD')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('m/d H:i')
                    ->toggleable(),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]));
    }
}
