<?php

namespace App\Filament\Pages;

use App\Support\DashboardData;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * لوحة التحكم — صفحة مخصّصة لا مجموعة ودجات.
 *
 * السبب: ودجات Filament تُغلَّف بهيكل Filament نفسه (عنوان، حشو، حدود، شبكة)
 * فتظهر بشكل مختلف عن التصميم المطلوب ولا يمكن ضبط ترتيبها ومسافاتها بدقة.
 * بصفحة مخصّصة نملك العرض كاملًا: الترتيب، المقاسات، الألوان، والنصوص.
 *
 * والبيانات كلها من App\Support\DashboardData — لا استعلام واحد هنا.
 */
class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    /** بلا ودجات: الصفحة كلها عرض واحد متكامل */
    public function getWidgets(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'actions' => DashboardData::actions(),
            'accounting' => $user?->hasPermission('reports.view') ? DashboardData::accounting() : null,
            'position' => $user?->hasPermission('reports.view') ? DashboardData::position() : null,
            'orders' => $user?->hasPermission('orders.view') ? DashboardData::latestOrders() : collect(),
            'canOrders' => (bool) $user?->hasPermission('orders.view'),
        ];
    }
}
