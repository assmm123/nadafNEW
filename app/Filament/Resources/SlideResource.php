<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlideResource\Pages;
use App\Models\Slide;
use App\Support\UploadLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * السلايدر — الشريط الكبير أعلى الصفحة الرئيسية.
 *
 * الجولة الأولى عالجت ستّ ثغرات:
 *   ١ صورة غلاف للفيديو — بدونه تظهر شريحة سوداء أثناء التحميل
 *   ٢ نص الزر صار من اللوحة لا مكتوبًا في الكود
 *   ٣ سطر توضيحي تحت العنوان
 *   ٤ مدة عرض لكل شريحة لا ٦ ثوانٍ للجميع
 *   ٥ معاينة داخل النموذج
 *   ٦ لا تُحفظ شريحة بلا صورة ولا فيديو
 *
 * والجولة الثانية عالجت بلاغ المالك «فيديو بصوت حتى دقيقة، وزر تسوق الآن»:
 *   ٧ حدّ الرفع: ١٢ ميغا فعلية في PHP كانت ترفض فيديو الدقيقة. رُفعت إلى
 *      ‏١٢٨ ميغا في أربع طبقات (php.ini · public/.user.ini · Livewire · هنا)
 *   ٨ مدة الشريحة: كانت تقصّ عند ٣٠ ثانية فيُقطع الفيديو في منتصفه
 *   ٩ زر الصوت: الفيديو يبدأ صامتًا لأن المتصفحات تمنع غير ذلك، ومعه
 *      زر يشغّل الصوت بنقرة — فالصوت صار ممكنًا لا ممنوعًا
 *   ١٠ زر «تسوق الآن» صار يظهر على كل شريحة ويرث رابطًا افتراضيًا،
 *      وقاعدة الرابط الصارمة التي كانت ترفض `#categories` أُبدلت بتطبيع
 */
class SlideResource extends Resource
{
    protected static ?string $model = Slide::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 7;

    /**
     * حدّ رفع الفيديو — ١٢٨ ميغابايت.
     *
     * فيديو يصل إلى دقيقة يبلغ عادةً عشرات الميغابايت، وكان الحد السابق
     * (٢٠ ميغا في Filament، و١٢ ميغا فعلًا في PHP) يرفضه. وحدّ PHP هو
     * الحاكم فعلًا، فرُفع في php.ini و`public/.user.ini` إلى نفس القيمة
     * هنا، ووُحّد معه حدّ Livewire في `config/livewire.php`. لو اختلفت
     * هذه الأربعة لرفض أحدها ما سمح به الآخر — وهو أصل البلاغ.
     */
    public const MAX_UPLOAD_MB = 128;

    public const MAX_UPLOAD_KB = self::MAX_UPLOAD_MB * 1024;

    /**
     * تنبيه إن كان حدّ السيرفر الفعلي أقل من الحد الذي نطلبه.
     *
     * يُقرأ من `post_max_size` لأن الطلب كله يخضع له لا الملف وحده.
     * يعيد null حين كل شيء سليم فلا يزدحم النموذج بلا سبب.
     */
    public static function serverLimitsNotice(): ?string
    {
        if (! UploadLimits::isBelow(self::MAX_UPLOAD_KB * 1024)) {
            return null;
        }

        $readable = UploadLimits::human(UploadLimits::effectiveBytes());

        return "تنبيه: السيرفر يسمح حاليًا بـ{$readable} فقط للفيديو الواحد، وهو أقل من "
            .self::MAX_UPLOAD_MB.' ميغابايت التي يطلبها هذا الحقل. '
            .'ارفع الفيديو بجودة أقل، أو ارفع الحد في php.ini و`public/.user.ini`.';
    }

    public static function getNavigationLabel(): string
    {
        return 'السلايدر';
    }

