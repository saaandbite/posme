<?php

namespace App\Filament\Pages;

use App\Models\DiningTable;
use Filament\Pages\Page;

class PrintQrCodes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Print QR Meja';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.print-qr-codes';

    public function getTables()
    {
        return DiningTable::where('is_active', true)->orderBy('name')->get();
    }
}
