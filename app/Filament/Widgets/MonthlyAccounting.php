<?php

namespace App\Filament\Widgets;

use App\Services\ReportService;
use Filament\Widgets\Widget;

/**
 * الطبقة الثانية: «المحاسبة — هذا الشهر».
 *
 * لا تحسب شيئًا بنفسها — تستدعي ReportService نفسه الذي يغذّي صفحة التقارير
 * والتصدير، فلا يوجد رقمان مختلفان لنفس الشيء (وهو ما كان يحدث قبل: ثلاث
 * طرق مختلفة لحساب الربح).
 */
class MonthlyAccounting extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.monthly-accounting';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('reports.view');
    }

    /** @return array<string, mixed> */
    public function getAccounting(): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $summary = ReportService::summary($from, $to);

        $sales = (float) $summary['salesUsd'];
        $profit = (float) $summary['profitUsd'];
        $cogs = round($sales - $profit, 2);
        $margin = $sales > 0 ? (int) round($profit / $sales * 100) : 0;

        $categories = collect(ReportService::byCategory($from, $to));

        $best = $categories->sortByDesc(fn ($c) => (float) $c['profit'])->first();

        $worst = $categories
            ->filter(fn ($c) => (float) $c['sales'] > 0)
            ->sortBy(fn ($c) => (float) $c['profit'] / max((float) $c['sales'], 0.01))
            ->first();

        $daily = collect(ReportService::daily($from, $to))
            ->sortBy('day')
            ->take(-14)
            ->values();

        $peak = max(0.01, (float) $daily->max('sales'));

        return [
            'label' => 'هذا الشهر — من '.$from->format('Y/m/d').' حتى اليوم',
            'orders' => (int) $summary['ordersCount'],
            'sales' => $sales,
            'salesSyp' => (float) $summary['salesSyp'],
            'cogs' => $cogs,
            'profit' => $profit,
            'margin' => $margin,
            'items' => (int) $summary['itemsSold'],
            'best' => $best ? ['name' => $best['name'], 'margin' => $this->marginOf($best)] : null,
            'worst' => $worst ? ['name' => $worst['name'], 'margin' => $this->marginOf($worst)] : null,
            'bars' => $daily->map(fn ($d) => [
                'day' => $d['day'],
                'sales' => (float) $d['sales'],
                'height' => max(3, (int) round((float) $d['sales'] / $peak * 100)),
            ])->all(),
        ];
    }

    private function marginOf(array $row): int
    {
        $sales = (float) $row['sales'];

        return $sales > 0 ? (int) round((float) $row['profit'] / $sales * 100) : 0;
    }
}
