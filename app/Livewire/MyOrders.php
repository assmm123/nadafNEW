<?php

namespace App\Livewire;

use App\Models\Order;
use Livewire\Component;

/**
 * «طلباتي» v2 — بوابة عميل:
 * 1) تتبع أحدث الطلبات فوق الكروت — الطلبيات التي بلغت التسليم تُعرض بعدها 24 ساعة
 *    (عداد ساعات تنازلي) ثم تُخفى من التتبع وتنتقل لكرت «المستلمة» مع فاتورتها.
 * 2) كرتان فقط: الطلبات المستلمة (شاملة ما بعد 24س) + الملغية.
 * 3) فتح أي كرت يعرض تفاصيل طلبياته كاملة.
 */
class MyOrders extends Component
{
    public ?string $openSection = null;

    public function openSection(string $key): void
    {
        $this->openSection = $key;
    }

    public function closeSection(): void
    {
        $this->openSection = null;
    }

    /** ساعة التسليم الفعلية (من سجل الحالات) — لحساب نافذة الـ24 ساعة */
    private function deliveredAt(Order $o): ?\Carbon\Carbon
    {
        return $o->statusHistory->firstWhere('to_status', 'delivered')?->created_at
            ?: ($o->status === 'delivered' ? $o->updated_at : null);
    }

    /** الطلبيات الحية في التتبع: كل ما ليس مكتملًا نهائيًا + المكتملة داخل نافذة 24 ساعة */
    public function getTrackingProperty(): array
    {
        return auth()->user()->orders()
            ->with(['paymentMethod', 'statusHistory', 'items'])
            ->latest()
            ->get()
            ->map(function (Order $o) {
                $delivered = $this->deliveredAt($o);
                // بلا 'confirmed' — الحالة حُذفت. القبض صار ختمًا لا مرحلة،
                // فلا يتغيّر مؤشر التقدّم عند العميل بمجرد قبض المال.
                $stageMap = ['pending' => 1, 'preparing' => 2, 'shipped' => 3, 'delivered' => 4];
                $stage = $stageMap[$o->status] ?? 1;
                $dead = $o->status === 'cancelled';
                $hideIn = null;

                if ($o->status === 'delivered' && $delivered) {
                    $hideAt = $delivered->copy()->addHours(24);
                    if (now()->gte($hideAt)) {
                        return null; // انتهت نافذة الـ24 ساعة → من قسم التتبع فقط (تبقى في كرت المستلمة)
                    }
                    $hideIn = (int) ceil(now()->diffInHours($hideAt, true));
                }

                return [
                    'model' => $o,
                    'code' => $o->order_code,
                    'date' => $o->created_at->format('Y/m/d'),
                    'stage' => $stage,
                    'dead' => $dead,
                    'paid' => (bool) $o->payment_confirmed_at,
                    'stamped' => (bool) $o->stamped_at,
                    'hideIn' => $hideIn,
                    'total' => fmt_usd($o->total_usd),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** كرت «المستلمة»: كل ما سُلّم (حتى ما انتهت نافذته من التتبع) — بفواتيرها */
    public function getDeliveredProperty(): array
    {
        return auth()->user()->orders()
            ->with(['paymentMethod', 'items'])
            ->where('status', 'delivered')
            ->latest()
            ->get()
            ->map(fn (Order $o) => [
                'model' => $o,
                'code' => $o->order_code,
                'date' => $o->created_at->format('Y/m/d H:i'),
                'items' => $o->items->count(),
                'payment' => $o->paymentMethod?->name ?? '—',
                'stamped' => (bool) $o->stamped_at,
                'total' => fmt_usd($o->total_usd),
                'totalSyp' => fmt_syp($o->total_syp),
                'invoice' => $o->stamped_at || $o->payment_confirmed_at,
            ])
            ->all();
    }

    /** كرت «الملغية» */
    public function getCancelledProperty(): array
    {
        return auth()->user()->orders()
            ->with(['paymentMethod', 'statusHistory', 'items'])
            ->where('status', 'cancelled')
            ->latest()
            ->get()
            ->map(function (Order $o) {
                $reason = $o->statusHistory->where('to_status', 'cancelled')->first()?->note;

                return [
                    'model' => $o,
                    'code' => $o->order_code,
                    'date' => $o->created_at->format('Y/m/d H:i'),
                    'items' => $o->items->count(),
                    'payment' => $o->paymentMethod?->name ?? '—',
                    'total' => fmt_usd($o->total_usd),
                    'totalSyp' => fmt_syp($o->total_syp),
                    'reason' => $reason ?: 'تواصل معنا لأي استفسار حول هذا الطلب',
                ];
            })
            ->all();
    }

    public function render()
    {
        return view('livewire.my-orders', [
            'tracking' => $this->tracking,
            'delivered' => $this->delivered,
            'cancelled' => $this->cancelled,
            'stages' => ['استلام الطلب', 'التأكيد', 'توثيق الدفع', 'التسليم'],
            'openSection' => $this->openSection,
        ]);
    }
}
