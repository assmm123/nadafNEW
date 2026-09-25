<?php

namespace App\Filament\Pages;

use App\Models\ProductVariant;
use App\Services\StockService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * الجرد الفعلي — عدّ المخزون وتسوية الفروقات.
 *
 * ── ما كان ناقصًا قبل هذه الجولة ──
 *   • لا إحصائيات إطلاقًا: لا عدد الأصناف ولا عدد الفروقات ولا مجموع الزيادة
 *     والنقص. المالك يعدّ ٢٠٠ صنف ثم لا يدري ماذا فعل.
 *   • لا مرشّح للفروقات: إيجاد الفرق الواحد بين ٢٠٠ صف يدويًا.
 *   • `limit(200)` كان يقتطع **بصمت**، فقد يظن المالك أنه جرد كل شيء.
 *   • `apply()` كان يحفظ في حلقة بلا معاملة: فشل في المنتصف يترك نصف
 *     التسويات مطبَّقة والنصف الآخر لا — بلا أي إشارة إلى ذلك.
 *   • زر «حفظ التسويات» كان بلا خلفية أصلًا (`bg-primary-600` غير مُولَّد
 *     في بناء اللوحة)، فالإجراء الأساسي في الصفحة غير مرئي.
 *   • `wire:model.live.debounce` على كل صف ⇒ طلب سيرفر لكل تعديل، ويعاد
 *     رسم ٢٠٠ صف في كل مرة.
 */
class StockCount extends Page
{
    /** الجرد الفعلي يحتاج صلاحية تسوية المخزون */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('stock.adjust');
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationGroup = 'المخزون والجرد';

    protected static string $view = 'filament.pages.stock-count';

    /** أقصى عدد صفوف تُعرض في الجولة الواحدة */
    public const MAX_ROWS = 200;

    /** @var array<int, array{id:int,name:string,sku:?string,book:int,actual:string}> */
    public array $counts = [];

    public string $search = '';

    /** all · diff · counted · uncounted */
    public string $filter = 'all';

    /** هل اقتُطع العرض عند السقف؟ يُعرض تنبيه صريح للمالك */
    public bool $truncated = false;

    public int $totalVariants = 0;

    public function mount(): void
    {
        $this->loadCounts(keepEntered: false);
    }

    /**
     * البحث يعيد بناء القائمة، فيجب أن **يحفظ ما أدخله المالك** للأصناف
     * التي تبقى معروضة. بدونه يمحو بحثٌ عابر عدّ ساعة كاملة بلا تحذير.
     */
    public function updatedSearch(): void
    {
        $this->loadCounts(keepEntered: true);
    }

    // لا `updatedFilter`: المرشّح عرضٌ لا بيانات. وربطه بإعادة التحميل كان
    // يمحو كل العدّ المُدخل لمجرد الضغط على «بها فرق» — وهو أول ما يضغطه
    // المالك بعد أن ينتهي من الجرد.

    /**
     * @param  bool  $keepEntered  يحفظ العدّ المُدخل للأصناف الباقية في القائمة
     */
    protected function loadCounts(bool $keepEntered = true): void
    {
        $entered = [];

        if ($keepEntered) {
            foreach ($this->counts as $row) {
                if (isset($row['id'])) {
                    $entered[$row['id']] = $row['actual'] ?? null;
                }
            }
        }

        $this->totalVariants = ProductVariant::count();

        $query = ProductVariant::with('product');

        if ($this->search !== '') {
            $term = trim($this->search);

            // الباركود/الرمز يُبحث به كما يُبحث بالاسم — وهو أول ما يُقرأ
            // من الملصق على الرف أثناء الجرد.
            $query->where(function ($q) use ($term) {
                $q->where('sku', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name_ar', 'like', "%{$term}%")
                        ->orWhere('name_en', 'like', "%{$term}%"));
            });
        }

        // صفّ زائد واحد لكشف الاقتطاع بدل أن يقع صامتًا
        $rows = $query->orderBy('id')->limit(self::MAX_ROWS + 1)->get();

        $this->truncated = $rows->count() > self::MAX_ROWS;

        $this->counts = $rows->take(self::MAX_ROWS)
            ->map(function ($v) use ($entered) {
                $book = (int) $v->quantity;

                return [
                    'id' => $v->id,
                    'name' => $v->product->name_ar.($v->label() ? " ({$v->label()})" : ''),
                    'sku' => $v->sku,
                    'book' => $book,
                    // العدّ المُدخل سابقًا يفوز على الرصيد الدفتري
                    'actual' => array_key_exists($v->id, $entered) && $entered[$v->id] !== null
                        ? (string) $entered[$v->id]
                        : (string) $book,
                ];
            })
            ->values()
            ->toArray();
    }

    /** الفرق = العدّ الفعلي - الدفتري */
    public function diff(array $row): int
    {
        return (int) $row['actual'] - (int) $row['book'];
    }

    /** الصفوف التي بها فرق فعلًا — أي ما سيُسجَّل عند الحفظ */
    public function pendingRows(): array
    {
        return array_values(array_filter($this->counts, fn ($row) => $this->diff($row) !== 0));
    }

