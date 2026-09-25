<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function profile()
    {
        return view('account.profile', [
            'recentOrders' => auth()->user()->orders()->with('items')->latest()->take(5)->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(6)],
        ]);

        $user->name = $data['name'];
        $user->phone = $data['phone'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', __('account.updated'));
    }

    public function orders(Request $request)
    {
        // واجهة بوابة الكروت — Live Component مع عرض معزول بنفس الصفحة
        return view('account.orders-portal');
    }

    public function order(string $code)
    {
        $order = Order::where('order_code', $code)
            ->where('user_id', auth()->id())
            ->with(['items', 'paymentMethod', 'statusHistory'])
            ->firstOrFail();

        return view('account.order', ['order' => $order]);
    }

    /** فاتورة العميل — نفس الفاتورة النظامية، لطلبه فقط */
    public function invoice(string $code)
    {
        $order = Order::where('order_code', $code)
            ->where('user_id', auth()->id())
            ->with(['items', 'user', 'paymentMethod', 'stampedBy', 'paymentConfirmedBy'])
            ->firstOrFail();

        // واتساب هنا = التواصل مع المتجر حول الفاتورة (زر العميل)
        $waLink = whatsapp_inquiry_link("مرحبًا، بخصوص فاتورة الطلب {$order->order_code}:");
        $waLink .= '%0A'.rawurlencode('رقم الطلب: '.$order->order_code.' — الإجمالي: '.fmt_usd($order->total_usd));

        return view('admin.invoice', ['order' => $order, 'waLink' => $waLink]);
    }
}
