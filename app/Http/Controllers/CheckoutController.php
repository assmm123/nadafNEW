<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;

class CheckoutController extends Controller
{
    public function index()
    {
        return view('checkout', [
            'paymentMethods' => PaymentMethod::active()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * صفحة نجاح الطلب.
     *
     * الطلب قد يكون لحساب مسجَّل أو لضيف. فالحماية لا تصلح أن تكون
     * `where('user_id', auth()->id())` وحدها — فحصرها بالحساب يمنع الضيف من
     * رؤية طلبه، وفتحها بالرمز وحده يجعلها قابلة للتخمين.
     *
     * فالقاعدة: صاحب الحساب يراها، **أو** من أنشأ الطلب في هذه الجلسة.
     * ويُسجَّل رمز الطلب في الجلسة لحظة إنشائه (في مكوّن الدفع).
     */
    public function success(string $code)
    {
        $order = Order::where('order_code', $code)->with('items')->firstOrFail();

        $mine = auth()->check() && $order->user_id === auth()->id();
        $placedInThisSession = in_array($code, (array) session('placed_orders', []), true);

        abort_unless($mine || $placedInThisSession, 404);

        return view('order-success', ['order' => $order]);
    }
}
