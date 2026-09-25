<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    /** قطعة واحدة — لون ومقاس واحد، ولا يختار العميل شيئًا */
    public const TYPE_SINGLE = 'single';

    /** عدة نسخ من القطعة نفسها باختلاف اللون/المقاس */
    public const TYPE_VARIANT = 'variant';

    public const TYPES = [
        self::TYPE_SINGLE => 'قطعة واحدة',
        self::TYPE_VARIANT => 'عدة ألوان أو مقاسات',
    ];

    protected $fillable = [
        'category_id',
        'product_type',
        'name_ar',
        'name_en',
        'slug',
        'internal_code',
        'description_ar',
        'description_en',
        'price_usd',
        'cost_usd',
        'wholesale_price_usd',
        'hide_wholesale',
        'old_price_usd',
        'hide_price',
        'hide_unit_price',
        'hide_colors',
        'manual_price_syp',
        'is_featured',
        'allow_inquiry',
        'is_active',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'allow_inquiry' => 'boolean',
        'is_active' => 'boolean',
        'hide_wholesale' => 'boolean',
        'hide_price' => 'boolean',
        'hide_unit_price' => 'boolean',
        'hide_colors' => 'boolean',
    ];

    // ═══════════════ نوع المنتج ═══════════════

    public function isSingle(): bool
    {
        return $this->product_type === self::TYPE_SINGLE;
    }

    public function isVariant(): bool
    {
        return ! $this->isSingle();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->product_type] ?? self::TYPES[self::TYPE_SINGLE];
    }

    /** هل يُعرض سعر الجملة لهذا المنتج؟ (إعداد عام + مفتاح خاص بالمنتج) */
    public function showsWholesale(): bool
    {
        return ! $this->hide_wholesale
            && ! Setting::bool('hide_wholesale_button')
            && (bool) $this->wholesale_price_usd;
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name_en ?: $product->name_ar);
            }
            if (blank($product->internal_code)) {
                $product->internal_code = static::generateInternalCode($product);
            }
            if (blank($product->product_type)) {
                $product->product_type = self::TYPE_SINGLE;
            }
        });
    }

    /** توليد كود داخلي تلقائي: 3 حروف من الاسم الإنجليزي + رقم تسلسلي (مثل CRV-07) */
    public static function generateInternalCode(Product $product): string
    {
        $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $product->name_en ?: '') ?: 'PRD', 0, 3));
        $base = $letters.'-'.str_pad((string) (static::max('id') + 1), 2, '0', STR_PAD_LEFT);

        $attempt = $base;
        while (static::where('internal_code', $attempt)->where('id', '!=', $product->id ?? 0)->exists()) {
            $attempt = $base.'-'.Str::upper(Str::random(2));
        }

        return $attempt;
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = \Illuminate\Support\Str::slug($base) ?: 'product';
        $slug = \Illuminate\Support\Str::limit($slug, 90, '');
        $attempt = $slug;
        $i = 1;
        while (static::where('slug', $attempt)->exists()) {
            $attempt = $slug.'-'.(++$i);
        }

        return $attempt;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function media()
    {
        return $this->hasMany(ProductMedia::class)->orderByDesc('is_main')->orderBy('sort_order');
    }

    public function mainMedia()
    {
        return $this->hasOne(ProductMedia::class)->orderByDesc('is_main')->orderBy('sort_order');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en'
            ? ($this->name_en ?: $this->name_ar)
            : $this->name_ar;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->description_en ?: $this->description_ar)
            : $this->description_ar;
    }

    public function getTotalStockAttribute(): int
    {
        // إن كانت العلاقة محمّلة مسبقًا نجمع في الذاكرة بدل استعلام لكل صف (N+1)
        if ($this->relationLoaded('variants')) {
            return (int) $this->variants->sum('quantity');
        }

        return (int) $this->variants()->sum('quantity');
    }

    public function inStock(): bool
    {
        if ($this->variants()->exists()) {
            return $this->total_stock > 0;
        }

        return true; // منتج بلا متغيرات = متوفر دائمًا
    }

    /**
     * حالة المخزون للعرض — **مصدر واحد يخدم اللوحة والمتجر معًا**.
     *
     * كانت اللوحة تجمع كميات المتغيرات مباشرةً، والمتجر يستخدم inStock().
     * وبما أن inStock() تعتبر منتجًا بلا متغيرات «متوفرًا دائمًا»، كان المنتج
     * نفسه يظهر «نفدت» في اللوحة و«متوفر» في المتجر. هذا الدالّة تُوحّدهما.
     *
     * @return array{kind: string, quantity: int, label: string}
     *   kind: unlimited | out | low | ok
     */
    public function stockInfo(): array
    {
        if (! $this->variants()->exists()) {
            return ['kind' => 'unlimited', 'quantity' => 0, 'label' => 'غير محدود'];
        }

        $quantity = $this->total_stock;

        if ($quantity <= 0) {
            return ['kind' => 'out', 'quantity' => 0, 'label' => 'نفدت'];
        }

        $low = $this->variants()->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->exists();

        return [
            'kind' => $low ? 'low' : 'ok',
            'quantity' => $quantity,
            'label' => $low ? 'كمية محدودة' : $quantity.' قطعة',
        ];
    }

    // ═══════════════ أعلام الظهور للعميل ═══════════════
    // كلها **لكل منتج** ولا تلغي الإعدادات العامة — الدمج في helpers.php

    /** إخفاء كل الأسعار لهذا المنتج */
    public function hidesPrice(): bool
    {
        return (bool) $this->hide_price;
    }

    /** إخفاء السعر العادي (سعر القطعة) مع إبقاء الجملة */
    public function hidesUnitPrice(): bool
    {
        return (bool) $this->hide_unit_price;
    }

    /** إخفاء سعر الجملة لهذا المنتج */
    public function hidesWholesale(): bool
    {
        return (bool) $this->hide_wholesale;
    }

    /** إخفاء الألوان والمقاسات — يُعرض المنتج بلا اختيار */
    public function hidesColors(): bool
    {
        return (bool) $this->hide_colors;
    }

    /** الألوان المعروضة للعميل — فارغة إن أُخفيت أو لم تُضف */
    public function visibleVariants()
    {
        return $this->hidesColors() ? collect() : $this->variants;
    }

    /** السعر بالدولار حسب نوع الطلب (مفرق/جملة) */
    public function unitPriceUsd(bool $wholesale = false): float
    {
        if ($wholesale && $this->wholesale_price_usd) {
            return (float) $this->wholesale_price_usd;
        }

        return (float) $this->price_usd;
    }

    /** السعر بالليرة: تجاوز يدوي إن وجد وإلا حساب من سعر الصرف (مقرّب لأقرب 100) */
    public function priceSyp(bool $wholesale = false): float
    {
        if (! $wholesale && $this->manual_price_syp) {
            return (float) $this->manual_price_syp;
        }

        $rate = (float) Setting::get('exchange_rate', 15000);

        return round($this->unitPriceUsd($wholesale) * $rate / 100) * 100;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function imageUrl(): ?string
    {
        $media = $this->media->first() ?? $this->mainMedia()->first();

        return $media?->url();
    }

    public function hasVideo(): bool
    {
        return $this->media->contains(fn (ProductMedia $m) => $m->type === 'video');
    }
}
