<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan';
    protected static ?string $navigationGroup = 'Sistem';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Resto')
                    ->schema([
                        Forms\Components\TextInput::make('restaurant_name')
                            ->label('Nama resto')
                            ->required()
                            ->maxLength(100),
                    ]),
                Forms\Components\Section::make('Pajak & Service')
                    ->description('Dihitung dari subtotal setiap order. Isi 0 bila tidak ada.')
                    ->schema([
                        Forms\Components\TextInput::make('tax_percent')
                            ->label('Pajak (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('service_percent')
                            ->label('Service (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->suffix('%'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Pembayaran manual di kasir')
                    ->schema([
                        Forms\Components\Toggle::make('accept_cash')
                            ->label('Terima tunai'),
                        Forms\Components\Toggle::make('accept_qris')
                            ->label('Terima QRIS (manual)'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Tampilan Menu')
                    ->description('Tampilan default menu customer. Toggle per item di halaman Item Menu mengalahkan default ini.')
                    ->schema([
                        Forms\Components\Select::make('menu_layout')
                            ->label('Layout menu')
                            ->options([
                                'grid' => 'Grid (2 kolom)',
                                'list' => 'List (1 kolom)',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('menu_default_show_image')
                            ->label('Tampilkan foto (default)'),
                        Forms\Components\Toggle::make('menu_default_show_description')
                            ->label('Tampilkan deskripsi (default)'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Payment Gateway')
                    ->description('Customer bisa bayar sendiri dari HP di meja lewat QRIS / VA / e-wallet.')
                    ->schema([
                        Forms\Components\Placeholder::make('gateway_status')
                            ->label('Gateway aktif')
                            ->content(function () {
                                $provider = PaymentGateway::usable();

                                return $provider
                                    ? $provider->label().' ('.(PaymentGateway::isProduction($provider) ? 'PRODUKSI' : 'Sandbox').') — customer bisa bayar lewat tombol "Bayar Sekarang".'
                                    : 'Tidak ada gateway aktif — customer diarahkan "Bayar di kasir" dan aplikasi tetap jalan normal. Atur di halaman Pengaturan Gateway (menu Sistem).';
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::current()->update($data);

        Notification::make()
            ->title('Pengaturan disimpan.')
            ->success()
            ->send();
    }
}
