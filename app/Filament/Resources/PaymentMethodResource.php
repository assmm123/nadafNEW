<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use App\Support\PaymentProof;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * وسائل الدفع — كل ما يدخله المالك هنا يظهر للعميل مباشرة.
 *
 * التنظيم:
 *   • النوع من قائمة **مجمَّعة** بالتصنيف (التسليم باليد · المحافظ · البنوك …)
 *   • قسم مستقلّ «ما يراه العميل» يحدّد فيه المالك أي حقل يُخفى
 *   • حقول مخصّصة بعنوان **وقيمة** — لا عنوان فقط، فيضيف ما يشاء
 *   • شروط خاصة تُكتب للعميل، وحدّ أدنى للطلب **يُنفَّذ فعلًا**
 */
class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return 'وسائل الدفع';
    }

    public static function getModelLabel(): string
    {
        return 'وسيلة دفع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'وسائل الدفع';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('النوع والاسم')
                ->description('التصنيف يحدّد المجموعة التي تظهر للعميل، والاسم هو ما يقرأه في نافذة الدفع.')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('النوع')
                        ->options(PaymentMethod::groupedTypes())
                        ->required()
                        ->searchable()
                        ->live()
                        ->helperText('الأنواع مرتّبة بالتصنيف: التسليم باليد · المحافظ · البنوك · التحويل المحلي · التحويل الدولي · العملات الرقمية.'),

                    Forms\Components\TextInput::make('name')
                        ->label('الاسم الظاهر للعميل')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('مثال: شام كاش — محفظتي'),

                    Forms\Components\Textarea::make('instructions')
                        ->label('تعليمات للعميل')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('خطوات مختصرة: «حوّل المبلغ ثم ارفع صورة الإيصال».')
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('بيانات الحساب')
                ->description('ما يملؤه العميل لتحويل المبلغ. أي حقل تُخفيه لا يظهر له.')
                ->schema([
                    Forms\Components\TextInput::make('account_number')
                        ->label('رقم الحساب / المحفظة')
                        ->maxLength(100)
                        ->placeholder('مثال: 0999 123 456'),

                    Forms\Components\TextInput::make('iban')
                        ->label('رقم الآيبان IBAN')
                        ->maxLength(50)
                        ->regex('/^[A-Za-z0-9 ]{15,50}$/')
                        ->helperText('حروف وأرقام فقط — مثال: SY03 0011 1234 5678 9012'),

                    Forms\Components\TextInput::make('account_name')
                        ->label('اسم المستلم')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('min_order_usd')
                        ->label('الحد الأدنى لقيمة الطلب $')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('اتركه فارغًا لبلا حدّ. وإن حُدّد تُخفى الوسيلة تلقائيًا عن الطلبات الأقل منه.')
                        ->columnSpanFull(),
                ])->columns(2),

            // ═══ ما يراه العميل — قسم بارز بلون نحاسي (مثل أعلام المنتجات) ═══
            Forms\Components\Section::make('ما يراه العميل')
                ->description('علّم ما تريد **إخفاءه**. وكل ما تتركه بلا تعليم يظهر للعميل.')
                ->icon('heroicon-o-eye')
                ->extraAttributes(['class' => 'nad-visibility'])
                ->schema([
                    Forms\Components\CheckboxList::make('hidden_fields')
                        ->label('')
                        ->options(PaymentMethod::HIDEABLE_FIELDS)
                        ->columns(3)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('conditions')
                        ->label('شروط خاصة تظهر للعميل')
                        ->rows(2)
                        ->maxLength(300)
                        ->placeholder('مثال: الحوالة باسم المتجر فقط · لا تُقبل الحوالات من خارج سوريا')
                        ->helperText('تُعرض في إطار مميّز تحت بيانات الوسيلة. وإن أردت إخفاءها علّم «الشروط الخاصة» أعلاه.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('حقول مخصّصة')
                ->description('أضف أي حقل تريده: عنوان يراه العميل + القيمة. مثال: «عنوان الفرع» = «المزة — شارع الجلاء».')
                ->schema([
                    Forms\Components\Repeater::make('extra_fields')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('العنوان كما يراه العميل')
                                ->required()
                                ->maxLength(80)
                                ->placeholder('مثال: عنوان الفرع'),

                            Forms\Components\TextInput::make('value')
                                ->label('القيمة')
                                ->maxLength(200)
                                ->placeholder('مثال: المزة — شارع الجلاء'),

                            Forms\Components\Select::make('type')
                                ->label('نوع الحقل')
                                ->options([
                                    'text' => 'سطر واحد',
                                    'textarea' => 'نص طويل',
                                ])
                                ->default('text')
                                ->required(),

                            Forms\Components\Toggle::make('visible')
                                ->label('يظهر للعميل')
                                ->default(true),
                        ])
                        ->columns(4)
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->defaultItems(0)
                        ->addActionLabel('＋ أضف حقلًا')
                        ->itemLabel(fn (array $state) => is_string($state['label'] ?? null) && $state['label'] !== ''
                            ? $state['label']
                            : 'حقل جديد'),
                ])
                ->columnSpanFull()
                ->collapsible(),

            Forms\Components\Section::make('الأيقونة والباركود')
                ->schema([
                    Forms\Components\FileUpload::make('icon_path')
                        ->label('أيقونة الوسيلة')
                        ->disk('public')
                        ->directory('payment-icons')
                        ->image()
                        ->maxSize(1024)
                        ->imagePreviewHeight('80')
                        ->helperText('PNG بخلفية شفافة. إن تُركت فارغة تُستخدم أيقونة النوع تلقائيًا.'),

                    Forms\Components\FileUpload::make('barcode_path')
                        ->label('صورة الباركود / QR')
                        ->disk('public')
                        ->directory('payment-barcodes')
                        ->image()
                        ->maxSize(2048)
                        ->imagePreviewHeight('120')
                        ->helperText('تظهر للعميل ليسحبها أو يمسحها. ويمكن إخفاؤها من قسم «ما يراه العميل».'),
                ])->columns(2)->collapsible()->collapsed(),

            Forms\Components\Section::make('الحالة')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعّلة — تظهر للعميل')
                        ->default(true),

                    Forms\Components\Toggle::make('is_default')
                        ->label('افتراضية — تُختار أولًا')
                        ->default(false),

                    Forms\Components\Toggle::make('requires_proof')
                        ->label('تتطلب إثبات دفع')
                        // الافتراضي `true` لا `false`: الأمان هو الأصل والإعفاء
                        // قرار واعٍ. وكان `false`، فكل وسيلة جديدة تُعفى صامتة
                        // إن لم يتذكّر المالك قلب المفتاح — وهو ما حدث فعلًا مع
                        // «syriatel» و«رستم باد».
                        ->default(true)
                        ->helperText('يُطلب من العميل رقم إيصال أو صورة إثبات قبل توليد كود الطلب. لا تُطفئه إلا لما لا حوالة فيه بطبيعته.'),

                    Forms\Components\Placeholder::make('proof_warning')
                        ->label('')
                        ->content('⚠️ هذه الوسيلة معفاة من إثبات الدفع — سيُولَّد كود الطلب بلا رقم إيصال ولا صورة. وهذا صحيح فقط لما لا حوالة تُرفق به بطبيعته.')
                        ->visible(fn (Forms\Get $get) => blank($get('requires_proof')) && $get('type') !== 'cash_on_delivery')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->helperText('الأصغر يظهر أولًا داخل مجموعته.'),
                ])->columns(4)->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الوسيلة')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (PaymentMethod $record) => $record->groupLabel()),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PaymentMethod::TYPES[$state]['ar'] ?? $state)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('الحساب')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\TextColumn::make('min_order_usd')
                    ->label('حدّ أدنى')
                    ->money('USD')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('hidden_fields')
                    ->label('مُخفى عن العميل')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state).' حقل' : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('requires_proof')
                    ->label('إثبات الدفع')
                    ->badge()
                    ->alignCenter()
                    // «معفاة» بالأحمر و«معفاة بطبيعتها» بالرمادي: الأولى خلل
                    // يحتاج انتباهًا، والثانية دفع عند التسليم لا حوالة فيه.
                    // فصار الإعفاء مرئيًا في الجدول لا مخفيًّا في مفتاح.
                    ->formatStateUsing(fn ($state, PaymentMethod $record) => $state
                        ? 'مطلوب'
                        : (in_array($record->type, PaymentProof::EXEMPT_TYPES, true) ? 'معفاة بطبيعتها' : 'معفاة ⚠'))
                    ->color(fn ($state, PaymentMethod $record) => $state
                        ? 'success'
                        : (in_array($record->type, PaymentProof::EXEMPT_TYPES, true) ? 'gray' : 'danger')),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('افتراضية')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّلة')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(collect(PaymentMethod::TYPES)->mapWithKeys(fn ($t, $k) => [$k => $t['ar']])),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مفعّلة')
                    ->falseLabel('معطّلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('لا وسائل دفع بعد')
            ->emptyStateDescription('أضف وسيلة دفع واحدة على الأقل، وإلا لن يستطيع العميل إتمام الطلب.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePaymentMethods::route('/')];
    }
}
