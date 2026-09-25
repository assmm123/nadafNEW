<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    /**
     * قيم افتراضية على مستوى النموذج.
     *
     * العمودان `NOT NULL` بلا قيمة افتراضية في قاعدة البيانات، فترك أحدهما
     * فارغًا في النموذج يُسقط الحفظ بخطأ قاعدة بيانات لا برسالة مفهومة.
     * وهذان هما المعنيان الصحيحان: كمية صفر، وحدّ تنبيه ٣.
     */
    protected $attributes = [
        'quantity' => 0,
        'low_stock_threshold' => 3,
    ];

    /**
     * تحويل الفراغ إلى القيمة الافتراضية عند الإسناد.
     *
     * `$attributes` وحدها لا تكفي: قد يُسند `null` صراحةً (وهو ما يفعله نموذج
     * المنتج حين يُترك الحقل فارغًا)، والإسناد الصريح يتقدّم على الافتراضي.
     */
    protected function quantity(): Attribute
    {
        return Attribute::make(set: fn ($value) => ($value === null || $value === '') ? 0 : (int) $value);
    }

    protected function lowStockThreshold(): Attribute
    {
        return Attribute::make(set: fn ($value) => ($value === null || $value === '') ? 3 : (int) $value);
    }

    protected $fillable = [
        'product_id',
        'color',
        'color_hex',
        'size',
        'sku',
        'quantity',
        'low_stock_threshold',
    ];

    protected static function booted(): void
    {
        // توليد SKU تلقائي إن تُرك فارغًا — قابل للتعديل يدويًا من الأدمن دائمًا
        static::creating(function (self $variant) {
            if (blank($variant->sku)) {
                $variant->sku = static::generateSku($variant);
            } else {
                $variant->sku = Str::upper(trim($variant->sku));
            }
        });

        static::updating(function (self $variant) {
            if (blank($variant->sku)) {
                $variant->sku = static::generateSku($variant);
            }
        });
    }

    /** توليد رمز قطعة من كود المنتج الداخلي + اللون + المقاس، مضمون التفرد */
    public static function generateSku(self $variant): string
    {
        static $colorMap = [
            'أسود' => 'BLK', 'كحلي' => 'NVY', 'أبيض' => 'WHT', 'رمادي' => 'GRY',
            'بني' => 'BRN', 'أزرق' => 'BLU', 'أحمر' => 'RED', 'أخضر' => 'GRN',
            'بيج' => 'BEI', 'ذهبي' => 'GLD', 'فضي' => 'SLV', 'وردي' => 'PNK',
            'عنابي' => 'MRN', 'بورجوندي' => 'BRG', 'إسفنجي' => 'CRM', 'قهوة' => 'COF',
            'ملكي' => 'ROY', 'سماوي' => 'SKY', 'زيتي' => 'OLV', 'كحلي/ذهبي' => 'NVY',
            'أسود/فضي' => 'BLK',
        ];

        $product = $variant->product ?: Product::find($variant->product_id);

        $base = $product?->internal_code
            ?: (strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $product?->name_en ?? '') ?: 'PRD', 0, 3)).str_pad((string) $variant->product_id, 2, '0', STR_PAD_LEFT));

        $color = '';
        if ($variant->color) {
            foreach ($colorMap as $ar => $lat) {
                if (str_contains($variant->color, $ar)) {
                    $color = '-'.$lat;
                    break;
                }
            }
            if ($color === '') {
                $color = '-'.strtoupper(substr($variant->size ?: 'GEN', 0, 3));
            }
        }

        $size = $variant->size ? '-'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $variant->size)) : '';

        $candidate = $base.$color.$size;

        $i = 2;
        while (static::where('sku', $candidate)->where('id', '!=', $variant->id)->exists()) {
            $candidate = $base.$color.$size.'-'.Str::upper(Str::random(2)).'-'.Str::upper(Str::random(2)).'-'.$i;
            $i++;
        }

        return $candidate;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** تسمية المتغير للعرض في السلة والطلبات */
    public function label(): string
    {
        return trim(implode(' / ', array_filter([
            $this->color,
            $this->size,
        ])));
    }

    /**
     * تسمية الاختيار في اللوحة: اسم المنتج (اللون / المقاس) — المتوفر N.
     * الرصيد ظاهر في الخيار نفسه لأن من يُنشئ طلبًا يدويًا يحتاج معرفة
     * المتوفر قبل أن يختار، لا بعد أن يُرفض الطلب.
     */
    public function optionLabel(): string
    {
        $name = $this->product?->name_ar ?? 'منتج';
        $label = $this->label();

        return trim($name.($label ? " ({$label})" : '').' — المتوفر '.(int) $this->quantity);
    }
}
