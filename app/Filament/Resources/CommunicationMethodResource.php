<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationMethodResource\Pages;
use App\Models\CommunicationMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationMethodResource extends Resource
{
    protected static ?string $model = CommunicationMethod::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string { return 'وسائل التواصل'; }
    public static function getModelLabel(): string { return 'وسيلة تواصل'; }
    public static function getPluralModelLabel(): string { return 'وسائل التواصل'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('النوع')
                ->options(collect(CommunicationMethod::TYPES)->mapWithKeys(fn ($t, $k) => [$k => $t['ar']]))
                ->required()
                ->live(),
            Forms\Components\TextInput::make('label')->label('تسمية (اختياري)')->maxLength(100),
            Forms\Components\TextInput::make('value')
                ->label('القيمة (رقم أو معرف أو رابط)')
                ->required()
                ->maxLength(255)
                ->helperText('مثال واتساب: +963999123456 — تيليجرام: username — ماسنجر: pagename — بريد: mail@x.com'),
            Forms\Components\Toggle::make('is_active')->label('مفعلة')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn ($state) => CommunicationMethod::TYPES[$state]['ar'] ?? $state),
                Tables\Columns\TextColumn::make('label')->label('التسمية')->placeholder('—'),
                Tables\Columns\TextColumn::make('value')->label('القيمة')->copyable(),
                Tables\Columns\ToggleColumn::make('is_active')->label('مفعلة'),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCommunicationMethods::route('/')];
    }
}
