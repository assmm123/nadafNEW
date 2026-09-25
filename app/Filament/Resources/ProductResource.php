<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 1;

    /** مخفي من القائمة الجانبية — المدخل الرسمي «منتجاتي» (البوابة بالكروت)؛ يبقى متاحًا كروابط خلفية */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'المنتجات';
    }

    public static function getModelLabel(): string
    {
        return 'منتج';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المنتجات';
    }

    /**
     * تنبيه الاسم المتكرر — **تنبيه لا منع**.
     *
     * لا يوجد قيد فريد على `name_ar` لأن التكرار قد يكون مقصودًا (نسختان
     * بخامة أو سعر مختلف). لكن التكرار غير المقصود حدث فعلًا في هذه البيانات
     * (منتجان باسم «بديلة بابيون سوداء»)، فالتنبيه يكفي لكشفه بلا منع الحفظ.
     */
    private static function duplicateNameWarning(Forms\Get $get, ?Product $record): ?string
    {
        $name = trim((string) $get('name_ar'));

        if ($name === '') {
            return null;
        }

        $exists = Product::query()
            ->where('name_ar', $name)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();

        return $exists
            ? 'يوجد منتج آخر بالاسم نفسه. تأكد أنه ليس تكرارًا غير مقصود — الحفظ مسموح رغم ذلك.'
            : null;
    }

    public static function form(Form $form): Form
    {        return $form->schema([
            // ═══ نوع المنتج — أول سؤال، لأنه يحدّد بقية النموذج ═══
            Forms\Components\Section::make('نوع المنتج')
                ->description('«قطعة واحدة» = لون ومقاس واحد وتُدخل الكمية فقط. «عدة ألوان أو مقاسات» = القطعة نفسها بعدة نسخ، ولكل نسخة مخزونها. الاختيار يحدّد بقية النموذج فابدأ به.')
                ->schema([
                    Forms\Components\ToggleButtons::make('product_type')
                        ->label('')
                        ->options(Product::TYPES)
                        ->default(Product::TYPE_SINGLE)
                        ->inline()
                        ->live()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('البيانات الأساسية')->schema([
                Forms\Components\Placeholder::make('internal_code_info')
                    ->label('الكود الداخلي')
                    ->content(fn (?Product $record) => $record?->internal_code
                        ? 'الكود الحالي: '.$record->internal_code.' — يظهر بغرف الجرد والفواتير'
                        : 'سيُولَّد تلقائيًا عند الحفظ — يظهر بغرف الجرد والفواتير'),
                Forms\Components\TextInput::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->required()
                    ->maxLength(255)
                    // live(onBlur): الفحص يقع عند مغادرة الحقل لا مع كل حرف
                    ->live(onBlur: true),

                Forms\Components\Placeholder::make('duplicate_name_warning')
                    ->label('')
                    ->content(fn (Forms\Get $get, ?Product $record) => self::duplicateNameWarning($get, $record) ?? '')
                    ->visible(fn (Forms\Get $get, ?Product $record) => self::duplicateNameWarning($get, $record) !== null)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name_en')
                    ->label('الاسم بالإنجليزية (اختياري)')
                    ->maxLength(255)
                    ->helperText('اختياري — إن تُرك فارغًا يُستخدم الاسم العربي في الروابط والبحث'),
                Forms\Components\Select::make('category_id')
                    ->label('القسم')
                    ->relationship('category', 'name_ar')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('description_ar')
                    ->label('الوصف بالعربية')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description_en')
                    ->label('الوصف بالإنجليزية')
                    ->rows(4)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('الأسعار (الدولار أساس التسعير)')->schema([
                Forms\Components\TextInput::make('price_usd')
                    ->label('السعر $')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('$'),
                Forms\Components\TextInput::make('cost_usd')
                    ->label('تكلفة الشراء $ (لحساب الأرباح)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->helperText('اتركه فارغًا إن لم ترغب بحساب أرباح هذا المنتج'),
                Forms\Components\TextInput::make('old_price_usd')
                    ->label('السعر قبل الخصم $ (اختياري)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),
                Forms\Components\TextInput::make('wholesale_price_usd')
                    ->label('سعر الجملة $ (اختياري)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->helperText('يطبق تلقائيًا عند بلوغ الحد الأدنى للجملة'),

                // مفتاح إخفاء سعر الجملة انتقل إلى قسم «ما يراه العميل»
                // لأنه خاصية ظهور لا خاصية تسعير — ووجوده هنا كان يُكرّره.

                Forms\Components\TextInput::make('manual_price_syp')
                    ->label('سعر الليرة يدويًا (اختياري)')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('اتركه فارغًا ليُحسب تلقائيًا من سعر الصرف'),
            ])->columns(2),

            // ═══ ما يراه العميل — مفاتيح لكل منتج على حدة ═══
            Forms\Components\Section::make('ما يراه العميل')
                ->description('هذه المفاتيح تخصّ هذا المنتج وحده ولا تُلغي الإعدادات العامة للمتجر — والعام يبقى الحاكم الأعلى.')
                ->icon('heroicon-o-eye')
                ->extraAttributes(['class' => 'nad-visibility'])
                ->schema([
                    Forms\Components\Toggle::make('hide_price')
                        ->label('إخفاء السعر بالكامل')
                        ->helperText('لا يظهر أي سعر لهذا المنتج — ويُستبدل بزر تواصل إن كان مفعّلًا')
                        ->live(),

                    Forms\Components\Toggle::make('hide_unit_price')
                        ->label('إخفاء سعر القطعة')
                        ->helperText('يُخفي السعر العادي ويُبقي سعر الجملة')
                        ->visible(fn (Forms\Get $get) => ! $get('hide_price')),

                    Forms\Components\Toggle::make('hide_wholesale')
                        ->label('إخفاء سعر الجملة')
                        ->helperText('يُخفي سعر الجملة ويُبقي السعر العادي')
                        ->visible(fn (Forms\Get $get) => ! $get('hide_price')),

                    Forms\Components\Toggle::make('hide_colors')
                        ->label('إخفاء الألوان والمقاسات')
                        ->helperText('يُعرض المنتج بلا اختيار لون أو مقاس — يفيد إن كان العرض على المقاس فقط')
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('vis_note')
                        ->label('')
                        ->content('إن أُخفي السعران معًا فلا يبقى سعر ظاهر — وهذا أثر «إخفاء السعر بالكامل» نفسه.')
                        ->visible(fn (Forms\Get $get) => $get('hide_unit_price') && $get('hide_wholesale'))
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('الخيارات')->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label('مفعل')
                    ->default(true),
                Forms\Components\Toggle::make('is_featured')
                    ->label('منتج مميز (يظهر بالرئيسية)'),
                Forms\Components\Toggle::make('allow_inquiry')
                    ->label('تفعيل زر الاستفسار')
                    ->default(true),
            ])->columns(3),

            Forms\Components\Section::make(fn (Forms\Get $get) => $get('product_type') === Product::TYPE_SINGLE
                    ? 'الكمية في المخزون'
                    : 'الألوان والمقاسات والمخزون')
                ->description(fn (Forms\Get $get) => $get('product_type') === Product::TYPE_SINGLE
                    ? 'أدخل الكمية المتوفرة فقط — لا حاجة لاسم لون ولا مقاس.'
                    : 'أضف صفًا لكل لون أو مقاس. لكل صف مخزونه وحدّ تنبيهه ورمزه الخاص.')
                ->schema([
                    Forms\Components\Repeater::make('variants')
                        ->relationship()
                        ->label('')
                        ->addActionLabel('＋ أضف لونًا أو مقاسًا')
                        ->schema([
                            // حقول اللون والمقاس تظهر في النوعين: المنتج الفردي
                            // أيضًا له لون ومقاس يُسجَّلهما المالك ولو لم يخترهما
                            // العميل. والفرق بين النوعين يبقى في عدد الصفوف
                            // وزرّي الإضافة والحذف، لا في الحقول.
                            Forms\Components\TextInput::make('color')
                                ->label('اسم اللون')
                                ->maxLength(50)
                                ->placeholder('مثال: كحلي'),

                            Forms\Components\ColorPicker::make('color_hex')
                                ->label('درجة اللون')
                                ->default('#888888')
                                ->helperText('انقر المربع لاختيار الدرجة كما تظهر للعميل'),

                            Forms\Components\TextInput::make('size')
                                ->label('المقاس')
                                ->maxLength(50)
                                ->placeholder('مثال: طويل'),

                            Forms\Components\TextInput::make('sku')->label('SKU')->maxLength(50),

                            Forms\Components\TextInput::make('quantity')
                                ->label('الكمية')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required(),

                            Forms\Components\TextInput::make('low_stock_threshold')
                                ->label('حد التنبيه')
                                ->numeric()
                                ->minValue(0)
                                ->default(3),
                        ])
                        ->columns(6)
                        ->defaultItems(1)
                        ->minItems(1)
                        // قطعة واحدة = صف واحد لا يُضاف ولا يُحذف
                        ->maxItems(fn (Forms\Get $get) => $get('product_type') === Product::TYPE_SINGLE ? 1 : null)
                        ->addable(fn (Forms\Get $get) => $get('product_type') !== Product::TYPE_SINGLE)
                        ->deletable(fn (Forms\Get $get) => $get('product_type') !== Product::TYPE_SINGLE)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state) => trim((string) ($state['color'] ?? '').' '.(string) ($state['size'] ?? '')) ?: 'الكمية'),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Forms\Components\Section::make('الوسائط (صور + فيديو ≤ 10 ثوانٍ)')
                ->schema([
                    Forms\Components\Repeater::make('media')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\FileUpload::make('file_path')
                                ->label('الملف')
                                ->disk('public')
                                ->directory('products')
                                ->acceptedFileTypes([
                                    'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml',
                                    'video/mp4', 'video/webm', 'video/quicktime',
                                ])
                                ->maxSize(10240)
                                ->required()
                                // فحص مدة الفيديو ≤ 10 ثوانٍ.
                                // تنبيه: Filament يمرّر داخل هذا القاعدة كائن TemporaryUploadedFile
                                // وليس مسارًا نصيًا (انظر BaseFileUpload::getValidationRules في الـ vendor)،
                                // لذا يجب التعامل مع الكائن والنص معًا — وإلا تُصبح القاعدة معطّلة تمامًا.
                                ->rule(function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $ext = null;
                                        $path = null;

                                        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                            $ext = strtolower((string) ($value->getClientOriginalExtension() ?: $value->extension() ?: ''));
                                            $real = $value->getRealPath();
                                            $path = $real !== false ? $real : null;
                                        } elseif (is_string($value) && $value !== '') {
                                            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                                            foreach (['public', 'local'] as $disk) {
                                                try {
                                                    $full = Storage::disk($disk)->path($value);
                                                    if (is_file($full)) {
                                                        $path = $full;
                                                        break;
                                                    }
                                                } catch (\Throwable) {
                                                    continue;
                                                }
                                            }
                                        }

                                        if ($ext === null || ! in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true)) {
                                            return;
                                        }

                                        // تعذّر الوصول للملف (مثلًا على استضافة بلا SSH) — لا نمنع الحفظ
                                        if ($path === null || ! is_file($path)) {
                                            return;
                                        }

                                        $duration = media_duration($path);

                                        if ($duration > 10.5) {
                                            $fail('مدة الفيديو يجب أن تكون 10 ثوانٍ أو أقل (المدة الحالية: '.round($duration, 1).' ثانية).');
                                        }
                                    };
                                }),
                            Forms\Components\Toggle::make('is_main')->label('رئيسية'),
                            Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state) => is_string($state['file_path'] ?? null) && $state['file_path'] !== '' ? basename($state['file_path']) : 'ملف جديد'),
                ])
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // تحميل مسبق يمنع N+1: imageUrl() يقرأ media لكل صف،
            // و total_stock يجمع كميات variants لكل صف.
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['media', 'variants']))
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->defaultImageUrl(fn (Product $record) => $record->imageUrl())
                    ->circular(false),
                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم')
                    ->limit(30)
                    ->searchable(['name_ar', 'name_en']),
                Tables\Columns\TextColumn::make('internal_code')
                    ->label('الكود الداخلي')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name_ar')
                    ->label('القسم')
                    ->badge(),
                Tables\Columns\TextColumn::make('price_usd')
                    ->label('السعر')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('المخزون')
                    ->state(fn (Product $record) => $record->total_stock)
                    ->badge()
                    ->color(fn ($state) => $state < 1 ? 'danger' : ($state < 5 ? 'warning' : 'success')),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('مميز')
                    ->boolean(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعل')
                    ->afterStateUpdated(function () {
                        \Illuminate\Support\Facades\Cache::forget('settings');
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('القسم')
                    ->relationship('category', 'name_ar'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('مميز'),
                Tables\Filters\TernaryFilter::make('is_active')->label('مفعل'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
