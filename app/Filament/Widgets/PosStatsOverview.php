<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PosStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $todayPaid = Order::today()->where('payment_status', Order::PAYMENT_PAID);
        $todayAll = Order::today();

        return [
            Stat::make('Pendapatan hari ini', rupiah((int) $todayPaid->sum('total')))
                ->description($todayPaid->count().' order lunas')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Order hari ini', (string) $todayAll->count())
                ->description($todayAll->where('status', Order::STATUS_CANCELLED)->count().' dibatalkan')
                ->icon('heroicon-o-receipt-refund')
                ->color('info'),
            Stat::make('Order aktif', (string) Order::active()->count())
                ->description('Belum disajikan / dibatalkan')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Menunggu bayar', (string) Order::today()->where('payment_status', Order::PAYMENT_UNPAID)->count())
                ->description('Order hari ini yang belum lunas')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger'),
        ];
    }
}
