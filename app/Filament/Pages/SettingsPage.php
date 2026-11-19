<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\MidtransService;
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
                Forms\Components\Section::make('Payment Gateway (Midtrans Snap)')
                    ->description('Customer bisa bayar sendiri dari HP di meja lewat QRIS / VA / e-wallet.')
                    ->schema([
                        Forms\Components\Placeholder::make('midtrans_status')
                            ->label('Status gateway')
                            ->content(fn () => MidtransService::configured()
                                ? 'Terhubung ('.(MidtransService::isProduction() ? 'PRODUKSI' : 'Sandbox').'). Customer bisa bayar lewat tombol "Bayar Sekarang".'
                                : 'BELUM DIKONFIGURASI — isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY di file .env, lalu restart aplikasi. Selama belum diisi, customer diarahkan "Bayar di kasir" dan aplikasi tetap jalan normal.'),
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
