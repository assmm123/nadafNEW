<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use Filament\Pages\Page;

class Reports extends Page
{
    /** الأرباح والتقارير — ممنوعة عن مسؤول المخزون والدعم */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('reports.view');
    }
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.pages.reports';

    public string $period = 'this_month';
    public ?string $from = null;
    public ?string $to = null;

    public array $summary = [];
    public array $byCategory = [];
    public array $byProduct = [];
    public array $daily = [];
    public array $comparison = [];
    public string $rangeLabel = '';

    /** كم منتجًا يُعرض في جدول «أفضل المنتجات» — يُختار من الصفحة */
    public int $topLimit = 10;

    public function mount(): void
    {
        $this->loadReport();
    }

    public function updatedPeriod(): void
    {
        $this->loadReport();
    }

    public function updatedTopLimit(): void
    {
        $this->loadReport();
    }

    public function updatedFrom(): void
    {
        if ($this->period === 'custom') {
            $this->loadReport();
        }
    }

    public function updatedTo(): void
    {
        if ($this->period === 'custom') {
            $this->loadReport();
        }
    }

    public function loadReport(): void
    {
        [$from, $to, $label] = ReportService::resolveRange($this->period, $this->from, $this->to);

        $this->summary = ReportService::summary($from, $to);
        $this->byCategory = ReportService::byCategory($from, $to);
        $this->byProduct = ReportService::byProduct($from, $to, $this->topLimit);
        $this->daily = ReportService::daily($from, $to);
        $this->rangeLabel = $label.' ('.$from->format('Y/m/d').' — '.$to->format('Y/m/d').')';

        // المقارنة تُمرَّر بالملخّص المحسوب أعلاه — فإعادة حسابه في الخدمة
        // تعني سبعة استعلامات إضافية في كل تحميل.
        $this->comparison = ReportService::comparison($from, $to, $this->summary);
    }

    /** نصّ نسبة التغيّر — أو شرطة إن لم يكن للفترة السابقة أساس */
    public static function deltaLabel(?float $delta): string
    {
        if ($delta === null) {
            return '—';
        }

        return ($delta > 0 ? '+' : '').number_format($delta, 1).'%';
    }

    /** اتجاه التغيّر لأجل التلوين: صعود · هبوط · ثبات */
    public static function deltaTone(?float $delta): string
    {
        return match (true) {
            $delta === null, abs($delta) < 0.05 => 'flat',
            $delta > 0 => 'up',
            default => 'down',
        };
    }

    public static function getNavigationLabel(): string
    {
        return 'السجلات والأرباح';
    }

    public function getTitle(): string
    {
        return 'سجل المبيعات والأرباح';
    }
}
