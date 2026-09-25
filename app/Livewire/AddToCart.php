<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Livewire\Component;

class AddToCart extends Component
{
    public Product $product;

    public array $colors = [];
    public array $sizes = [];

    public ?string $color = null;
    public ?string $size = null;

    public int $qty = 1;

    public function mount()
    {
        $this->colors = $this->product->variants->pluck('color')->filter()->unique()->values()->all();
        $this->sizes = $this->product->variants->pluck('size')->filter()->unique()->values()->all();

        // اختيار الخيار الأول تلقائيًا إن كان وحيدًا
        if (count($this->colors) === 1) {
            $this->color = $this->colors[0];
        }
        if (count($this->sizes) === 1) {
            $this->size = $this->sizes[0];
        }

        // ⚠️ وإن تعددت الخيارات ولم يُختر شيء، يُختار أول متغيّر **متوفّر**.
        // بدونه لا يطابق `variant` شيئًا فيصير `maxQty` صفرًا، ويُفتح المنتج
        // على «غير متوفر حاليًا» وهو يملك مخزونًا — فيظنّ الزائر أنه نافد
        // ويمضي. والاختيار التلقائي لا يمنع الزائر من تغييره.
        if ($this->color === null && $this->size === null) {
            $first = $this->product->variants->firstWhere('quantity', '>', 0)
                ?? $this->product->variants->first();

            $this->color = $first?->color;
            $this->size = $first?->size;
        }
    }

    public function getVariantProperty(): ?ProductVariant
    {
        if (! $this->product->variants->count()) {
            return null; // منتج بلا متغيرات
        }

        return $this->product->variants->first(
            fn (ProductVariant $v) => $v->color === $this->color && $v->size === $this->size
        );
    }

    public function getMaxQtyProperty(): int
    {
        if (! $this->product->variants->count()) {
            return 99;
        }

        return max(0, $this->variant?->quantity ?? 0);
    }

    public function getStockMessageProperty(): string
    {
        if (! $this->product->variants->count()) {
            return __('product.in_stock');
        }

        // اختيار ناقص لا يعني نفاد المخزون — والفرق بينهما يهمّ الزائر
        if (! $this->variant && $this->product->variants->sum('quantity') > 0) {
            return __('product.pick_variant') ?? 'اختر اللون والمقاس';
        }

        if ($this->maxQty > 0) {
            return __('product.stock_left', ['count' => $this->maxQty]);
        }

        return __('product.out_of_stock');
    }

    public function updatedColor()
    {
        $this->qty = 1;
    }

    public function updatedSize()
    {
        $this->qty = 1;
    }

    public function add(bool $buyNow = false)
    {
        if ($this->product->variants->count() && ($this->maxQty < 1 || $this->qty > $this->maxQty)) {
            $this->addError('qty', __('product.out_of_stock'));

            return;
        }

        $key = $this->variant
            ? 'v'.$this->variant->id
            : 'p'.$this->product->id;

        CartService::add($key, $this->qty);
        $this->dispatch('cart-updated');

        if ($buyNow) {
            // فتح نافذة إتمام الدفع المنبثقة مباشرة
            $this->dispatch('open-checkout-modal');
        } else {
            $this->dispatch('flash', message: __('product.added'));
        }
    }

    public function render()
    {
        return view('livewire.add-to-cart', [
            'maxQty' => $this->maxQty,
            'stockMessage' => $this->stockMessage,
        ]);
    }
}
