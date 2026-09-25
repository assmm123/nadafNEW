<?php

namespace App\Services;

use App\Models\AbandonedCart;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * رصد السلات المتروكة — لقطة من السلة الفعلية عند كل تعديل،
 * ولقطة الطلب الفعلي تمسح السجل (تم الشراء).
 */
class AbandonedCartTracker
{
    private const KEY = 'abandoned_cart_session';

    public static function track(): void
    {
        $items = CartService::detailed();

        if ($items->isEmpty()) {
            self::forget();

            return;
        }

        $snapshot = $items->map(fn ($i) => [
            'name' => $i->product->name,
            'qty' => $i->qty,
            'price' => $i->unit_usd,
        ])->all();

        AbandonedCart::updateOrCreate(
            ['session_id' => self::sessionId()],
            [
                'user_id' => auth()->id(),
                'identifier' => auth()->user()?->email ?? auth()->user()?->phone,
                'items' => $snapshot,
                'total_usd' => $items->sum('line_usd'),
                'first_seen_at' => now(),
                'last_activity_at' => now(),
                'notified' => false,
            ]
        );
    }

    /** الشراء اكتمل أو السلة أُفرغت — أزل اللقطة */
    public static function forget(): void
    {
        AbandonedCart::where('session_id', self::sessionId())->delete();
    }

    private static function sessionId(): string
    {
        if (! Session::has(self::KEY)) {
            Session::put(self::KEY, (string) Str::uuid());
        }

        return Session::get(self::KEY);
    }
}
