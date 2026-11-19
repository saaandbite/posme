<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiningTableResource\Pages;
use App\Models\DiningTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DiningTableResource extends Resource
{
    protected static ?string $model = DiningTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Meja & QR Code';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama meja')
                    ->placeholder('Meja 1')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('code')
                    ->label('Kode (dipakai di URL QR)')
                    ->placeholder('Kosongkan untuk kode otomatis')
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->dehydrated(fn ($state) => filled($state)),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Meja')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Order hari ini')
                    ->counts(['orders' => fn ($query) => $query->today()]),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('qr')
                    ->label('Lihat QR')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading(fn (DiningTable $record) => 'QR Code — '.$record->name)
                    ->modalContent(fn (DiningTable $record) => view('filament.table-qr', ['table' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiningTables::route('/'),
            'create' => Pages\CreateDiningTable::route('/create'),
            'edit' => Pages\EditDiningTable::route('/{record}/edit'),
        ];
    }
}
