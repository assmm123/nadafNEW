<?php

namespace App\Filament\Widgets;

use App\Models\ChatLog;
use App\Models\Order;
use App\Models\PaymentMethod;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

/**
 * «شو عليك هلق؟» — شريط الإنقاذ العلوي.
 * يجمع كل ما يحتاج قرار صاحب المتجر الآن، ويختفي تمامًا عند الفراغ.
 */
class ActionRequired extends Widget
{
    /**
     * مُستبدَل بـActionQueue: نفس الفكرة لكن ببطاقات قابلة للنقر تفتح القوائم
     * المفلترة، وبطاقة صفرية باهتة لا مخفية. يمكن حذف الملف وview المرافق.
     */
    public static function canView(): bool
    {
        return false;
    }
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected static string $view = 'filament.widgets.action-required';

    public function getActions(): array
    {
        $actions = [];

        // 1) إثباتات دفع بانتظار الاعتماد (طلبيات عليها إثبات لكن لم يُوثّق قبضها)
        $pendingProof = Order::whereIn('status', ['pending', 'preparing'])
            ->whereNotNull('payment_proof_path')
            ->whereNull('payment_confirmed_at')
            ->count();
        if ($pendingProof > 0) {
            $actions[] = [
                'label' => "{$pendingProof} إثبات دفع بانتظار اعتمادك",
                'color' => 'brass',
                'url' => \App\Filament\Resources\OrderResource::getUrl('index'),
                'icon' => 'wallet',
            ];
        }

        // 2) طلبيات جديدة بلا أي إثبات ولا مرجع — تحتاج متابعة
        $noProof = Order::where('status', 'pending')
            ->whereNull('payment_proof_path')
            ->whereNull('payment_reference')
            ->whereHas('paymentMethod', fn ($q) => $q->where('requires_proof', true))
            ->count();
        if ($noProof > 0) {
            $actions[] = [
                'label' => "{$noProof} طلبيات جديدة بلا إثبات دفع",
                'color' => 'red',
                'url' => \App\Filament\Resources\OrderResource::getUrl('index'),
                'icon' => 'bag',
            ];
        }

        // 3) سؤال زبون بلا جواب في الشات
        $chatUnanswered = ChatLog::where('was_helpful', false)->count();
        if ($chatUnanswered > 0) {
            $actions[] = [
                'label' => "{$chatUnanswered} سؤال زبون بلا جواب",
                'color' => 'blue',
                'url' => '/admin/chat-logs',
                'icon' => 'envelope',
            ];
        }

        return $actions;
    }

    public function render(): View
    {
        return view('filament.widgets.action-required', [
            'actions' => $this->getActions(),
        ]);
    }
}