    /**
     * ملخّص الجرد المعروض.
     *
     * الإحصاءات تُحسب على **الصفوف المعروضة** لا على كل الأصناف، لأن الفرق
     * لا وجود له إلا بعد أن يُدخل المالك عدًّا. وعند الاقتطاع يُنبَّه صراحةً
     * كي لا يظن أن ما يراه هو كل المخزون.
     */
    public function stats(): array
    {
        $counted = 0;
        $surplus = 0;
        $shortage = 0;

        foreach ($this->counts as $row) {
            $d = $this->diff($row);

            if ($d === 0) {
                continue;
            }

            $counted++;
            $d > 0 ? $surplus += $d : $shortage += abs($d);
        }

        return [
            'rows' => count($this->counts),
            'counted' => $counted,
            'untouched' => count($this->counts) - $counted,
            'surplus' => $surplus,
            'shortage' => $shortage,
            'net' => $surplus - $shortage,
            'units' => array_sum(array_map(fn ($r) => (int) $r['book'], $this->counts)),
        ];
    }

    /**
     * مرشّح الصفوف للعرض — يطبَّق في الواجهة لأن الفرق محسوب من إدخال المالك.
     *
     * ⚠️ كل صف يحمل مفتاح `i` = **فهرسه في `$counts` الأصلية** لا في القائمة
     * المفلترة. وحقل الإدخال يُربط بـ`counts.{i}.actual`؛ فلو استُخدم فهرس
     * العرض (`$loop->index`) لانزاح الربط عند أي ترشيح، فيُكتب العدّ في صنف
     * آخر بلا أي خطأ ظاهر — وهو أسوأ من الفشل الصريح.
     */
    public function visibleCounts(): array
    {
        $rows = [];

        foreach ($this->counts as $i => $row) {
            $d = $this->diff($row);

            $keep = match ($this->filter) {
                'diff' => $d !== 0,
                'counted' => $d > 0,
                'shortage' => $d < 0,
                'untouched' => $d === 0,
                default => true,
            };

            if ($keep) {
                $rows[] = $row + ['i' => $i];
            }
        }

        return $rows;
    }

    /** إرجاع صنف واحد إلى رصيده الدفتري */
    public function resetRow(int $id): void
    {
        foreach ($this->counts as $i => $row) {
            if ($row['id'] === $id) {
                $this->counts[$i]['actual'] = (string) $row['book'];

                return;
            }
        }
    }

    /** إرجاع كل الصفوف إلى الرصيد الدفتري — بداية جرد جديدة */
    public function resetAll(): void
    {
        foreach ($this->counts as $i => $row) {
            $this->counts[$i]['actual'] = (string) $row['book'];
        }
    }

    /**
     * تثبيت العدّ الفعلي في المخزون.
     *
     * الحلقة كلها داخل معاملة واحدة: `StockService::record` يفتح معاملة
     * لكل صنف على حدة، فلو فشل الصنف الخامس بعد نجاح أربعة لبقي المخزون
     * نصف مسوّى بلا أن يعلم أحد. المعاملة الخارجية تجعلها كلها أو لا شيء.
     */
    public function apply(): void
    {
        $pending = $this->pendingRows();

        if ($pending === []) {
            Notification::make()
                ->title('لا توجد فروقات للتسوية')
                ->body('كل الأصناف المعروضة تطابق رصيدها الدفتري.')
                ->info()
                ->send();

            return;
        }

        $applied = DB::transaction(function () use ($pending) {
            foreach ($pending as $row) {
                $actual = (int) $row['actual'];

                StockService::record(
                    $row['id'],
                    'adjust',
                    $actual - (int) $row['book'],
                    null,
                    null,
                    'تسوية جرد فعلي ('.$row['book'].' → '.$actual.')',
                );
            }

            return count($pending);
        });

        // بعد الحفظ تُقرأ الأرصدة من جديد بلا حفظ إدخالات — فالقاعدة صارت
        // هي المرجع، والإدخالات القديمة لم تعد تعني شيئًا.
        $this->loadCounts(keepEntered: false);

        Notification::make()
            ->title("تم تسجيل تسوية الجرد لـ {$applied} صنفًا")
            ->body('يمكنك مراجعة الحركات في «سجل حركات المخزون».')
            ->success()
            ->send();
    }

    /** تصدير نتيجة الجرد المعروضة — للمراجعة الورقية أو الأرشفة */
    public function export()
    {
        $stats = $this->stats();
        $rows = $this->visibleCounts();

        $csv = "\u{FEFF}";   // BOM حتى يقرأ Excel العربية بلا تشويش
        $csv .= "الصنف,الرمز,الرصيد الدفتري,العدّ الفعلي,الفرق\n";

        foreach ($rows as $row) {
            $d = $this->diff($row);
            $csv .= implode(',', [
                '"'.str_replace('"', '""', $row['name']).'"',
                '"'.str_replace('"', '""', (string) $row['sku']).'"',
                $row['book'],
                (int) $row['actual'],
                ($d > 0 ? '+' : '').$d,
            ])."\n";
        }

        $csv .= "\n";
        $csv .= "أصناف معروضة,{$stats['rows']}\n";
        $csv .= "بها فرق,{$stats['counted']}\n";
        $csv .= "زيادة (وحدات),{$stats['surplus']}\n";
        $csv .= "نقص (وحدات),{$stats['shortage']}\n";
        $csv .= "صافي الفرق,{$stats['net']}\n";

        return response()->streamDownload(
            fn () => print($csv),
            'الجرد-الفعلي-'.now()->format('Y-m-d-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public static function getNavigationLabel(): string
    {
        return 'الجرد الفعلي';
    }

    public function getTitle(): string
    {
        return 'الجرد الفعلي — عدّ المخزون وتسوية الفروقات';
    }
}
