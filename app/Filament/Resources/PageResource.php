<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string { return 'الصفحات الثابتة'; }
    public static function getModelLabel(): string { return 'صفحة'; }
    public static function getPluralModelLabel(): string { return 'الصفحات الثابتة'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('slug')
                ->label('المعرف في الرابط')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(100)
                ->prefix('/page/'),
            Forms\Components\TextInput::make('title_ar')->label('العنوان بالعربية')->required()->maxLength(255),
            Forms\Components\TextInput::make('title_en')->label('العنوان بالإنجليزية')->required()->maxLength(255),
            Forms\Components\RichEditor::make('content_ar')
                ->label('المحتوى بالعربية (نص عام — استخدم الأقسام أدناه لصفحة «من نحن» الغنية)')
                ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                ->columnSpanFull(),
            Forms\Components\RichEditor::make('content_en')
                ->label('المحتوى بالإنجليزية')
                ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                ->columnSpanFull(),

            Forms\Components\Section::make('أقسام الصفحة — نص وصور وفيديو (لـ «من نحن»)')
                ->description('أضف أقسامًا لا نهائية: كل قسم عنوان + نص + صور متعددة + فيديوهات قصيرة (≤10 ثوانٍ). رتبها بالسحب')
                ->schema([
                    Forms\Components\Repeater::make('sections')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('layout')
                                ->label('شكل القسم')
                                ->options([
                                    'text' => 'نص فقط',
                                    'gallery' => 'معرض صور',
                                    'video' => 'فيديو',
                                    'split' => 'نص + صور (جانبًا لجانب)',
                                ])
                                ->default('text')
                                ->live(),
                            Forms\Components\TextInput::make('heading_ar')->label('عنوان القسم بالعربية')->maxLength(150),
                            Forms\Components\TextInput::make('heading_en')->label('عنوان القسم بالإنجليزية')->maxLength(150),
                            Forms\Components\Textarea::make('body_ar')->label('النص بالعربية')->rows(4)->columnSpanFull(),
                            Forms\Components\Textarea::make('body_en')->label('النص بالإنجليزية')->rows(4)->columnSpanFull(),
                            Forms\Components\FileUpload::make('images')
                                ->label('الصور (متعددة)')
                                ->disk('public')
                                ->directory('pages')
                                ->image()
                                ->imageEditor()
                                ->multiple()
                                ->reorderable()
                                ->maxFiles(12)
                                ->maxSize(4096)
                                ->columnSpanFull(),
                            Forms\Components\FileUpload::make('videos')
                                ->label('فيديوهات (كل واحدة ≤ 10 ثوانٍ)')
                                ->disk('public')
                                ->directory('pages')
                                ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                                ->multiple()
                                ->reorderable()
                                ->maxFiles(4)
                                ->maxSize(15360)
                                ->columnSpanFull()
                                ->rule(function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (! $value || ! is_string($value)) {
                                            return;
                                        }
                                        $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                                        if (! in_array($ext, ['mp4', 'webm', 'mov', 'm4v'])) {
                                            return;
                                        }
                                        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($value);
                                        if (is_file($fullPath) && media_duration($fullPath) > 10.5) {
                                            $fail('مدة الفيديو يجب أن تكون 10 ثوانٍ أو أقل.');
                                        }
                                    };
                                }),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => is_string($state['heading_ar'] ?? null) && $state['heading_ar'] !== '' ? $state['heading_ar'] : 'قسم بدون عنوان')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Toggle::make('is_active')->label('مفعلة')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->weight('bold'),
                Tables\Columns\TextColumn::make('slug')->label('المعرف')->badge(),
                Tables\Columns\ToggleColumn::make('is_active')->label('مفعلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePages::route('/')];
    }
}