    public static function getModelLabel(): string
    {
        return 'شريحة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'السلايدر';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الوسيط — صورة أو فيديو')
                ->description('الفيديو يبدأ تلقائيًا صامتًا (المتصفحات تمنع التشغيل التلقائي بالصوت) ومع الشريحة زر يشغّل الصوت بنقرة. وصورة الغلاف تُعرض قبل تحميله فلا يرى العميل شريحة سوداء.')
                ->schema([
                    Forms\Components\FileUpload::make('video_path')
                        ->label('الفيديو المرفوع (اختياري)')
                        ->disk('public')
                        ->directory('slides')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                        ->maxSize(self::MAX_UPLOAD_KB)
                        ->helperText('حتى دقيقة كاملة. الحد الأقصى '.self::MAX_UPLOAD_MB.' ميغابايت — وإن كان الفيديو أطول من مدة الشريحة فاضبط المدة في «العرض والترتيب».'),

                    Forms\Components\TextInput::make('youtube_url')
                        ->label('أو رابط يوتيوب (اختياري)')
                        ->maxLength(255)
                        ->placeholder('https://youtu.be/VIDEOID أو youtube.com/watch?v=...')
                        ->helperText('بديل الفيديو المرفوع — يُضمَّن داخل السلايدر ويُخدَّم من خوادم يوتيوب، فلا حدّ رفع ولا مساحة. يتجاهله النظام إن لم يكن رابط يوتيوب صالحًا.')
                        // رابط يوتيوب غير صالح لا يكسر الحفظ — لكن ننبه المالك
                        // صراحةً وإلا سيحفظ شريحة يظنها فيديو وهي صورة.
                        ->rule('nullable')
                        ->live(onBlur: true),

                    Forms\Components\Placeholder::make('youtube_hint')
                        ->label('')
                        ->content(function (Forms\Get $get) {
                            $value = trim((string) $get('youtube_url'));

                            if ($value === '') {
                                return null;
                            }

                            $id = Slide::extractYoutubeId($value);

                            return $id === null
                                ? 'تنبيه: لم يُتعرَّف على هذا كرابط يوتيوب صالح — لن يُعرض الفيديو. الصيغ المقبولة: youtu.be/xxx · youtube.com/watch?v=xxx · أو معرّف ١١ حرفًا.'
                                : 'مقبول — معرّف الفيديو: '.$id.'، سيُضمَّن في السلايدر ويُكرَّر تلقائيًا صامتًا.';
                        })
                        ->visible(fn (Forms\Get $get) => filled($get('youtube_url')))
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('image_path')
                        ->label('الصورة / صورة الغلاف')
                        ->disk('public')
                        ->directory('slides')
                        ->image()
                        ->maxSize(8192)
                        // بلا فيديو تصير الصورة إلزامية — فلا تُحفظ شريحة فارغة
                        ->required(fn (Forms\Get $get) => blank($get('video_path')))
                        ->helperText('إن رفعت فيديو فهذه صورة الغلاف (اختيارية لكن مُستحسنة). وإن لم ترفع فيديو فهي إلزامية.'),

                    Forms\Components\Placeholder::make('media_warning')
                        ->label('')
                        ->content('رفع الفيديو بلا صورة غلاف يجعل الشريحة تبدو سوداء أثناء التحميل — أضف صورة.')
                        ->visible(fn (Forms\Get $get) => filled($get('video_path')) && blank($get('image_path')))
                        ->columnSpanFull(),

                    // الحدّ الحقيقي يأتي من php.ini لا من هذا النموذج. فإن كانت
                    // الاستضافة تضبطه أقل مما نظن، يرى المالك السبب صراحةً
                    // بدل أن يفشل الرفع بلا تفسير.
                    Forms\Components\Placeholder::make('upload_limits')
                        ->label('')
                        ->content(fn () => self::serverLimitsNotice())
                        ->visible(fn () => self::serverLimitsNotice() !== null)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('النص الظاهر')
                ->description('العنوان يظهر كبيرًا فوق الوسيط، والسطر التوضيحي تحته.')
                ->schema([
                    Forms\Components\TextInput::make('title_ar')
                        ->label('العنوان بالعربية')
                        ->maxLength(255)
                        ->live(onBlur: true),

                    Forms\Components\TextInput::make('title_en')
                        ->label('العنوان بالإنجليزية (اختياري)')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('subtitle_ar')
                        ->label('السطر التوضيحي بالعربية')
                        ->maxLength(255)
                        ->placeholder('مثال: مجموعة جديدة وصلت — بأسعار الموسم'),

                    Forms\Components\TextInput::make('subtitle_en')
                        ->label('السطر التوضيحي بالإنجليزية (اختياري)')
                        ->maxLength(255),
                ])->columns(2)->collapsible(),

            Forms\Components\Section::make('الزر والرابط')
                ->description('الزر يظهر على كل شريحة — لا يحتاج رابطًا ليظهر. اكتب الرابط هنا لتحديد وجهته، وإن تركته فارغًا ذهب إلى الرابط الافتراضي في الإعدادات العامة.')
                ->schema([
                    Forms\Components\TextInput::make('link')
                        ->label('الرابط عند النقر (اختياري)')
                        ->maxLength(255)
                        // لا قاعدة صرامة هنا عمدًا. المُطبِّع في النموذج
                        // (Slide::normalizeLink) يقبل `/category/tie`
                        // و`#categories` و`www.nadaf.com` ويصحّحها. أما
                        // الرفض بالرسالة فقد كان يُصطدم بالمالك بلا فائدة —
                        // حتى إن القالب نفسه يستخدم `#categories`.
                        ->placeholder('/category/tie أو #categories أو www.nadaf.com')
                        ->helperText('تُقبل المسارات الداخلية والمراسي والنطاقات. النطاق المجرّد يُصحَّح إلى https تلقائيًا.'),

                    Forms\Components\TextInput::make('button_text_ar')
                        ->label('نص الزر بالعربية')
                        ->maxLength(60)
                        ->placeholder('تسوق الآن'),

                    Forms\Components\TextInput::make('button_text_en')
                        ->label('نص الزر بالإنجليزية (اختياري)')
                        ->maxLength(60),

                    Forms\Components\Placeholder::make('button_hint')
                        ->label('')
                        ->content('لم تكتب رابطًا — سيذهب الزر إلى الرابط الافتراضي: '
                            .setting('slide_default_link', Slide::DEFAULT_LINK)
                            .' (يمكن تغييره من الإعدادات العامة ← الصفحة الرئيسية).')
                        ->visible(fn (Forms\Get $get) => blank($get('link')))
                        ->columnSpanFull(),
                ])->columns(3)->collapsible()->collapsed(),

            Forms\Components\Section::make('العرض والترتيب')
                ->schema([
                    Forms\Components\TextInput::make('duration_seconds')
                        ->label('مدة بقاء الشريحة (ثانية)')
                        ->numeric()
                        ->integer()
                        ->minValue(Slide::MIN_DURATION)
                        ->maxValue(Slide::MAX_DURATION)
                        ->default(Slide::DEFAULT_DURATION)
                        ->suffix('ث')
                        ->helperText('من '.Slide::MIN_DURATION.' إلى '.Slide::MAX_DURATION.' ثانية. الافتراضي '.Slide::DEFAULT_DURATION.'. وإن كان الفيديو دقيقة فاجعل المدة ٦٠ حتى لا يُقطع قبل نهايته.'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->helperText('الأصغر يظهر أولًا. ويمكن السحب في الجدول.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعّلة — تظهر في المتجر')
                        ->default(true),
                ])->columns(3)->collapsible(),

            // ═══ المعاينة — تُرى في النموذج لا في المتجر ═══
            Forms\Components\Section::make('معاينة')
                ->description('هكذا تقريبًا ستظهر الشريحة في الصفحة الرئيسية.')
                ->schema([
                    Forms\Components\Placeholder::make('slide_preview')
                        ->label('')
                        ->content(fn (Forms\Get $get) => view('filament.slides.preview', [
                            'image' => $get('image_path'),
                            'video' => $get('video_path'),
                            'youtube' => $get('youtube_url'),
                            'title' => $get('title_ar'),
                            'subtitle' => $get('subtitle_ar'),
                            // الزر يظهر دائمًا — بلا رابط يرث الافتراضي
                            'button' => $get('button_text_ar') ?: 'تسوق الآن',
                            'href' => $get('link') ?: setting('slide_default_link', Slide::DEFAULT_LINK),
                        ]))
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->square()
                    ->height(46)
                    ->defaultImageUrl(fn (Slide $record) => $record->imageUrl()),

                Tables\Columns\TextColumn::make('title_ar')
                    ->label('العنوان')
                    ->limit(34)
                    ->placeholder('بلا عنوان')
                    ->description(fn (Slide $record) => $record->subtitle_ar),

                Tables\Columns\TextColumn::make('video_path')
                    ->label('الوسيط')
                    ->badge()
                    ->formatStateUsing(fn ($state, Slide $record) => $record->hasYoutube()
                        ? 'يوتيوب'
                        : ($state ? 'فيديو' : 'صورة'))
                    ->color(fn ($state, Slide $record) => $record->hasYoutube() ? 'warning' : ($state ? 'info' : 'gray')),

                Tables\Columns\IconColumn::make('link')
                    ->label('زر')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('المدة')
                    ->suffix(' ث')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّلة')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مفعّلة')
                    ->falseLabel('معطّلة'),

                Tables\Filters\Filter::make('video')
                    ->label('فيديو فقط')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereNotNull('video_path')->orWhereNotNull('youtube_url');
                    })),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('لا شرائح بعد')
            ->emptyStateDescription('السلايدر هو أول ما يراه العميل. أضف شريحة بصورة أو فيديو، ومعها عنوان.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSlides::route('/')];
    }
}
