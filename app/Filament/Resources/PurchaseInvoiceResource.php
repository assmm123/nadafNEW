<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Models\PurchaseInvoice;
use App\Models\ProductVariant;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationGroup = 'المخزون والجرد';

    public static function getNavigationLabel(): string { return 'فواتير الشراء'; }
    public static function getModelLabel(): string { return 'فاتورة شراء'; }
    public static function getPluralModelLabel(): string { return 'فواتير الشراء'; }
    public static function getNavigationBadge(): ?string
    {
        return (string) PurchaseInvoice::where('status', 'draft')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الفاتورة')->schema([
                Forms\Components\Select::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('اسم المورد')->required(),
                        Forms\Components\TextInput::make('phone')->label('الهاتف')->tel(),
                        Forms\Components\Textarea::make('address')->label('العنوان')->rows(2),
                    ]),
                Forms\Components\DatePicker::make('created_at')->label('تاريخ الفاتورة')->default(now()),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2)->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('الأصناف المستلمة')
                ->description('عند تأكيد الفاتورة تُضاف الكميات للمخزون تلقائيًا وتُسجل كحركات استلام')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('variant_id')
                                ->label('الصنف (منتج/لون/مقاس)')
                                ->options(fn () => ProductVariant::with('product')
                                    ->get()
                                    ->mapWithKeys(fn ($v) => [$v->id => $v->product->name_ar.($v->label() ? " ({$v->label()})" : '')]))
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('quantity')->label('الكمية')->numeric()->minValue(1)->required(),
                            Forms\Components\TextInput::make('unit_cost')->label('تكلفة الوحدة $')->numeric()->minValue(0),
                        ])
                        ->columns(3)
                        ->defaultItems(1)
                        ->reorderable(false),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** إجراء تأكيد الفاتورة: يضيف الكميات للمخزون ويوثقها */
    public static function confirmAction(bool $forTable = true): \Filament\Actions\Action|\Filament\Tables\Actions\Action
    {
        $class = $forTable ? \Filament\Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return $class::make('confirmPurchase')
            ->label('تأكيد واستلام')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->visible(fn (PurchaseInvoice $record) => $record->canBeConfirmed())
            ->requiresConfirmation()
            ->modalHeading('تأكيد فاتورة الشراء')
            ->modalDescription('ستُضاف كميات كل صنف للمخزون فورًا وتُسجل كحركات استلام في سجل الجرد، ولن يمكن تعديل الفاتورة بعد التأكيد.')
            ->modalSubmitActionLabel('تأكيد وإضافة للمخزون')
            ->action(function (PurchaseInvoice $record) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                    $total = 0;
                    foreach ($record->items()->with('variant')->get() as $item) {
                        StockService::record(
                            $item->variant_id,
                            'receive',
                            $item->quantity,
                            null,
                            $record->id,
                            'استلام شراء — فاتورة '.$record->code,
                        );

                        // تحديث تكلفة المنتج إن أُدخلت
                        if ($item->unit_cost !== null && $item->variant->product) {
                            $item->variant->product->update(['cost_usd' => $item->unit_cost]);
                        }
                        $total += ($item->unit_cost ?? 0) * $item->quantity;
                    }

                    $record->update([
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                        'total_cost' => round($total, 2),
                    ]);
                });

                // إشعار استلام لقناة الجرد
                try {
                    \App\Services\TelegramService::sendInventory(
                        "📦 استلام بضاعة — فاتورة {$record->code}\n".
                        'الأصناف: '.$record->items()->count()."\n".
                        'الإجمالي: $'.number_format((float) $record->total_cost, 2)
                    );
                } catch (\Throwable $e) {
                    report($e);
                }

                Notification::make()
                    ->title('تم تأكيد فاتورة '.$record->code.' وإضافة الكميات للمخزون')
                    ->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('الكود')->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->placeholder('—'),
                Tables\Columns\TextColumn::make('items_count')->label('الأصناف')->counts('items'),
                Tables\Columns\TextColumn::make('total_cost')->label('الإجمالي $')->money('USD'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'confirmed' ? 'مؤكدة' : 'مسودة')
                    ->color(fn ($state) => $state === 'confirmed' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(['draft' => 'مسودة', 'confirmed' => 'مؤكدة']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (PurchaseInvoice $record) => $record->status === 'draft'),
                self::confirmAction(),
                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * الفاتورة المؤكدة تُقفل فعليًا (لا إخفاء زر فقط).
     *
     * السبب: إضافة/تعديل بنود فاتورة مؤكدة تُغيّر الكميات والتكلفة دون كتابة
     * أي حركة في stock_movements ودون إعادة حساب total_cost (لا يحدث ذلك إلا
     * في confirmAction) — فينفصل سجل المخزون عن الواقع صامتًا.
     */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // الشرطان معًا: مسودة (قاعدة العمل) + صلاحية السياسة (الأمان)
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->status === 'draft' && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
            'view' => Pages\ViewPurchaseInvoice::route('/{record}'),
        ];
    }
}
