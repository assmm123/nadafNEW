<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatQuestionResource\Pages;
use App\Models\ChatQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChatQuestionResource extends Resource
{
    protected static ?string $model = ChatQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    /** قسم موحّد مع سجل المحادثات ودليل البوت */
    protected static ?string $navigationGroup = 'الدردشة والبوت';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return 'الشات العائم';
    }

    public static function getModelLabel(): string
    {
        return 'سؤال وأجوبة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الشات العائم — الأسئلة والأجوبة';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('question')
                ->label('السؤال (كما سأله الزائر)')
                ->required()
                ->maxLength(200),
            Forms\Components\Textarea::make('answer')
                ->label('الجواب الجاهز')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\TagsInput::make('keywords')
                ->label('الكلمات المفتاحية')
                ->placeholder('اكتب كلمة واضغط Enter')
                ->helperText('إذا وردت أي كلمة في سؤال الزائر سيعرض هذا الجواب فورًا — مثال: توصيل، شحن، دمشق'),
            Forms\Components\Toggle::make('is_active')
                ->label('مفعل')
                ->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            /*
             * الأسئلة المُجابة تُخفى كليًا — والقسم يبقى لإدخال سؤال جديد فقط.
             *
             * والمُرشِّح هنا لا في العمود: إخفاء العمود يُبقي الصفّ فارغًا في
             * الجدول، والمراد ألّا يظهر السؤال نفسه. فالقاعدة على الاستعلام.
             *
             * و`answer` إلزامي في النموذج، فكل سؤال محفوظ له جواب بالضرورة ⇒
             * القائمة تبقى فارغة **بحكم البناء** لا بحكم حادثة. ومن أراد لاحقًا
             * رؤية الأسئلة المُجابة فليطلب فتحها — لا تُستنتج.
             */
            ->modifyQueryUsing(fn ($query) => $query->where(
                fn ($q) => $q->whereNull('answer')->orWhere('answer', ''),
            ))
            ->columns([
                Tables\Columns\TextColumn::make('question')->label('السؤال')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('answer')->label('الجواب')->limit(60),
                Tables\Columns\TextColumn::make('keywords')->label('الكلمات المفتاحية')->badge()->separator(',')->limit(20),
                Tables\Columns\IconColumn::make('is_active')->label('مفعل')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('لا أسئلة بانتظار جواب')
            ->emptyStateDescription('كل سؤال له جواب يُخفى تلقائيًا. هذه الشاشة لإدخال سؤال جديد فقط — أضِفه من زر «سؤال وأجوبة» أعلاه.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatQuestions::route('/'),
            'create' => Pages\CreateChatQuestion::route('/create'),
            'edit' => Pages\EditChatQuestion::route('/{record}/edit'),
        ];
    }
}
