<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationGroup = 'المخزون والجرد';

    public static function getNavigationLabel(): string { return 'سجل حركات المخزون'; }
    public static function getModelLabel(): string { return 'حركة مخزون'; }
    public static function getPluralModelLabel(): string { return 'حركات المخزون'; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('Y/m/d — H:i')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع الحركة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => StockMovement::typeLabel($state))
                    ->icon(fn ($state) => match ($state) {
                        'sale' => 'heroicon-m-shopping-cart',
                        'return' => 'heroicon-m-arrow-uturn-left',
                        'receive' => 'heroicon-m-inbox-arrow-down',
                        'adjust' => 'heroicon-m-adjustments-horizontal',
                        'damage' => 'heroicon-m-exclamation-triangle',
                        default => null,
                    })
                    ->color(fn ($state) => StockMovement::TYPES[$state]['color'] ?? 'gray'),
                Tables\Columns\TextColumn::make('variant.product.name_ar')
                    ->label('المنتج')
                    ->description(fn ($record) => $record->variant->label() ?: null)
                    ->searchable(),
                Tables\Columns\TextColumn::make('variant.sku')
                    ->label('رمز القطعة SKU')
                    ->copyable()
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('التغير')
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('balance_before')
                    ->label('الرصيد قبل')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('الرصيد بعد')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('order.order_code')
                    ->label('الطلب المرتبط')
                    ->url(fn ($record) => $record->order_id
                        ? \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null)
                    ->color('primary')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('purchaseInvoice.code')
                    ->label('فاتورة الشراء')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('بواسطة')
                    ->placeholder('النظام')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('note')
                    ->label('السبب / ملاحظة')
                    ->limit(45)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع الحركة')
                    ->options(collect(StockMovement::TYPES)->mapWithKeys(fn ($t, $k) => [$k => $t['ar']])),
                Tables\Filters\SelectFilter::make('variant')
                    ->label('الصنف (SKU)')
                    ->relationship('variant', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->sku ? $record->sku.' — ' : '').$record->product->name_ar.($record->label() ? " ({$record->label()})" : '')),
                Tables\Filters\SelectFilter::make('product_category')
                    ->label('القسم')
                    ->relationship('variant.product.category', 'name_ar'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('من تاريخ'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
