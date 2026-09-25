<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * «الأقسام» — مدخل إضافة الأقسام وإدارتها.
 *
 * هذا هو المدخل الوحيد للمجال بعد حذف «أقسامي»: كانا مدخلين لنفس الشيء،
 * فبقي هذا لأنه المكان الذي تُضاف منه الأقسام فعلًا.
 *
 * والتنظيم هنا على ثلاث طبقات:
 *   • النموذج مقسَّم إلى أقسام منطقية (بيانات · صورة وترتيب · حالة)
 *   • الجدول فيه تبويبات وفلاتر، وترتيب بالسحب لا بالأرقام
 *   • كل صف يُظهر ما يهم: عدد المنتجات ومفعولها، وهل القسم فارغ
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'الأقسام';
    }

    public static function getModelLabel(): string
    {
        return 'قسم';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الأقسام';
    }

    public static function getNavigationBadge(): ?string
    {
        // الأقسام الفارغة تحتاج تصرّفًا: إمّا تُعبّأ أو تُخفى
        $empty = Category::whereDoesntHave('products')->count();

        return $empty > 0 ? (string) $empty : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ═══════════════════════════ النموذج ═══════════════════════════

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('البيانات الأساسية')
                ->description('الاسم العربي هو ما يراه العميل. والاسم الإنجليزي يُستخدم في رابط القسم.')
                ->schema([
                    Forms\Components\TextInput::make('name_ar')
                        ->label('الاسم بالعربية')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),

                    Forms\Components\TextInput::make('name_en')
                        ->label('الاسم بالإنجليزية (اختياري)')
                        ->maxLength(255)
                        ->helperText('اتركه فارغًا ليُشتقّ رابط القسم من الاسم العربي.'),

                    Forms\Components\Select::make('parent_id')
                        ->label('القسم الأب (اختياري)')
                        ->options(fn (?Category $record) => Category::query()
                            ->whereNull('parent_id')
                            // لا يكون القسم أبًا لنفسه
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->orderBy('sort_order')
                            ->pluck('name_ar', 'id')
                            ->all())
                        ->searchable()
                        ->placeholder('بلا قسم أب — قسم رئيسي')
                        ->helperText('اختر قسمًا أبًا ليصير هذا قسمًا فرعيًا تحته.'),

                    Forms\Components\Placeholder::make('duplicate_warning')
                        ->label('')
                        ->content(fn (Forms\Get $get, ?Category $record) => self::duplicateNameWarning($get, $record) ?? '')
                        ->visible(fn (Forms\Get $get, ?Category $record) => self::duplicateNameWarning($get, $record) !== null)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('الصورة والترتيب')
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label('صورة القسم')
                        ->disk('public')
                        ->directory('categories')
                        ->image()
                        ->imageEditor()
                        ->maxSize(4096)
                        ->helperText('تظهر في كرت القسم بالمتجر. الأفضل صورة أفقية واضحة.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->helperText('الأصغر يظهر أولًا. ويمكنك أيضًا سحب الصفوف في الجدول لإعادة الترتيب.'),
                ])->columns(2)->collapsible(),

            Forms\Components\Section::make('الحالة')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعّل — يظهر في المتجر')
                        ->default(true)
                        ->helperText('إن أُطفئ يختفي القسم من المتجر ولا يظهر للعميل، ويبقى هنا.'),

                    Forms\Components\Placeholder::make('products_hint')
                        ->label('')
                        ->content(fn (?Category $record) => $record
                            ? 'هذا القسم فيه '.$record->products()->count().' منتج.'
                            : 'يُضاف القسم فارغًا، ثم تُضاف المنتجات إليه.')
                        ->columnSpanFull(),
                ])->columns(2)->collapsible(),
        ]);
    }

    /**
     * تنبيه الاسم المتكرر — **تنبيه لا منع**، كما في المنتجات.
     * لا قيد فريد على الاسم، لكن التكرار يُربك العميل في القائمة.
     */
    private static function duplicateNameWarning(Forms\Get $get, ?Category $record): ?string
    {
        $name = trim((string) $get('name_ar'));

        if ($name === '') {
            return null;
        }

        $exists = Category::query()
            ->where('name_ar', $name)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();

        return $exists ? 'يوجد قسم آخر بالاسم نفسه — تأكد أنه ليس تكرارًا.' : null;
    }

    // ═══════════════════════════ الجدول ═══════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            // السحب لإعادة الترتيب: أوضح من كتابة أرقام وتخمين أيّها أول
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            // العدّادات كلها باستعلام واحد — لا استعلام لكل صف
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'products',
                'products as active_products_count' => fn ($q) => $q->where('is_active', true),
                'children',
            ]))
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->square()
                    ->height(44)
                    ->defaultImageUrl(fn (Category $record) => $record->imageUrl()),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label('القسم')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Category $record) => $record->name_en ?: $record->slug),

                Tables\Columns\TextColumn::make('parent.name_ar')
                    ->label('تحت')
                    ->badge()
                    ->placeholder('قسم رئيسي')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('المنتجات')
                    ->badge()
                    ->alignCenter()
                    ->color(fn ($state) => $state === 0 ? 'gray' : 'primary')
                    ->description(fn (Category $record) => $record->products_count === 0
                        ? 'قسم فارغ'
                        : $record->active_products_count.' مفعّل من '.$record->products_count),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('أقسام فرعية')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                // مفتاح لا أيقونة: إظهار/إخفاء القسم بنقرة واحدة من الجدول
                // أوفر من فتح نموذج التعديل لكل تبديل
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّل')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مفعّل')
                    ->falseLabel('مخفي'),

                Tables\Filters\TernaryFilter::make('parent_id')
                    ->label('النوع')
                    ->placeholder('الكل')
                    ->trueLabel('أقسام فرعية فقط')
                    ->falseLabel('أقسام رئيسية فقط')
                    ->nullable(),

                Tables\Filters\Filter::make('empty')
                    ->label('فارغة فقط')
                    ->query(fn ($query) => $query->whereDoesntHave('products')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),

                Tables\Actions\Action::make('products')
                    ->label('منتجات القسم')
                    ->icon('heroicon-m-cube')
                    ->color('gray')
                    ->url(fn (Category $record) => \App\Filament\Resources\ProductResource::getUrl('index', ['category' => $record->getKey()])),

                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    // الحذف يُفقد الربط بالمنتجات: تُترك بلا قسم لا تُحذف
                    ->modalDescription(fn (Category $record) => $record->products_count > 0
                        ? "هذا القسم فيه {$record->products_count} منتج. الحذف لن يحذفها، لكنها ستبقى بلا قسم — والأفضل نقلها أولًا أو إخفاء القسم بدل حذفه."
                        : 'لا منتجات في هذا القسم.'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('لا أقسام بعد')
            ->emptyStateDescription('الأقسام هي أول ما يراه العميل في المتجر. أضف أول قسم ثم أضف إليه المنتجات.')
            ->emptyStateIcon('heroicon-o-square-3-stack-3d');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
