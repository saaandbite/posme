<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopMenuItems extends BaseWidget
{
    protected static ?string $heading = 'Item terlaris';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->selectRaw('menu_item_id as id, name, SUM(qty) as total_qty, SUM(subtotal) as total_sales')
                    ->whereHas('order', fn (Builder $q) => $q->where('status', '!=', 'cancelled'))
                    ->groupBy('menu_item_id', 'name')
                    ->orderByDesc('total_qty')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item'),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Terjual')
                    ->numeric(),
                Tables\Columns\TextColumn::make('total_sales')
                    ->label('Penjualan')
                    ->money('IDR'),
            ]);
    }
}
