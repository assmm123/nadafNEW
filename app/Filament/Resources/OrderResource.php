<?php

namespace App\Filament\Resources;

use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * قسم الطلبات — مطابق لنموذج NADAF-Orders-Concept.html.
 *
 * المبدأ: **الحالة** (أين وصل الطلب) و**المال** (أقُبض أم لا) بُعدان منفصلان.
 *   • الحالة: قيد المراجعة → قيد التحضير → تم الشحن → تم التسليم | ملغي
 *   • الختم الأخضر = قبض المال · الختم الذهبي = تسليم الطلب
 * وكل ختم بفعل صريح من الأدمن، واسمه ووقته يُسجَّلان تلقائيًا.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    // 0 لا 1: «المنتجات» كانت على 1 أيضًا، فكان التعادل يجعل الترتيب غير مضمون.
    // و0 يضمن أن الطلبات أول عنصر في القائمة بعد لوحة التحكم.
    protected static ?int $navigationSort = 0;

    /**
     * بلا مجموعة — ليظهر **أول عنصر في القائمة بعد لوحة التحكم**.
     * (Filament يعرض العناصر غير المجموعة قبل المجموعات، فالمجموعة كانت
     * تُنزله أسفل القائمة رغم أنه القسم الأكثر استخدامًا.)
     *
     * وهو **قسم الطلبات الوحيد**: «لوحة الطلبات» و«الأرشيف» أُخفيا من القائمة —
     * الأولى أُلغيت لأن النموذج المعتمد قائمة لا كروت، والثاني يُفتح من زر
     * «الأرشيف» في شريط أدوات القسم.
     */
    protected static ?string $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return 'الطلبات';
    }

    public static function getModelLabel(): string
    {
        return 'طلب';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الطلبات';
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Order::whereNull('archived_at')->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** الطلب لا يُحرَّر — تُدار حالته وأختامه بأزرار صريحة */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // columns(1): بلا تحديد، شبكة المخطط الافتراضية عمودان، فيأخذ مكوّن
        // التبويبات عمودًا واحدًا ⇒ المحتوى في نصف العرض ويضيع باقي الصفحة.
        return $infolist
            ->columns(1)
            ->schema([
                Infolists\Components\Tabs::make('order')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                    // ── الملخص ──
                    Infolists\Components\Tabs\Tab::make('الملخص')->schema([
                        Infolists\Components\Section::make('معلومات الطلب')->schema([
                            Infolists\Components\TextEntry::make('order_code')
                                ->label('الكود')
                                ->copyable()
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                ->weight(FontWeight::Bold)
                                ->color('primary'),
                            Infolists\Components\TextEntry::make('status')
                                ->label('الحالة')
                                ->badge()
                                ->formatStateUsing(fn ($state) => Order::statusLabel($state))
                                ->color(fn ($state) => Order::statusColor($state)),
                            Infolists\Components\TextEntry::make('created_at')->label('التاريخ')->dateTime('Y/m/d H:i'),
                            Infolists\Components\TextEntry::make('exchange_rate')->label('سعر الصرف لحظة الطلب'),
                        ])->columns(4),

                        Infolists\Components\Section::make('الإجماليات')->schema([
                            Infolists\Components\TextEntry::make('subtotal_usd')->label('المجموع الفرعي')->money('USD'),
                            Infolists\Components\TextEntry::make('discount_usd')->label('الخصم')->money('USD'),
                            Infolists\Components\TextEntry::make('shipping_usd')->label('الشحن')->money('USD'),
                            Infolists\Components\TextEntry::make('total_usd')->label('الإجمالي $')
                                ->money('USD')->weight(FontWeight::Bold)->color('primary'),
                            Infolists\Components\TextEntry::make('total_syp')->label('الإجمالي ل.س')->numeric(),
                        ])->columns(5),

                        Infolists\Components\Section::make('حالة الطلب محاسبيًا')->schema([
                            Infolists\Components\IconEntry::make('payment_confirmed_at')
                                ->label('الختم الأخضر — قبض الدفع')
                                ->boolean()
                                ->trueIcon('heroicon-o-check-badge')
                                ->falseIcon('heroicon-o-x-circle')
                                ->trueColor('success')
                                ->falseColor('gray'),
                            Infolists\Components\TextEntry::make('payment_confirmed_at')
                                ->label('تاريخ القبض')->dateTime('Y/m/d — H:i')->placeholder('لم يُقبض بعد'),
                            Infolists\Components\TextEntry::make('paymentConfirmedBy.name')
                                ->label('اعتمده')->placeholder('—'),
                            Infolists\Components\IconEntry::make('stamped_at')
                                ->label('الختم الذهبي — تم التسليم')
                                ->boolean()
                                ->trueIcon('heroicon-o-sparkles')
                                ->falseIcon('heroicon-o-x-circle')
                                ->trueColor('warning')
                                ->falseColor('gray'),
                            Infolists\Components\TextEntry::make('stamped_at')
                                ->label('تاريخ التسليم')->dateTime('Y/m/d — H:i')->placeholder('لم يُسلَّم بعد'),
                            Infolists\Components\TextEntry::make('stampedBy.name')
                                ->label('سلّمه')->placeholder('—'),
                        ])->columns(3),
                    ]),

                    // ── المنتجات ──
                    Infolists\Components\Tabs\Tab::make('المنتجات')->schema([
                        Infolists\Components\RepeatableEntry::make('items')->label('')->schema([
                            Infolists\Components\TextEntry::make('displayName')
                                ->label('المنتج')->state(fn ($record) => $record->displayName()),
                            Infolists\Components\TextEntry::make('quantity')->label('الكمية')->badge(),
                            Infolists\Components\TextEntry::make('unit_price_usd')->label('سعر الوحدة')->money('USD'),
                            Infolists\Components\TextEntry::make('total_price_usd')->label('المجموع')
                                ->money('USD')->weight(FontWeight::Bold),
                            Infolists\Components\IconEntry::make('is_wholesale')->label('جملة')->boolean(),
                        ])->columns(5)->grid(1),
                    ]),

                    // ── الدفع والإثبات ──
                    Infolists\Components\Tabs\Tab::make('الدفع والإثبات')->schema([
                        Infolists\Components\TextEntry::make('paymentMethod.name')->label('وسيلة الدفع')->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment_reference')->label('رقم الحوالة')
                            ->placeholder('—')->copyable()->badge()->color('success'),
                        Infolists\Components\TextEntry::make('payment_sender_name')->label('اسم مرسل الحوالة')->placeholder('—'),
                        Infolists\Components\ImageEntry::make('payment_proof_path')
                            ->label('صورة إثبات الدفع')->disk('public')->height(240)
                            ->placeholder('— لا يوجد إثبات مرفوع')->columnSpanFull(),
                    ])->columns(2),

                    // ── التوصيل ──
                    Infolists\Components\Tabs\Tab::make('التوصيل')->schema([
                        Infolists\Components\TextEntry::make('shipping_method')->label('الاستلام')
                            ->formatStateUsing(fn ($state) => $state === 'local' ? 'توصيل محلي' : 'استلام من المحل'),
                        Infolists\Components\TextEntry::make('city')->label('المدينة')->placeholder('—')->badge(),
                        Infolists\Components\TextEntry::make('shipping_address')->label('العنوان')->placeholder('—')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('notes')->label('ملاحظات العميل')->placeholder('—')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('user.name')->label('العميل'),
                        Infolists\Components\TextEntry::make('user.phone')->label('الهاتف')->copyable(),
                        Infolists\Components\TextEntry::make('user.email')->label('البريد'),
                    ])->columns(2),

                    // ── السجل ──
                    Infolists\Components\Tabs\Tab::make('السجل')->schema([
                        Infolists\Components\RepeatableEntry::make('statusHistory')->label('')->schema([
                            Infolists\Components\TextEntry::make('created_at')->label('التاريخ')->dateTime('Y/m/d — H:i'),
                            Infolists\Components\TextEntry::make('to_status')->label('الحالة')
                                ->formatStateUsing(fn ($state) => Order::statusLabel($state))->badge(),
                            Infolists\Components\TextEntry::make('note')->label('ملاحظة')->placeholder('—'),
                        ])->columns(3)->grid(1),
                    ]),
                ]),
            ]);
    }

    // ═══════════════════════════ الأزرار ═══════════════════════════

    /** «تم قبض الدفع» — الختم الأخضر + نقل الحالة إلى «قيد التحضير» */
    public static function confirmPaymentAction(bool $forTable = true): \Filament\Actions\Action|\Filament\Tables\Actions\Action
    {
        $class = $forTable ? \Filament\Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return $class::make('confirmPayment')
            ->label('تم قبض الدفع')
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->visible(fn (Order $record) => ! $record->isPaid() && $record->status !== 'cancelled')
            ->requiresConfirmation()
            ->modalHeading('تأكيد قبض الدفع')
            ->modalDescription('يُثبَّت الختم الأخضر على الفاتورة باسمك وتاريخ اللحظة، وتنتقل الحالة إلى «قيد التحضير»، وتصدر الفاتورة جاهزة للطباعة أو الإرسال.')
            ->modalSubmitActionLabel('تأكيد القبض')
            ->action(function (Order $record) {
                self::markPaymentConfirmed($record);

                Notification::make()
                    ->title('تم قبض دفع الطلب '.$record->order_code)
                    ->body('ثُبّت الختم الأخضر وانتقل الطلب إلى «قيد التحضير».')
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('openInvoice')
                            ->label('فتح الفاتورة')
                            ->url(route('admin.invoice', $record))
                            ->openUrlInNewTab()
                            ->button(),
                    ])
                    ->send();
            });
    }

    /** «تم تسليم الطلب» — الختم الذهبي + الحالة «تم التسليم» + إغلاق */
    public static function deliverAction(bool $forTable = true): \Filament\Actions\Action|\Filament\Tables\Actions\Action
    {
        $class = $forTable ? \Filament\Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return $class::make('deliver')
            ->label('تم تسليم الطلب')
            ->icon('heroicon-m-sparkles')
            ->color('warning')
            ->visible(fn (Order $record) => $record->canBeDelivered())
            ->requiresConfirmation()
            ->modalHeading('تأكيد تسليم الطلب')
            ->modalDescription(fn (Order $record) => $record->isPaid()
                ? 'يُثبَّت الختم الذهبي، وتنتقل الحالة إلى «تم التسليم»، ويُغلق الطلب بكل إجراءاته.'
                : ($record->requiresPaymentBeforeDelivery()
                    ? '⚠️ لم يُقبض دفع هذا الطلب بعد. يجب تأكيد القبض أولًا قبل التسليم.'
                    : 'الطلب غير مقبوض، ومرحلة القبض مضبوطة على «غير مشروط» — سيُسلَّم ويُثبَّت الختم الذهبي.'))
            ->modalSubmitActionLabel('تأكيد التسليم')
            ->action(function (Order $record) {
                // حماية محاسبية: لا تسليم موثّق بلا مال إلا إن كان الإعداد يسمح
                if (! $record->isPaid() && $record->requiresPaymentBeforeDelivery()) {
                    Notification::make()
                        ->title('لا يمكن التسليم قبل قبض الدفع')
                        ->body('اضغط «تم قبض الدفع» أولًا، أو غيّر إعداد مرحلة القبض إلى «غير مشروط» من الإعدادات.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                self::markDelivered($record);

                Notification::make()
                    ->title('تم تسليم الطلب '.$record->order_code)
                    ->body('ثُبّت الختم الذهبي وأُغلق الطلب. افتح الفاتورة لإرسالها للعميل.')
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('openInvoice')
                            ->label('فتح الفاتورة')
                            ->url(route('admin.invoice', $record))
                            ->openUrlInNewTab()
                            ->button(),
                    ])
                    ->send();
            });
    }

    /** «إلغاء الطلب» — سبب إلزامي + إرجاع الكميات للمخزون */
    public static function cancelAction(bool $forTable = true): \Filament\Actions\Action|\Filament\Tables\Actions\Action
    {
        $class = $forTable ? \Filament\Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return $class::make('cancelOrder')
            ->label('إلغاء الطلب')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->visible(fn (Order $record) => $record->canBeCancelled())
            ->form([
                Forms\Components\Select::make('reason')
                    ->label('سبب الإلغاء')
                    ->options([
                        'customer_request' => 'طلب العميل الإلغاء',
                        'no_answer' => 'العميل لا يرد',
                        'out_of_stock' => 'نفاد المخزون',
                        'payment_failed' => 'تعذّر قبض الدفع',
                        'duplicate' => 'طلب مكرر',
                        'other' => 'سبب آخر',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('note')
                    ->label('تفصيل السبب')
                    ->rows(2)
                    ->maxLength(300)
                    // requiredIf ليست دالة في Filament — الشرط يُكتب كـclosure
                    ->required(fn (Forms\Get $get) => $get('reason') === 'other'),
            ])
            ->modalHeading('إلغاء الطلب')
            ->modalDescription('تُرجَع كميات المنتجات للمخزون تلقائيًا، ويُسجَّل السبب في سجل الطلب.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->action(function (Order $record, array $data) {
                $reason = [
                    'customer_request' => 'طلب العميل الإلغاء',
                    'no_answer' => 'العميل لا يرد',
                    'out_of_stock' => 'نفاد المخزون',
                    'payment_failed' => 'تعذّر قبض الدفع',
                    'duplicate' => 'طلب مكرر',
                    'other' => 'سبب آخر',
                ][$data['reason']] ?? $data['reason'];

                $note = trim($reason.($data['note'] ? ' — '.$data['note'] : ''));

                try {
                    DB::transaction(function () use ($record, $note) {
                        foreach ($record->items()->with('variant')->get() as $item) {
                            if (! $item->variant) {
                                continue;
                            }

                            \App\Services\StockService::record(
                                $item->variant->id,
                                'return',
                                $item->quantity,
                                $record->id,
                                null,
                                'إرجاع عند إلغاء الطلب '.$record->order_code.' — '.$note,
                            );
                        }

                        $record->statusHistory()->create([
                            'from_status' => $record->status,
                            'to_status' => 'cancelled',
                            'note' => $note,
                            'user_id' => auth()->id(),
                            'created_at' => now(),
                        ]);

                        $record->update(['status' => 'cancelled']);
                    });
                } catch (InsufficientStockException $e) {
                    Notification::make()->title('تعذّر الإلغاء — خطأ في المخزون')->body($e->getMessage())->danger()->send();

                    return;
                }

                try {
                    \App\Services\TelegramService::sendOrderStatus($record->refresh());
                } catch (\Throwable $e) {
                    report($e);
                }

                Notification::make()->title('أُلغي الطلب '.$record->order_code)->body($note)->success()->send();
            });
    }

    /** «تغيير الحالة» — للانتقالات اليدوية (الشحن مثلًا) */
    public static function changeStatusAction(bool $forTable = true): \Filament\Actions\Action|\Filament\Tables\Actions\Action
    {
        $class = $forTable ? \Filament\Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return $class::make('changeStatus')
            ->label('تغيير الحالة')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('الحالة الجديدة')
                    ->options(collect(Order::STATUSES)->mapWithKeys(fn ($s, $k) => [$k => $s['ar']]))
                    ->required(),
                Forms\Components\Textarea::make('note')->label('ملاحظة (اختياري)')->maxLength(300),
            ])
            ->visible(fn (Order $record) => ! in_array($record->status, ['delivered', 'cancelled'], true))
            ->action(function (Order $record, array $data) {
                $from = $record->status;
                $to = $data['status'];

                if ($from === $to) {
                    Notification::make()->title('الطلب على هذه الحالة أصلًا')->info()->send();

                    return;
                }

                // التسليم والإلغاء لهما زرّاهما الخاصان — يمنع تجاوز الحمايات
                if (in_array($to, ['delivered', 'cancelled'], true)) {
                    Notification::make()
                        ->title('استخدم الزر المخصّص')
                        ->body($to === 'delivered'
                            ? 'للتسليم استخدم زر «تم تسليم الطلب» — يثبّت الختم الذهبي ويتحقق من القبض.'
                            : 'للإلغاء استخدم زر «إلغاء الطلب» — يطلب سببًا ويرجّع الكميات.')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    DB::transaction(function () use ($record, $from, $to, $data) {
                        $record->statusHistory()->create([
                            'from_status' => $from,
                            'to_status' => $to,
                            'note' => $data['note'] ?? null,
                            'user_id' => auth()->id(),
                            'created_at' => now(),
                        ]);

                        $record->update(['status' => $to]);
                    });
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()->title('تعذّر تغيير الحالة')->danger()->send();

                    return;
                }

                try {
                    \App\Services\TelegramService::sendOrderStatus($record->refresh());
                } catch (\Throwable $e) {
                    report($e);
                }

                Notification::make()->title('حالة الطلب '.$record->order_code.' → '.Order::statusLabel($to))->success()->send();
            });
    }

    public static function invoiceAction(bool $forTable = true): \Filament\Actions\Action|\Filament\Tables\Actions\Action
    {
        $class = $forTable ? \Filament\Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return $class::make('invoice')
            ->label('الفاتورة')
            ->icon('heroicon-m-printer')
            ->color('gray')
            ->url(fn (Order $record) => route('admin.invoice', $record))
            ->openUrlInNewTab();
    }

    /**
     * «+ طلب يدوي» — إنشاء طلب من اللوحة للطلبات الهاتفية وزوار المحل.
     *
     * هذا هو الزر الوحيد في شريط أدوات النموذج الذي لم يكن منفّذًا. والمنطق
     * كله في ManualOrderService، فلا يُكرَّر منطق الحساب أو خصم المخزون هنا.
     */
    public static function manualOrderAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('manualOrder')
            ->label('+ طلب يدوي')
            ->icon('heroicon-m-plus')
            ->color('primary')
            ->modalWidth('5xl')
            ->modalHeading('إنشاء طلب يدوي')
            ->modalDescription('للطلبات الهاتفية وزوار المحل. تُخصم الكميات من المخزون وتُسجَّل حركة بيع في السجل، ويُحسب الإجمالي بسعر الصرف الحالي — تمامًا كطلب الموقع.')
            ->modalSubmitActionLabel('إنشاء الطلب')
            ->form([
                Forms\Components\Section::make('العميل')
                    ->description('كل الحقول هنا اختيارية. اتركها فارغة للبيع النقدي المباشر — يُسجَّل الطلب على حساب «عميل نقدي».')
                    ->schema([
                        Forms\Components\ToggleButtons::make('customer_mode')
                            ->label('نوع العميل')
                            ->options([
                                'new' => 'عميل جديد',
                                'existing' => 'عميل مسجّل',
                            ])
                            ->default('new')
                            ->inline()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('user_id')
                            ->label('اختر العميل')
                            ->searchable()
                            ->placeholder('ابحث بالاسم أو الهاتف أو البريد…')
                            ->required(fn (Forms\Get $get) => $get('customer_mode') === 'existing')
                            ->visible(fn (Forms\Get $get) => $get('customer_mode') === 'existing')
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%"))
                                ->orderBy('name')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (User $u) => [$u->id => self::customerOptionLabel($u)])
                                ->all())
                            ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? self::customerOptionLabel($u) : null)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('اسم العميل')
                            ->maxLength(100)
                            ->visible(fn (Forms\Get $get) => $get('customer_mode') === 'new')
                            ->helperText('اتركه فارغًا للبيع النقدي.'),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('الهاتف')
                            ->tel()
                            ->maxLength(30)
                            ->visible(fn (Forms\Get $get) => $get('customer_mode') === 'new')
                            ->helperText('إن كان الرقم مسجّلًا يُربط الطلب بحسابه بدل إنشاء حساب مكرّر.'),

                        Forms\Components\TextInput::make('customer_email')
                            ->label('البريد (اختياري)')
                            ->email()
                            ->maxLength(150)
                            ->visible(fn (Forms\Get $get) => $get('customer_mode') === 'new')
                            ->helperText('إن كان البريد مسجّلًا يُربط الطلب بحسابه — ولا يُنشأ حساب مكرّر.'),
                    ])->columns(3),

                Forms\Components\Section::make('المنتجات')->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->addActionLabel('+ أضف منتجًا')
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->collapsible(false)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->schema([
                            Forms\Components\Select::make('variant_id')
                                ->label('المنتج')
                                ->searchable()
                                ->required()
                                ->live()
                                ->placeholder('ابحث باسم المنتج أو اللون أو المقاس…')
                                ->getSearchResultsUsing(fn (string $search) => ProductVariant::query()
                                    ->with('product')
                                    ->where(fn ($q) => $q
                                        ->where('sku', 'like', "%{$search}%")
                                        ->orWhere('color', 'like', "%{$search}%")
                                        ->orWhere('size', 'like', "%{$search}%")
                                        ->orWhereHas('product', fn ($p) => $p
                                            ->where('name_ar', 'like', "%{$search}%")
                                            ->orWhere('name_en', 'like', "%{$search}%")
                                            ->orWhere('internal_code', 'like', "%{$search}%")))
                                    ->limit(30)
                                    ->get()
                                    ->mapWithKeys(fn (ProductVariant $v) => [$v->id => $v->optionLabel()])
                                    ->all())
                                ->getOptionLabelUsing(fn ($value) => ($v = ProductVariant::with('product')->find($value))
                                    ? $v->optionLabel()
                                    : null)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('quantity')
                                ->label('الكمية')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->live(onBlur: true),

                            Forms\Components\TextInput::make('unit_price_usd')
                                ->label('سعر الوحدة $')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('سعر المنتج')
                                ->helperText('اتركه فارغًا ليُؤخذ سعر المنتج، أو اكتب السعر المتفاوض عليه.'),

                            Forms\Components\Toggle::make('is_wholesale')
                                ->label('سعر جملة')
                                ->live(),

                            Forms\Components\Placeholder::make('line_preview')
                                ->label('المجموع المتوقع')
                                ->content(function (Forms\Get $get): string {
                                    $variant = ProductVariant::with('product')->find($get('variant_id'));

                                    if (! $variant?->product) {
                                        return '— اختر منتجًا';
                                    }

                                    $qty = max(1, (int) ($get('quantity') ?: 1));
                                    $override = $get('unit_price_usd');
                                    $unit = ($override === null || $override === '')
                                        ? (float) $variant->product->unitPriceUsd((bool) $get('is_wholesale'))
                                        : (float) $override;

                                    $available = (int) $variant->quantity;
                                    $short = $qty > $available;

                                    return fmt_usd($unit).' × '.$qty.' = '.fmt_usd($unit * $qty)
                                        .($short ? '  ⚠️ المتوفر '.$available.' فقط' : '');
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),
                ])->collapsible(),

                Forms\Components\Section::make('التسليم والدفع')->schema([
                    Forms\Components\ToggleButtons::make('shipping_method')
                        ->label('الاستلام')
                        ->options([
                            'pickup' => 'استلام من المحل',
                            'local' => 'توصيل محلي',
                        ])
                        ->default('pickup')
                        ->inline()
                        ->live(),

                    Forms\Components\TextInput::make('shipping_usd')
                        ->label('أجرة التوصيل $')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn (Forms\Get $get) => $get('shipping_method') === 'local')
                        ->helperText('الاستلام من المحل بلا أجرة تلقائيًا.'),

                    Forms\Components\TextInput::make('city')
                        ->label('المدينة')
                        ->maxLength(80)
                        ->visible(fn (Forms\Get $get) => $get('shipping_method') === 'local'),

                    Forms\Components\Textarea::make('shipping_address')
                        ->label('العنوان')
                        ->rows(2)
                        ->maxLength(300)
                        ->visible(fn (Forms\Get $get) => $get('shipping_method') === 'local')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('discount_usd')
                        ->label('خصم $')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),

                    Forms\Components\Select::make('payment_method_id')
                        ->label('وسيلة الدفع')
                        ->options(fn () => \App\Models\PaymentMethod::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),

                    Forms\Components\TextInput::make('payment_reference')
                        ->label('رقم الحوالة')
                        ->maxLength(80),

                    Forms\Components\TextInput::make('payment_sender_name')
                        ->label('اسم مرسل الحوالة')
                        ->maxLength(120),

                    Forms\Components\Toggle::make('confirm_payment')
                        ->label('قبض الدفع الآن (الختم الأخضر)')
                        ->helperText('للعميل الذي دفع في المحل: يُثبَّت الختم الأخضر باسمك، وينتقل الطلب إلى «قيد التحضير» فورًا.')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])->columns(3),
            ])
            ->action(function (array $data) {
                try {
                    $order = \App\Services\ManualOrderService::place($data);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    Notification::make()
                        ->title('تعذّر إنشاء الطلب')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('أُنشئ الطلب '.$order->order_code)
                    ->body(
                        'العميل: '.$order->customerName()
                        .' · الإجمالي: '.fmt_syp($order->total_syp)
                        .' ('.fmt_usd($order->total_usd).')'
                        .($order->isPaid() ? ' · مقبوض — الختم الأخضر ✓' : '')
                    )
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('openOrder')
                            ->label('فتح الطلب')
                            ->url(self::getUrl('view', ['record' => $order]))
                            ->button(),
                        \Filament\Notifications\Actions\Action::make('openInvoice')
                            ->label('الفاتورة')
                            ->url(route('admin.invoice', $order))
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            });
    }

    /** تسمية العميل في قائمة الاختيار: الاسم — الهاتف أو البريد */
    private static function customerOptionLabel(User $user): string
    {
        $contact = $user->phone ?: $user->email;

        return $contact ? "{$user->name} — {$contact}" : $user->name;
    }

    // ═══════════════ ألوان الحالة — من النموذج المعتمد ═══════════════
    // نفس القيم المستخدمة في لوحة التحكم (filament/pages/dashboard.blade.php)
    // وفي النموذج NADAF-Orders-Concept.html، فلا تظهر الحالة بلونين مختلفين.

    private const TONE_OK = '#7FD3A2';

    private const TONE_CHAMP = '#EAC97F';

    private const TONE_DIM = '#8A93A3';

    private static function statusTone(string $status): string
    {
        return match ($status) {
            'pending' => '#E8B98A',
            'preparing' => '#EAC97F',
            'shipped' => '#9DC0DC',
            'delivered' => '#7FD3A2',
            'cancelled' => '#E9A3B2',
            default => self::TONE_DIM,
        };
    }

    /** نقطة ملوّنة + النص — كما في عمود «الحالة» بالنموذج */
    private static function dotHtml(string $label, string $color): string
    {
        return '<span style="display:inline-flex;align-items:center;gap:7px;color:'.$color.'">'
            .'<i style="display:inline-block;width:7px;height:7px;border-radius:50%;background:currentColor;flex:none"></i>'
            .e($label).'</span>';
    }

    /** علامة نصّية ملوّنة — كما في عمودَي «الدفع» و«التوثيق» بالنموذج */
    private static function markHtml(string $text, string $color): string
    {
        return '<span style="font-size:11.5px;color:'.$color.'">'.e($text).'</span>';
    }

    // ═══════════════════════════ الأختام ═══════════════════════════

    /** تثبيت الختم الأخضر — قبض المال، ونقل الحالة إلى «قيد التحضير» */
    public static function markPaymentConfirmed(Order $record): void
    {
        if ($record->isPaid() || $record->status === 'cancelled') {
            return;
        }

        DB::transaction(function () use ($record) {
            $from = $record->status;

            $record->update([
                'payment_confirmed_at' => now(),
                'payment_confirmed_by' => auth()->id(),
            ]);

            // «قيد المراجعة» تنتقل إلى «قيد التحضير» تلقائيًا بعد القبض
            $to = $from === 'pending' ? 'preparing' : $from;

            if ($to !== $from) {
                $record->update(['status' => $to]);
            }

            $record->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => 'تم قبض الدفع (الختم الأخضر) بواسطة '.auth()->user()->name,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    /** تثبيت الختم الذهبي — تسليم الطلب وإغلاق إجراءاته */
    public static function markDelivered(Order $record): void
    {
        if ($record->isDelivered()) {
            return;
        }

        DB::transaction(function () use ($record) {
            $from = $record->status;

            $record->update([
                'stamped_at' => now(),
                'stamped_by' => auth()->id(),
                'status' => 'delivered',
            ]);

            $record->statusHistory()->create([
                'from_status' => $from,
                'to_status' => 'delivered',
                'note' => 'تم تسليم الطلب (الختم الذهبي) بواسطة '.auth()->user()->name,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    // ═══════════════════════════ الجدول ═══════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_code')
                    ->label('الكود')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('العميل')
                    ->searchable()
                    ->description(fn (Order $record) => $record->user?->phone),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('القطع')
                    ->counts('items')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_syp')
                    ->label('الإجمالي')
                    ->numeric()
                    ->sortable()
                    ->description(fn (Order $record) => fmt_usd($record->total_usd)),

                // الحالة: نقطة ملوّنة + النص — كما في النموذج (لا شارة مصمتة).
                // والألوان هي ألوان النموذج نفسها المستخدمة في لوحة التحكم،
                // فلا تظهر الحالة بلونين مختلفين في صفحتين من نفس اللوحة.
                // الحالة: نقطة ملوّنة + النص — كما في النموذج (لا شارة مصمتة).
                // والألوان هي ألوان النموذج نفسها المستخدمة في لوحة التحكم،
                // فلا تظهر الحالة بلونين مختلفين في صفحتين من نفس اللوحة.
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->html()
                    ->formatStateUsing(fn ($state) => self::dotHtml(Order::statusLabel($state), self::statusTone($state)))
                    ->sortable(),

                // عمود القبض — لا اسم الوسيلة. هذا ما يهم محاسبيًا.
                Tables\Columns\TextColumn::make('payment_confirmed_at')
                    ->label('الدفع')
                    ->html()
                    ->formatStateUsing(fn ($state) => $state
                        ? self::markHtml('✓ مقبوض', self::TONE_OK)
                        : self::markHtml('○ غير مقبوض', self::TONE_DIM)),

                // التوثيق: يبيّن **أي** ختم ثُبّت — أخضر بعد القبض، وذهبي بعد التسليم.
                // كان يعرض الذهبي وحده، فيظهر «—» على طلب مقبوض موثّق بالختم الأخضر.
                Tables\Columns\TextColumn::make('stamped_at')
                    ->label('التوثيق')
                    ->html()
                    ->formatStateUsing(fn ($state, Order $record) => match ($record->stampState()) {
                        'gold' => self::markHtml('◆ ذهبي', self::TONE_CHAMP),
                        'green' => self::markHtml('✓ أخضر', self::TONE_OK),
                        default => self::markHtml('—', self::TONE_DIM),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d/m · H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(collect(Order::STATUSES)->mapWithKeys(fn ($s, $k) => [$k => $s['ar']])),

                Tables\Filters\TernaryFilter::make('payment_confirmed_at')
                    ->label('الدفع')
                    ->placeholder('الكل')
                    ->trueLabel('مقبوض')
                    ->falseLabel('غير مقبوض')
                    ->nullable(),

                Tables\Filters\TernaryFilter::make('stamped_at')
                    ->label('التوثيق')
                    ->placeholder('الكل')
                    ->trueLabel('مُسلَّم')
                    ->falseLabel('لم يُسلَّم')
                    ->nullable(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $out = [];
                        if ($data['from'] ?? null) {
                            $out[] = Tables\Filters\Indicator::make('من: '.$data['from']);
                        }
                        if ($data['until'] ?? null) {
                            $out[] = Tables\Filters\Indicator::make('إلى: '.$data['until']);
                        }

                        return $out;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
                self::confirmPaymentAction(),
                self::deliverAction(),
                self::invoiceAction(),
                Tables\Actions\ActionGroup::make([
                    self::changeStatusAction(),
                    self::cancelAction(),
                    Tables\Actions\Action::make('archive')
                        ->label(fn (Order $record) => $record->archived_at ? 'إلغاء الأرشفة' : 'أرشفة')
                        ->icon('heroicon-m-archive-box')
                        ->color(fn (Order $record) => $record->archived_at ? 'gray' : 'warning')
                        ->visible(fn (Order $record) => in_array($record->status, ['delivered', 'cancelled'], true))
                        ->requiresConfirmation(fn (Order $record) => ! $record->archived_at)
                        ->action(function (Order $record) {
                            $record->update(['archived_at' => $record->archived_at ? null : now()]);
                            Notification::make()
                                ->title($record->archived_at ? 'أُرشف الطلب' : 'أُعيد الطلب للقائمة النشطة')
                                ->success()->send();
                        }),
                ])->label('المزيد'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->whereNull('archived_at'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
