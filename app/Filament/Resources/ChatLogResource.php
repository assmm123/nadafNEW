<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatLogResource\Pages;
use App\Models\ChatLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** سجل محادثات الشات العائم — للقراءة فقط */
class ChatLogResource extends Resource
{
    protected static ?string $model = ChatLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?int $navigationSort = 7;

    /** قسم موحّد مع الأسئلة والأجوبة ودليل البوت */
    protected static ?string $navigationGroup = 'الدردشة والبوت';

    public static function getNavigationLabel(): string
    {
        return 'سجل المحادثات';
    }

    public static function getModelLabel(): string
    {
        return 'محادثة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'سجل محادثات الشات';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('الوقت')->dateTime('Y/m/d H:i')->sortable(),
                Tables\Columns\TextColumn::make('visitor_name')->label('الزائر')->placeholder('زائر')->badge(),
                Tables\Columns\TextColumn::make('message')->label('رسالته')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('matched_answer')->label('الجواب المقدم')->limit(50)->placeholder('— لا جواب —'),
                Tables\Columns\IconColumn::make('was_helpful')->label('وجَب جوابًا')
                    ->boolean()->placeholder('—'),
                Tables\Columns\TextColumn::make('trigger')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'payment_error' ? 'عند فشل الدفع' : 'سؤال عادي')
                    ->color(fn ($state) => $state === 'payment_error' ? 'danger' : 'gray'),
            ])
            ->filters([
                // الافتراضي: «بانتظار إجابة» فقط — فتختفي الرسالة فور معالجتها
                // بزر «علّم الشات» أو «علّم مقروءًا»، ولا تُشوّش على قائمة العمل.
                Tables\Filters\TernaryFilter::make('was_helpful')
                    ->label('حالة الإجابة')
                    ->placeholder('الكل')
                    ->trueLabel('تمت الإجابة')
                    ->falseLabel('بانتظار إجابة')
                    ->default(false),
                Tables\Filters\SelectFilter::make('trigger')
                    ->label('المصدر')
                    ->options(['user' => 'سؤال عادي', 'payment_error' => 'عند فشل الدفع']),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('trainFromLog')
                    ->label('علّم الشات')
                    ->icon('heroicon-m-academic-cap')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->was_helpful)
                    ->modalHeading('تحويل الرسالة إلى سؤال جديد')
                    ->modalDescription('سيُنشأ سؤال جديد بنص رسالة الزائر — أكمل الجواب ثم احفظه ليتعلم الشات الإجابة فورًا.')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('question')
                            ->label('السؤال')
                            ->default(fn ($record) => $record->message)
                            ->required()
                            ->maxLength(200),
                        \Filament\Forms\Components\Textarea::make('answer')
                            ->label('الجواب الجاهز')
                            ->required()
                            ->rows(4),
                        \Filament\Forms\Components\TagsInput::make('keywords')
                            ->label('كلمات مفتاحية')
                            ->placeholder('مثال: توصيل، شحن')
                            ->suggestions(['توصيل', 'دفع', 'فاتورة', 'إرجاع', 'مقاس', 'سعر']),
                    ])
                    ->action(function ($record, array $data) {
                        \App\Models\ChatQuestion::create([
                            'question' => $data['question'],
                            'answer' => $data['answer'],
                            'keywords' => $data['keywords'],
                            'is_active' => true,
                            'sort_order' => (\App\Models\ChatQuestion::max('sort_order') ?: 0) + 1,
                        ]);
                        $record->update(['was_helpful' => true, 'matched_answer' => $data['answer']]);

                        \Filament\Notifications\Notification::make()
                            ->title('تعلم الشات جوابًا جديدًا ✓')
                            ->body('سيرد به فورًا على أي زائر يسأل بسؤال مشابه.')
                            ->success()
                            ->send();
                    }),
                \Filament\Tables\Actions\Action::make('markSeen')
                    ->label('علّم مقروءًا')
                    ->icon('heroicon-m-check')
                    ->color('gray')
                    ->visible(fn ($record) => $record->was_helpful === false)
                    ->action(fn ($record) => $record->update(['was_helpful' => true])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatLogs::route('/'),
        ];
    }
}
