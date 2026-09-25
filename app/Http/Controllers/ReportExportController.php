<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function export(Request $request)
    {
        // التصدير يخرج الأرباح والتفاصيل كاملة — لا يكفي أن المستخدم من الطاقم.
        // بوابة /admin تسمح لكل الأدوار، فالفحص هنا صريح لأن هذا المسار خارج Filament
        // ولا تمر عليه السياسات تلقائيًا.
        abort_unless($request->user()?->hasPermission('reports.view'), 403);

        [$from, $to, $label] = ReportService::resolveRange(
            $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to'),
        );

        return match ($request->query('type', 'csv')) {
            'xlsx' => $this->xlsx($from, $to, $label),
            'pdf' => $this->pdf($from, $to, $label),
            default => $this->csv($from, $to, $label),
        };
    }

    /** CSV — يفتح في Excel مباشرة (بترميز UTF-8 BOM للعربية) */
    private function csv(Carbon $from, Carbon $to, string $label): StreamedResponse
    {
        $filename = "nadaf-report-{$from->format('Ymd')}-{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($from, $to, $label) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $summary = ReportService::summary($from, $to);
            fputcsv($out, ['تقرير متجر نداف — '.$label]);
            fputcsv($out, ['عدد الطلبات', $summary['ordersCount']]);
            fputcsv($out, ['إجمالي المبيعات $', $summary['salesUsd']]);
            fputcsv($out, ['إجمالي المبيعات ل.س', $summary['salesSyp']]);
            fputcsv($out, ['الأرباح المقدرة $', $summary['profitUsd']]);
            fputcsv($out, ['عناصر مبيعة', $summary['itemsSold']]);
            fputcsv($out, []);
            fputcsv($out, ['التاريخ', 'كود الطلب', 'الحالة', 'العميل', 'القسم', 'المنتج', 'المتغير', 'الكمية', 'سعر الوحدة $', 'التكلفة $', 'الإجمالي $', 'النوع']);

            foreach (ReportService::detailed($from, $to) as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** XLSX متعدد الأوراق عبر OpenSpout */
    private function xlsx(Carbon $from, Carbon $to, string $label): StreamedResponse
    {
        $filename = "nadaf-report-{$from->format('Ymd')}-{$to->format('Ymd')}.xlsx";
        $summary = ReportService::summary($from, $to);

        $headStyle = (new Style())->setFontBold();

        return response()->streamDownload(function () use ($from, $to, $label, $summary, $headStyle) {
            $writer = new XlsxWriter();

            // openToFile('php://output') بدل openToBrowser():
            // الأخيرة تستدعي header() و ob_end_clean() بعد أن أرسلت Laravel رؤوسها
            // داخل streamDownload — فيظهر تحذير «headers already sent» يتسرّب إلى
            // الملف الثنائي ويفسده (خصوصًا مع APP_DEBUG=true).
            $writer->openToFile('php://output');

            // ورقة الملخص
            $writer->getCurrentSheet()->setName('الملخص');
            $writer->addRow(Row::fromValues(['تقرير متجر نداف — '.$label], $headStyle));
            $writer->addRow(Row::fromValues(['عدد الطلبات', $summary['ordersCount']]));
            $writer->addRow(Row::fromValues(['إجمالي المبيعات $', $summary['salesUsd']]));
            $writer->addRow(Row::fromValues(['إجمالي المبيعات ل.س', $summary['salesSyp']]));
            $writer->addRow(Row::fromValues(['الأرباح المقدرة $', $summary['profitUsd']]));
            $writer->addRow(Row::fromValues(['عناصر مبيعة', $summary['itemsSold']]));
            $writer->addRow(Row::fromValues(['متوسط قيمة الطلب $', $summary['avgUsd']]));

            // السجل اليومي
            $writer->addNewSheetAndMakeItCurrent()->setName('السجل اليومي');
            $writer->addRow(Row::fromValues(['التاريخ', 'الطلبات', 'المبيعات $', 'المبيعات ل.س'], $headStyle));
            foreach (ReportService::daily($from, $to) as $r) {
                $writer->addRow(Row::fromValues([$r['day'], $r['orders'], $r['sales'], $r['sales_syp']]));
            }

            // الأقسام
            $writer->addNewSheetAndMakeItCurrent()->setName('الأقسام');
            $writer->addRow(Row::fromValues(['القسم', 'الكمية', 'المبيعات $', 'الأرباح $'], $headStyle));
            foreach (ReportService::byCategory($from, $to) as $r) {
                $writer->addRow(Row::fromValues([$r['name'], $r['qty'], $r['sales'], $r['profit']]));
            }

            // المنتجات
            $writer->addNewSheetAndMakeItCurrent()->setName('المنتجات');
            $writer->addRow(Row::fromValues(['المنتج', 'الكمية', 'المبيعات $', 'الأرباح $'], $headStyle));
            foreach (ReportService::byProduct($from, $to, 100) as $r) {
                $writer->addRow(Row::fromValues([$r['name'], $r['qty'], $r['sales'], $r['profit']]));
            }

            // التفاصيل
            $writer->addNewSheetAndMakeItCurrent()->setName('التفاصيل');
            $writer->addRow(Row::fromValues(['التاريخ', 'كود الطلب', 'الحالة', 'العميل', 'القسم', 'المنتج', 'المتغير', 'الكمية', 'سعر الوحدة $', 'التكلفة $', 'الإجمالي $', 'النوع'], $headStyle));
            foreach (ReportService::detailed($from, $to) as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** PDF عبر صفحة طباعة محسّنة (طباعة المتصفح = PDF بجودة عربية مثالية) */
    private function pdf(Carbon $from, Carbon $to, string $label)
    {
        return view('admin.report-print', [
            'from' => $from,
            'to' => $to,
            'label' => $label,
            'summary' => ReportService::summary($from, $to),
            'byCategory' => ReportService::byCategory($from, $to),
            'byProduct' => ReportService::byProduct($from, $to, 15),
            'daily' => ReportService::daily($from, $to),
        ]);
    }
}
