<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string { return 'كوبونات الخصم'; }
    public static function getModelLabel(): string { return 'كوبون'; }
    public static function getPluralModelLabel(): string { return 'كوبونات الخصم'; }

    public static function getNavigationBadge(): ?string
    {
        $active = Coupon::where('is_active', true)->count();

        return $active ? (string) $active : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('كود الكوبون')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state)))
                ->helperText('يُحوَّل تلقائيًا إلى أحرف كبيرة — العميل يكتبه في صفحة الدفع'),

            Forms\Components\Select::make('discount_type')
                ->label('نوع الخصم')
                ->options([
                    'percentage' => 'نسبة مئوية %',
                    'fixed' => 'مبلغ ثابت بالدولار',
                ])
                ->default('percentage')
                ->required()
                ->live(),

            Forms\Components\TextInput::make('discount_value')
                ->label(fn (Forms\Get $get) => $get('discount_type') === 'fixed'
                    ? 'قيمة الخصم ($)'
                    : 'نسبة الخصم (%)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->rules([
                    fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ($get('discount_type') === 'percentage' && (float) $value > 100) {
                            $fail('نسبة الخصم لا يمكن أن تتجاوز 100%.');
                        }
                    },
                ])
                ->helperText('النسبة تُحسب على المجموع الفرعي قبل الشحن'),

            Forms\Components\TextInput::make('min_order_usd')
                ->label('أدنى قيمة للطلب ($)')
                ->numeric()
                ->minValue(0)
                ->nullable()
                ->helperText('اتركه فارغًا ليعمل الكوبون على أي طلب'),

            Forms\Components\DatePicker::make('expiry_date')
                ->label('تاريخ الانتهاء')
                ->nullable()
                ->native(false)
                ->helperText('اتركه فارغًا ليبقى الكوبون صالحًا دائمًا'),

            Forms\Components\TextInput::make('usage_limit')
                ->label('حد الاستخدام')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->nullable()
                ->helperText('أقصى عدد مرات استخدام — فارغ = بلا حد'),

            Forms\Components\TextInput::make('used_count')
                ->label('استُخدم حتى الآن')
                ->numeric()
                ->default(0)
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit')
                ->helperText('يُحدَّث تلقائيًا مع كل طلب'),

            Forms\Components\Toggle::make('is_active')
                ->label('مفعّل')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('الكود')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('discount_value')
                    ->label('الخصم')
                    ->formatStateUsing(fn ($state, Coupon $record) => $record->discount_type === 'percentage'
                        ? rtrim(rtrim(number_format((float) $state, 2), '0'), '.').'%'
                        : fmt_usd((float) $state)),

                Tables\Columns\TextColumn::make('min_order_usd')
                    ->label('أدنى طلب')
                    ->formatStateUsing(fn ($state) => $state ? fmt_usd((float) $state) : '—'),

                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('ينتهي')
                    ->date('Y/m/d')
                    ->placeholder('دائم')
                    ->color(fn (?Coupon $record) => $record?->expiry_date?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('usage')
                    ->label('الاستخدام')
                    ->state(fn (Coupon $record) => $record->used_count.' / '.($record->usage_limit ?? '∞')),

                Tables\Columns\ToggleColumn::make('is_active')->label('مفعّل'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُنشئ')
                    ->date('Y/m/d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCoupons::route('/')];
    }
}
