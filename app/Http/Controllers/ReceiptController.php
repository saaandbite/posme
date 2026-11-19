<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(Order $order): View
    {
        return view('receipt', [
            'order' => $order->load(['items', 'diningTable']),
            'settings' => Setting::current(),
        ]);
    }
}
