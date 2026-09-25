<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Slide;
use App\Services\TelegramService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    /** الإعدادات العامة — للمالك وحده (لا مدير متجر ولا غيره) */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('settings.manage');
    }
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'الإعدادات العامة';
    }

    public function getTitle(): string
    {
        return 'الإعدادات العامة';
    }

    public function mount(): void
    {
        $this->form->fill([
            'store_name_ar' => Setting::get('store_name_ar'),
            'store_name_en' => Setting::get('store_name_en'),
            'store_phone' => Setting::get('store_phone'),
            'wa_country_code' => Setting::get('wa_country_code', '963'),
            'exchange_rate' => Setting::get('exchange_rate', 15000),
            'shipping_enabled' => Setting::bool('shipping_enabled', true),
            'shipping_fee_usd' => Setting::get('shipping_fee_usd', 0),
            'wholesale_min_quantity' => Setting::get('wholesale_min_quantity', 10),
            'wholesale_min_amount_usd' => Setting::get('wholesale_min_amount_usd', 200),
            'max_qty_per_item' => Setting::get('max_qty_per_item', 99),

            // ── مرحلة قبض الدفع ──
            'payment_collect_stage' => Setting::get('payment_collect_stage', 'on_deliver'),

            // ── الصفحة الرئيسية ──
            'home_sections' => collect(\App\Support\HomeSections::active())
                ->map(fn (string $s) => ['section' => $s])
                ->all(),
            'home_hero_height' => Setting::get('home_hero_height', 56),
            // وجهة زر «تسوق الآن» في الشرائح التي لا رابط لها
            'slide_default_link' => Setting::get('slide_default_link', Slide::DEFAULT_LINK),
            'about_kicker' => Setting::get('about_kicker', 'عن نداف'),
            'about_title' => Setting::get('about_title', 'لا نبيع كرافات'),
            'about_title_accent' => Setting::get('about_title_accent', 'نبيع التفاصيل'),
            'about_body' => Setting::get('about_body', 'نختار كل قطعة يدويًا: حرير مستورد، حاشية مخيطة، وألوان لا تنافس الطقم. نُشحن إلى كل المحافظات، ومع كل طلب فاتورة نظامية.'),
            'about_stat_1_value' => Setting::get('about_stat_1_value', '73'),
            'about_stat_1_label' => Setting::get('about_stat_1_label', 'قطعة معروضة'),
            'about_stat_2_value' => Setting::get('about_stat_2_value', '8'),
            'about_stat_2_label' => Setting::get('about_stat_2_label', 'سنوات خبرة'),
            'about_stat_3_value' => Setting::get('about_stat_3_value', '14'),
            'about_stat_3_label' => Setting::get('about_stat_3_label', 'محافظة توصيل'),
            'about_cta_1_label' => Setting::get('about_cta_1_label', 'تسوّق الآن'),
            'about_cta_1_link' => Setting::get('about_cta_1_link', '#categories'),
            'about_cta_2_label' => Setting::get('about_cta_2_label'),
            'about_cta_2_link' => Setting::get('about_cta_2_link'),
            'trust_1_title' => Setting::get('trust_1_title', 'توصيل لكل المحافظات'),
            'trust_1_text' => Setting::get('trust_1_text', 'دمشق وريفها خلال ٢٤ ساعة، وبقية المحافظات ٢–٤ أيام.'),
            'trust_2_title' => Setting::get('trust_2_title', 'دفع موثّق'),
            'trust_2_text' => Setting::get('trust_2_text', 'شام كاش أو حوالة بنكية، ومع كل طلب فاتورة نظامية.'),
            'trust_3_title' => Setting::get('trust_3_title', 'استبدال خلال ٣ أيام'),
            'trust_3_text' => Setting::get('trust_3_text', 'لم يناسب المقاس أو اللون؟ نستبدله بلا أسئلة.'),
            'trust_4_title' => Setting::get('trust_4_title', 'سعر جملة من ١٠ قطع'),
            'trust_4_text' => Setting::get('trust_4_text', 'يُطبَّق تلقائيًا عند بلوغ الكمية أو قيمة الطلب.'),
            'hide_wholesale_button' => Setting::bool('hide_wholesale_button'),
            'archiving_enabled' => Setting::bool('archiving_enabled', true),
            'archive_after_days' => Setting::get('archive_after_days', 30),
            'inquiry_enabled_global' => Setting::bool('inquiry_enabled_global', true),
            'price_display_mode' => Setting::get('price_display_mode', 'both'),
            'hide_prices' => Setting::bool('hide_prices'),
            'whatsapp_number' => Setting::get('whatsapp_number'),
            'logo_path' => Setting::get('logo_path'),
            'stamp_gold_path' => Setting::get('stamp_gold_path'),
            'stamp_green_path' => Setting::get('stamp_green_path'),
            'stamp_top_text' => Setting::get('stamp_top_text', 'متجر نداف'),
            'invoice_show_logo' => Setting::bool('invoice_show_logo', true),
            'invoice_title_draft' => Setting::get('invoice_title_draft', 'إيصال طلب'),
            'invoice_title_certified' => Setting::get('invoice_title_certified', 'فاتورة نظامية معتمدة'),
            'invoice_subtitle' => Setting::get('invoice_subtitle', 'كرافات وأطقم رسمية وإكسسوارات'),
            'invoice_color_primary' => Setting::get('invoice_color_primary', '#1a2a3a'),
            'invoice_color_accent' => Setting::get('invoice_color_accent', '#c9a84c'),
            'invoice_footer_note' => Setting::get('invoice_footer_note', 'شكرًا لتسوقكم من متجر نداف — جميع الحقوق محفوظة'),
            'invoice_show_green_stamp' => Setting::bool('invoice_show_green_stamp', true),
            'invoice_green_stamp_label' => Setting::get('invoice_green_stamp_label', '✓ تم قبض الدفع'),
            'invoice_green_stamp_position' => Setting::get('invoice_green_stamp_position', 'bottom-left'),
            'invoice_green_stamp_size' => Setting::get('invoice_green_stamp_size', 140),
            'invoice_show_gold_stamp' => Setting::bool('invoice_show_gold_stamp', true),
            'invoice_gold_stamp_label' => Setting::get('invoice_gold_stamp_label', 'فاتورة نظامية معتمدة'),
            'invoice_gold_stamp_position' => Setting::get('invoice_gold_stamp_position', 'bottom-left'),
            'invoice_gold_stamp_size' => Setting::get('invoice_gold_stamp_size', 170),
            'notify_telegram_enabled' => Setting::bool('notify_telegram_enabled'),
            'telegram_bot_token' => Setting::get('telegram_bot_token'),
            'telegram_chat_id' => Setting::get('telegram_chat_id'),
            'telegram_orders_chat_id' => Setting::get('telegram_orders_chat_id'),
            'telegram_inventory_chat_id' => Setting::get('telegram_inventory_chat_id'),
            'telegram_verify_ssl' => Setting::bool('telegram_verify_ssl', true),
            'bot_orders_token' => Setting::get('bot_orders_token'),
            'bot_inventory_token' => Setting::get('bot_inventory_token'),
            // ⚠️ المفتاح هنا **يجب أن يطابق اسم الحقل في النموذج حرفيًا**.
            // كان مكتوبًا `bot_command_token` بينما الحقل اسمه
            // `telegram_command_bot_token` ⇒ القيمة المحفوظة لا تُحمَّل أبدًا،
            // فيظهر الحقل فارغًا، و`save()` يمرّ على كل حقول النموذج فيكتب
            // فراغًا فوق التوكن — **فقدان بيانات صامت** بمجرد فتح الإعدادات
            // وحفظها. (نمط الخطأ نفسه الذي جعل حقول البوتات الثلاثة بلا أثر.)
            'telegram_command_bot_token' => Setting::get('telegram_command_bot_token'),
            'telegram_admin_chat_id' => Setting::get('telegram_admin_chat_id'),
            'bot_polling_enabled' => Setting::bool('bot_polling_enabled'),
            'bot_scheduled_reports' => Setting::bool('bot_scheduled_reports'),
            'bot_abandoned_alerts' => Setting::bool('bot_abandoned_alerts'),
            'notify_email_enabled' => Setting::bool('notify_email_enabled'),
            'notify_email' => Setting::get('notify_email'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات المتجر')->schema([
                    Forms\Components\TextInput::make('store_name_ar')->label('اسم المتجر بالعربية')->maxLength(100),
                    Forms\Components\TextInput::make('store_name_en')->label('اسم المتجر بالإنجليزية')->maxLength(100),
                    Forms\Components\TextInput::make('store_phone')->label('هاتف المتجر')->maxLength(30),
                    Forms\Components\TextInput::make('wa_country_code')
                        ->label('مفتاح الدولة لواتساب')
                        ->maxLength(5)
                        ->placeholder('963')
                        ->helperText('أرقام بلا مفتاح: هاتف العميل المحفوظ محليًا مثل `0987654365` لا يفتح على واتساب أبدًا. اكتب المفتاح هنا (‏963 لسوريا) فيُستبدل الصفر به عند بناء رابط المراسلة.'),
                ])->columns(3),

                Forms\Components\Section::make('العملة')
                    ->schema([
                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('سعر الصرف (ل.س لكل 1$)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('تُحسب أسعار الليرة تلقائيًا من هذا السعر، ويُثبّت السعر مع كل طلب'),
                    ])->columns(1),

                Forms\Components\Section::make('الشحن')->schema([
                    Forms\Components\Toggle::make('shipping_enabled')->label('تفعيل التوصيل المحلي')->live(),
                    Forms\Components\TextInput::make('shipping_fee_usd')
                        ->label('أجرة التوصيل $')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get) => (bool) $get('shipping_enabled')),
                ])->columns(2),

                Forms\Components\Section::make('الجملة')->schema([
                    Forms\Components\Toggle::make('hide_wholesale_button')
                        ->label('إخفاء الجملة نهائيًا عن المتجر')
                        ->helperText('عند تفعيله لا يرى أي عميل أسعار الجملة في أي منتج، حتى لو حُددت'),
                    Forms\Components\TextInput::make('wholesale_min_quantity')
                        ->label('الحد الأدنى للكمية (لكل عنصر)')
                        ->numeric()->minValue(1),
                    Forms\Components\TextInput::make('wholesale_min_amount_usd')
                        ->label('الحد الأدنى لقيمة الطلب $')
                        ->numeric()->minValue(0)
                        ->helperText('يطبق سعر الجملة عند بلوغ أيٍّ من الحدين'),
                ])->columns(2),

                Forms\Components\Section::make('حدود السلة')
                    ->description('ينطبق على المنتجات التي لا تُتتبَّع كميتها بالمخزون. أما المنتجات ذات المتغيرات فسقفها مخزونها الفعلي دائمًا، وأي إعداد هنا لا يتجاوزه.')
                    ->schema([
                        Forms\Components\TextInput::make('max_qty_per_item')
                            ->label('أقصى كمية للعنصر الواحد')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(9999)
                            ->default(99)
                            ->helperText('يمنع طلب كميات ضخمة بالخطأ أو بالعبث — يُطبَّق عند الإضافة وعند تعديل الكمية داخل السلة معًا'),
                    ])->columns(2),

                Forms\Components\Section::make('الصفحة الرئيسية')
                    ->description('ترتيب الأقسام ونصوص قسم تعريف المنصة بجانب السلايدر. الشرائح نفسها تُدار من «السلايدر» في القائمة.')
                    ->schema([
                        Forms\Components\Repeater::make('home_sections')
                            ->label('ترتيب الأقسام')
                            ->addActionLabel('أضف قسمًا')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible(false)
                            ->defaultItems(0)
                            ->schema([
                                Forms\Components\Select::make('section')
                                    ->label('القسم')
                                    ->options(\App\Support\HomeSections::options())
                                    ->required()
                                    ->distinct(),
                            ])
                            ->columns(1)
                            ->helperText('اسحب لتغيير الترتيب. احذف صفًا لإخفاء القسم. أي قسم غير مذكور يظهر تلقائيًا في آخر الصفحة.')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('home_hero_height')
                            ->label('ارتفاع السلايدر (% من الشاشة)')
                            ->numeric()->integer()->minValue(38)->maxValue(90)->default(56)
                            ->suffix('%')
                            ->helperText('56 تعني نصف الشاشة تقريبًا. عمود تعريف المنصة يأخذ نفس الارتفاع.'),

                        Forms\Components\TextInput::make('slide_default_link')
                            ->label('وجهة زر «تسوق الآن» في السلايدر')
                            ->maxLength(255)
                            ->default(Slide::DEFAULT_LINK)
                            ->placeholder('#categories')
                            ->helperText('تُستخدم في الشرائح التي لا رابط لها. `#categories` يذهب إلى قسم الأقسام في الرئيسية. ويمكنك كتابة `/c/كرافات` أو رابط كامل.'),

                        Forms\Components\TextInput::make('about_kicker')->label('وسم صغير فوق العنوان')->maxLength(60),
                        Forms\Components\TextInput::make('about_title')->label('عنوان التعريف — السطر الأول')->maxLength(120),
                        Forms\Components\TextInput::make('about_title_accent')->label('عنوان التعريف — السطر الثاني (باهت)')->maxLength(120),
                        Forms\Components\Textarea::make('about_body')->label('فقرة التعريف')->rows(3)->maxLength(400)->columnSpanFull(),

                        Forms\Components\TextInput::make('about_stat_1_value')->label('رقم ١')->maxLength(20),
                        Forms\Components\TextInput::make('about_stat_1_label')->label('تسمية رقم ١')->maxLength(40),
                        Forms\Components\TextInput::make('about_stat_2_value')->label('رقم ٢')->maxLength(20),
                        Forms\Components\TextInput::make('about_stat_2_label')->label('تسمية رقم ٢')->maxLength(40),
                        Forms\Components\TextInput::make('about_stat_3_value')->label('رقم ٣')->maxLength(20),
                        Forms\Components\TextInput::make('about_stat_3_label')->label('تسمية رقم ٣')->maxLength(40),

                        Forms\Components\TextInput::make('about_cta_1_label')->label('الزر الأساسي — النص')->maxLength(40),
                        Forms\Components\TextInput::make('about_cta_1_link')->label('الزر الأساسي — الرابط')->maxLength(200),
                        Forms\Components\TextInput::make('about_cta_2_label')->label('الزر الثانوي — النص (اتركه فارغًا لإخفائه)')->maxLength(40),
                        Forms\Components\TextInput::make('about_cta_2_link')->label('الزر الثانوي — الرابط')->maxLength(200),

                        Forms\Components\Fieldset::make('شريط الثقة')
                            ->schema([
                                Forms\Components\TextInput::make('trust_1_title')->label('بطاقة ١ — العنوان')->maxLength(60),
                                Forms\Components\TextInput::make('trust_1_text')->label('بطاقة ١ — النص')->maxLength(160),
                                Forms\Components\TextInput::make('trust_2_title')->label('بطاقة ٢ — العنوان')->maxLength(60),
                                Forms\Components\TextInput::make('trust_2_text')->label('بطاقة ٢ — النص')->maxLength(160),
                                Forms\Components\TextInput::make('trust_3_title')->label('بطاقة ٣ — العنوان')->maxLength(60),
                                Forms\Components\TextInput::make('trust_3_text')->label('بطاقة ٣ — النص')->maxLength(160),
                                Forms\Components\TextInput::make('trust_4_title')->label('بطاقة ٤ — العنوان')->maxLength(60),
                                Forms\Components\TextInput::make('trust_4_text')->label('بطاقة ٤ — النص')->maxLength(160),
                            ])->columns(2)->columnSpanFull(),
                    ])->columns(2)->collapsible()->collapsed(),

                Forms\Components\Section::make('مرحلة قبض الدفع')
                    ->description('تحدّد المرحلة التي يُشترط فيها قبض المال قبل التسليم. والدفع عند الاستلام يحتاج «غير مشروط».')
                    ->schema([
                        Forms\Components\Select::make('payment_collect_stage')
                            ->label('متى يُشترط قبض الدفع؟')
                            ->options([
                                'on_confirm' => 'عند التأكيد — يُقبض المال قبل بدء التحضير',
                                'on_ship' => 'عند الشحن — يُقبض لحظة تسليمه للتوصيل',
                                'on_deliver' => 'عند التسليم — يُقبض عند استلام العميل (الافتراضي)',
                                'optional' => 'غير مشروط — يُسمح بالتسليم بلا قبض (للدفع عند الاستلام)',
                            ])
                            ->default('on_deliver')
                            ->required()
                            ->helperText('الختم الذهبي (تم التسليم) لا يُصدر لطلب غير مقبوض إلا إن اخترت «غير مشروط».'),
                    ])->columns(1)->collapsible()->collapsed(),

                Forms\Components\Section::make('الأرشفة التلقائية للطلبات')
                    ->description('الطلبات المكتملة/الملغاة القديمة تُنقل للأرشيف تلقائيًا (تبقى قابلة للعرض والبحث من صفحة الأرشيف)')
                    ->schema([
                        Forms\Components\Toggle::make('archiving_enabled')->label('تفعيل الأرشفة التلقائية')->live(),
                        Forms\Components\TextInput::make('archive_after_days')
                            ->label('أرشفة الطلبات بعد (يوم)')
                            ->numeric()->minValue(1)->maxValue(365)
                            ->visible(fn (Forms\Get $get) => (bool) $get('archiving_enabled'))
                            ->helperText('تُنفّذ يوميًا الساعة 2 فجرًا'),
                    ])->columns(2),

                Forms\Components\Section::make('الأسعار الظاهرة — تحكم كامل')
                    ->description('حدد أي سعر يراه الزائر: العادي، سعر الجملة/السعر الثاني، أو إخفاء كامل مع أزرار تواصل كبيرة')
                    ->schema([
                        Forms\Components\Select::make('price_display_mode')
                            ->label('وضع عرض الأسعار')
                            ->options([
                                'both' => 'السعر العادي + الجملة (عادي)',
                                'second_big' => 'السعر الثاني (الجملة) بحجم كبير مع إخفاء العادي',
                                'normal_big' => 'السعر العادي بحجم كبير (إخفاء الجملة)',
                                'whatsapp' => 'إخفاء الكل — أزرار واتساب بأيقونات كبيرة',
                                'none' => 'إخفاء كل الأسعار نهائيًا (بلا بديل)',
                            ])
                            ->default('both')
                            ->live()
                            ->columnSpan(2),
                        Forms\Components\Toggle::make('hide_prices')
                            ->label('وضع الاستفسار (اختصار سريع لإخفاء الكل)')
                            ->helperText('بمفعله يتحول العرض تلقائيًا إلى وضع واتساب')
                            ->live()->columnSpan(1),
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('رقم واتساب لاستقبال الطلبات والاستفسارات')
                            ->placeholder('9639xxxxxxxx')
                            ->maxLength(20)
                            ->regex('/^\d{8,15}$/')
                            ->helperText('بالصيغة الدولية دون + — مثال: 963944123456. يُستخدم في وضع الإخفاء الكامل وزر الاستفسار')
                            ->visible(fn (Forms\Get $get) => $get('price_display_mode') === 'whatsapp' || $get('hide_prices')),
                    ])->columns(2),

                Forms\Components\Section::make('زر الاستفسار')->schema([
                    Forms\Components\Toggle::make('inquiry_enabled_global')->label('تفعيل الاستفسار في كل المنتجات'),
                ])->columns(1),

                Forms\Components\Section::make('الهوية البصرية — الشعار والأختام')
                    ->description('ارفع صورًا بخلفية شفافة (PNG). عند رفع الشعار يُستخدم في الهيدر والفوتر بدل الشعار الافتراضي، وعند رفع الأختام تُستخدم على الفاتورة بدل الختم المرسوم')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('شعار المتجر')
                            ->disk('public')
                            ->directory('branding')
                            ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                            ->maxSize(2048)
                            ->imagePreviewHeight('90')
                            ->helperText('يُفضَّل PNG شفاف بعنصرين: الدرع + الاسم، أو أي شعار تريده يظهر أعلى الموقع'),
                        Forms\Components\FileUpload::make('stamp_gold_path')
                            ->label('الختم الذهبي (اعتماد التوثيق)')
                            ->disk('public')
                            ->directory('branding')
                            ->acceptedFileTypes(['image/png'])
                            ->maxSize(2048)
                            ->imagePreviewHeight('110')
                            ->helperText('يظهر على الفاتورة المعتمدة — PNG شفاف. إن تُرك فارغًا يُرسم الختم الذهبي تلقائيًا'),
                        Forms\Components\FileUpload::make('stamp_green_path')
                            ->label('الختم الأخضر (قبض الدفع)')
                            ->disk('public')
                            ->directory('branding')
                            ->acceptedFileTypes(['image/png'])
                            ->maxSize(2048)
                            ->imagePreviewHeight('110')
                            ->helperText('يُثبت على الفاتورة تلقائيًا لحظة اعتماد إثبات الدفع/قبض المال — PNG شفاف'),
                    ])->columns(3),

                Forms\Components\Section::make('ختم التوثيق الرسمي')
                    ->description('يظهر الختم على الفاتورة النظامية بعد اعتماد الطلب — الاسم أعلى الإطار والتاريخ والوقت الحقيقي أسفله')
                    ->schema([
                        Forms\Components\TextInput::make('stamp_top_text')
                            ->label('الاسم أعلى الختم')
                            ->maxLength(60)
                            ->helperText('مثال: متجر نداف للكرافات — يظهر مقوّسًا أعلى إطار الختم'),
                    ])->columns(1),

                Forms\Components\Section::make('محرر الفاتورة — التحكم بكل التفاصيل')
                    ->description('الأدمن ينسّق الفاتورة: العناوين، موضع كل ختم على حدة، الألوان، الأحجام والتواريخ — تُطبق على كل الفواتير')
                    ->schema([
                        Forms\Components\Toggle::make('invoice_show_logo')
                            ->label('إظهار الشعار في الفاتورة')->default(true)->live()->columnSpan(1),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('invoice_title_draft')
                                ->label('عنوان الوثيقة المبدئية')
                                ->default('إيصال طلب')->maxLength(40),
                            Forms\Components\TextInput::make('invoice_title_certified')
                                ->label('عنوان الوثيقة المعتمدة')
                                ->default('فاتورة نظامية معتمدة')->maxLength(40),
                            Forms\Components\TextInput::make('invoice_subtitle')
                                ->label('سطر فرعي تحت الهيدر')
                                ->default('كرافات وأطقم رسمية وإكسسوارات')->maxLength(60),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\ColorPicker::make('invoice_color_primary')
                                ->label('اللون الأساسي (الهيدر)')
                                ->default('#1a2a3a')
                                ->helperText('كحلي افتراضيًا'),
                            Forms\Components\ColorPicker::make('invoice_color_accent')
                                ->label('لون التمييز (الإطارات والشارة)')
                                ->default('#c9a84c')
                                ->helperText('ذهبي افتراضيًا'),
                            Forms\Components\TextInput::make('invoice_footer_note')
                                ->label('سطر أسفل الفاتورة')
                                ->default('شكرًا لتسوقكم من متجر نداف — جميع الحقوق محفوظة')
                                ->maxLength(120),
                        ]),

                        Forms\Components\Fieldset::make('الختم الأخضر — قبض الدفع')
                            ->schema([
                                Forms\Components\Toggle::make('invoice_show_green_stamp')
                                    ->label('تفعيل الختم الأخضر')->default(true)->live()->columnSpan(1),
                                Forms\Components\TextInput::make('invoice_green_stamp_label')
                                    ->label('نص شارته')->default('✓ تم قبض الدفع')->maxLength(40)->columnSpan(1),
                                Forms\Components\Select::make('invoice_green_stamp_position')
                                    ->label('موضعه')
                                    ->options(['bottom-left' => 'أسفل يسار', 'bottom-right' => 'أسفل يمين', 'bottom-center' => 'أسفل الوسط'])
                                    ->default('bottom-left')->columnSpan(1),
                                Forms\Components\TextInput::make('invoice_green_stamp_size')
                                    ->label('حجمه (بكسل)')->numeric()->default(140)->minValue(90)->maxValue(240)->columnSpan(1),
                            ])->columns(2),

                        Forms\Components\Fieldset::make('الختم الذهبي — اعتماد التوثيق')
                            ->schema([
                                Forms\Components\Toggle::make('invoice_show_gold_stamp')
                                    ->label('تفعيل الختم الذهبي')->default(true)->live()->columnSpan(1),
                                Forms\Components\TextInput::make('invoice_gold_stamp_label')
                                    ->label('نص شارته')->default('فاتورة نظامية معتمدة')->maxLength(40)->columnSpan(1),
                                Forms\Components\Select::make('invoice_gold_stamp_position')
                                    ->label('موضعه')
                                    ->options(['bottom-left' => 'أسفل يسار', 'bottom-right' => 'أسفل يمين', 'bottom-center' => 'أسفل الوسط'])
                                    ->default('bottom-left')->columnSpan(1),
                                Forms\Components\TextInput::make('invoice_gold_stamp_size')
                                    ->label('حجمه (بكسل)')->numeric()->default(170)->minValue(90)->maxValue(280)->columnSpan(1),
                            ])->columns(2),
                    ])->columns(1),

                Forms\Components\Section::make('إشعارات الطلبات الجديدة — تيليجرام')
                    ->description('أنشئ بوتًا مجانيًا من @BotFather وأرسل له رسالة، ثم انسخ التوكن هنا. للحصول على chat_id أرسل رسالة للبوت ثم افتح: api.telegram.org/bot{TOKEN}/getUpdates')
                    ->schema([
                        Forms\Components\Toggle::make('notify_telegram_enabled')->label('تفعيل إشعار تيليجرام')->live(),
                        Forms\Components\TextInput::make('telegram_bot_token')
                            ->label('Bot Token')
                            ->password()->revealable()
                            ->visible(fn (Forms\Get $get) => (bool) $get('notify_telegram_enabled')),
                        Forms\Components\TextInput::make('telegram_chat_id')
                            ->label('Chat ID الأساسي')
                            ->visible(fn (Forms\Get $get) => (bool) $get('notify_telegram_enabled')),
                        Forms\Components\TextInput::make('telegram_orders_chat_id')
                            ->label('Chat ID قناة الطلبات (اختياري)')
                            ->hint('فارغ = الأساسي')
                            ->visible(fn (Forms\Get $get) => (bool) $get('notify_telegram_enabled')),
                        Forms\Components\TextInput::make('telegram_inventory_chat_id')
                            ->label('Chat ID قناة الجرد والمخزون (اختياري)')
                            ->hint('فارغ = الأساسي')
                            ->visible(fn (Forms\Get $get) => (bool) $get('notify_telegram_enabled')),
                        Forms\Components\Toggle::make('telegram_verify_ssl')
                            ->label('التحقق من شهادة SSL')
                            ->default(true)
                            ->helperText('أطفئه فقط إذا ظهر خطأ «self-signed certificate» — يحدث خلف بعض برامج VPN أو مضادات الفيروسات التي تفحص الاتصال. أطفئه ثم اضغط زر الاختبار.')
                            ->visible(fn (Forms\Get $get) => (bool) $get('notify_telegram_enabled')),
                    ])->columns(2),

                Forms\Components\Section::make('البوتات الثلاثة — تيليجرام')
                    ->description('للبوت الأول (الطلبات): أنشئ بوتًا من @BotFather والصق توكنه هنا — الإشعارات فورية. البوت الثاني (تنبيهات المخزون) بوت منفصل للتنبيهات. البوت الثالث (تفاعلي) يستقبل أوامرك — اجعل الاستقصاء مفعّلًا وأرسل له «/start» ثم أضف chat_id الخاص بك')
                    ->schema([
                        Forms\Components\TextInput::make('bot_orders_token')
                            ->label('توكن بوت الطلبات (إشعار كل طلب جديد)')
                            ->password()->revealable()
                            ->maxLength(60)
                            ->helperText('إن تُرك فارغًا يُستخدم التوكن الأساسي أعلاه'),
                        Forms\Components\TextInput::make('bot_inventory_token')
                            ->label('توكن بوت تنبيهات المخزون')
                            ->password()->revealable()
                            ->maxLength(60)
                            ->helperText('إن تُرك فارغًا يُستخدم التوكن الأساسي أعلاه'),
                        Forms\Components\TextInput::make('telegram_command_bot_token')
                            ->label('توكن البوت التفاعلي (يستقبل أوامرك)')
                            ->password()->revealable()
                            ->maxLength(60),
                        Forms\Components\TextInput::make('telegram_admin_chat_id')
                            ->label('chat_id المدير للبوت التفاعلي')
                            ->maxLength(30)
                            ->helperText('الأوامر مقصورة على هذا chat_id فقط — أرسل «/start» للبوت وسيخبرك برقمك'),
                        Forms\Components\Toggle::make('bot_polling_enabled')
                            ->label('تفعيل استقبال الأوامر (الاستقصاء كل دقيقة)')
                            ->helperText('يتطلب cron أو مجدولًا نشطًا'),
                        Forms\Components\Toggle::make('bot_scheduled_reports')
                            ->label('تقارير تلقائية صباحية (9 صباحًا) ومسائية (9 مساءً)'),
                        Forms\Components\Toggle::make('bot_abandoned_alerts')
                            ->label('تنبيه السلات المتروكة (مرتان يوميًا)')
                            ->helperText('يقترح التواصل مع العملاء الذين تركوا سلات فيها منتجات دون إتمام الشراء'),
                    ])->columns(2),

                Forms\Components\Section::make('إشعارات الطلبات الجديدة — البريد الإلكتروني')->schema([
                    Forms\Components\Toggle::make('notify_email_enabled')->label('تفعيل إشعار البريد')->live(),
                    Forms\Components\TextInput::make('notify_email')
                        ->label('بريد المتجر لاستقبال الإشعارات')
                        ->email()
                        ->visible(fn (Forms\Get $get) => (bool) $get('notify_email_enabled')),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, match (true) {
                is_bool($value) => $value ? '1' : '0',
                // الإعدادات المصفوفية (مثل ترتيب أقسام الرئيسية) تُخزَّن JSON،
                // لأن عمود settings.value نصّي. HomeSections يفكّها عند القراءة.
                is_array($value) => json_encode(array_values($value), JSON_UNESCAPED_UNICODE),
                default => $value,
            });
        }

        Notification::make()->title('تم حفظ الإعدادات بنجاح')->success()->send();
    }

    public function testTelegram(): void
    {
        $data = $this->form->getState();

        if (empty($data['telegram_bot_token']) || empty($data['telegram_chat_id'])) {
            Notification::make()->title('أدخل التوكن و Chat ID أولًا')->danger()->send();

            return;
        }

        try {
            $result = TelegramService::test($data['telegram_bot_token'], $data['telegram_chat_id']);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('فشل الاتصال بتيليجرام')
                ->body(\Illuminate\Support\Str::limit($e->getMessage(), 120).' — تحقق من اتصال الإنترنت أو الإعدادات.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($result['ok'] ?? false) {
            Notification::make()->title('نجح الإرسال! تحقق من تيليجرام')->success()->send();
        } else {
            Notification::make()
                ->title('فشل الإرسال: '.($result['description'] ?? 'خطأ غير معروف'))
                ->body('تأكد من صحة التوكن ومن أنك أرسلت رسالة للبوت أولًا ثم تحقق من Chat ID.')
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
