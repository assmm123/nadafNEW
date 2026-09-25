<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Support\OrderWhatsapp;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * النافذة الإلزامية لبيانات العميل — مصدر واحد لكل مسارات الطلب.
 *
 * ── لماذا نافذة مشتركة ──
 * «إتمام الطلب» و«الشراء الآن» و«إرسال على واتساب» تحتاج كلها الاسم والرقم
 * والعنوان. ولو جُمعت في كل موضع على حدة لتفرّقت القواعد وتغيّر أحدها دون
 * الآخر. فهي هنا مرة واحدة، تُفتح من أي زر بحدث `open-customer-details`.
 *
 * ── ولا تسجيل دخول ──
 * البيانات تُحفظ في الجلسة (`customer_details`) لا في حساب. فمن لا يريد
 * إنشاء حساب يُتمّ طلبه، ومن أنشأ حسابًا تُعبَّأ بياناته مسبقًا من حسابه.
 */
class CustomerDetailsModal extends Component
{
    public bool $open = false;

    /** وجهة الطلب: product (منتج واحد) أو cart (كل السلة) */
    public string $target = 'cart';

    public ?int $productId = null;
    public ?int $variantId = null;
    public int $quantity = 1;

    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public string $city = '';

    public function mount(): void
    {
        $saved = (array) session('customer_details', []);

        // المسجَّل تُقدَّم بياناته على المحفوظ — هي الأدقّ
        $this->name = (string) (auth()->user()?->name ?? ($saved['name'] ?? ''));
        $this->phone = (string) (auth()->user()?->phone ?? ($saved['phone'] ?? ''));
        $this->address = (string) ($saved['address'] ?? '');
        $this->city = (string) ($saved['city'] ?? '');
    }

    #[On('open-customer-details')]
    public function openModal(string $target = 'cart', ?int $productId = null, ?int $variantId = null, int $quantity = 1): void
    {
        $this->target = $target === 'product' ? 'product' : 'cart';
        $this->productId = $productId;
        $this->variantId = $variantId;
        $this->quantity = max(1, $quantity);

        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /** الحفظ إلزامي: لا تُبنى رسالة بلا اسم ورقم وعنوان */
    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:6', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required' => 'الاسم مطلوب لإتمام الطلب',
            'name.min' => 'اكتب الاسم كاملًا',
            'phone.required' => 'رقم الهاتف مطلوب لإتمام الطلب',
            'phone.min' => 'رقم الهاتف قصير جدًا',
            'address.required' => 'العنوان مطلوب لإتمام الطلب',
        ]);

        $customer = [
            'name' => trim($this->name),
            'phone' => trim($this->phone),
            'address' => trim(implode(' — ', array_filter([$this->city, $this->address]))),
        ];

        session(['customer_details' => $customer + ['city' => $this->city]]);

        $this->open = false;

        $this->redirect(whatsapp_inquiry_link($this->message($customer)), navigate: false);
    }

    /** الرسالة بحسب الوجهة — والصيغة نفسها في الحالتين */
    private function message(array $customer): string
    {
        if ($this->target === 'product' && $this->productId) {
            $product = Product::with('variants')->find($this->productId);

            if ($product) {
                $variant = $this->variantId
                    ? $product->variants->firstWhere('id', $this->variantId)
                    : null;

                return OrderWhatsapp::forProduct($product, $variant, $this->quantity, $customer);
            }
        }

        return OrderWhatsapp::forCart(CartService::totals()['items'], $customer);
    }

    public function render()
    {
        return view('livewire.customer-details-modal');
    }
}
