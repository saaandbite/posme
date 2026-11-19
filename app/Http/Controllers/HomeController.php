<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Arahkan pengunjung di '/' ke halaman kerja sesuai role. */
class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        return redirect()->route(match ($user->role) {
            UserRole::Admin => 'filament.admin.pages.dashboard',
            UserRole::Cashier => 'cashier',
            UserRole::Kitchen => 'kitchen',
            default => 'login',
        });
    }
}
