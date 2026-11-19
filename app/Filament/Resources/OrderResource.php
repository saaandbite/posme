<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationLabel = 'Order';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?int $navigationSort = 2;

    /** Order dibuat customer lewat QR — admin tidak membuat order manual. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cancelled_reason')
                    ->label('Alasan pembatalan')
                    ->maxLength(200),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('Nomor order'),
                        Infolists\Components\TextEntry::make('diningTable.name')
                            ->label('Meja'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                            ->color(fn (OrderStatus $state) => $state->color()),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Pembayaran')
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                            ->color(fn (PaymentStatus $state) => $state->color()),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Metode')
                            ->formatStateUsing(fn (?PaymentMethod $state) => $state?->label() ?? '—'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Waktu order')
                            ->dateTime('d M Y, H:i'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Item')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('qty')
                                    ->label('Jml'),
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Item'),
                                Infolists\Components\TextEntry::make('note')
                                    ->label('Catatan')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('price')
                                    ->label('Harga')
                                    ->money('IDR'),
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('IDR'),
                            ])
                            ->columns(5),
                    ]),
                Infolists\Components\Section::make('Ringkasan')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                        Infolists\Components\TextEntry::make('tax_amount')->label('Pajak')->money('IDR'),
                        Infolists\Components\TextEntry::make('service_amount')->label('Service')->money('IDR'),
                        Infolists\Components\TextEntry::make('total')->label('Total')->money('IDR'),
                        Infolists\Components\TextEntry::make('paid_amount')->label('Dibayar')->money('IDR'),
                        Infolists\Components\TextEntry::make('change_amount')->label('Kembalian')->money('IDR'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Nomor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('diningTable.name')
                    ->label('Meja')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Bayar')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status order')
                    ->options(OrderStatus::options()),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status bayar')
                    ->options(PaymentStatus::options()),
                Tables\Filters\Filter::make('created_at')
                    ->label('Periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['dari'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markPaidCash')
                    ->label('Lunas (Tunai)')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Order $record) => $record->canBePaid())
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->markPaidManually(PaymentMethod::Cash, $record->total)),
                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => $record->canBeCancelled())
                    ->form([
                        Forms\Components\TextInput::make('cancelled_reason')
                            ->label('Alasan pembatalan')
                            ->required()
                            ->maxLength(200),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update([
                            'status' => OrderStatus::Cancelled,
                            'cancelled_reason' => $data['cancelled_reason'],
                        ]);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
