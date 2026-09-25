<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\PaymentProof;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class CheckoutModal extends Component
{
    use WithFileUploads;

    public bool $open = false;

    /** wallets | details | success */
    public string $step = 'wallets';

    public array $paymentMethods = [];
    public ?int $payment_method_id = null;

    public string $shipping_method = 'pickup';
    public string $city = '';
    public string $address = '';
    public ?string $payment_reference = null;
    public ?string $payment_sender_name = null;

    /**
     * بيانات العميل — إلزامية للجميع.
     * وكانت النافذة تخلو منها وتُحوّل الزائر إلى `/login`، فيُشترط عليه حساب.
     */
    public string $customer_name = '';
    public string $customer_phone = '';
    public array $dynamicData = [];
    public $proof = null;
    public ?string $notes = null;

    public ?string $orderCode = null;
    public ?array $orderTotals = null;

    /**
     * تعبئة بيانات العميل مسبقًا: من حسابه إن كان مسجَّلًا، وإلا مما حفظه في
     * الجلسة من نافذة سابقة — فلا يعيد كتابتها في كل مرة.
     */
    public function mount(): void
    {
        $this->fillCustomer();
    }

    private function fillCustomer(): void
    {
        $saved = (array) session('customer_details', []);

        $this->customer_name = (string) (auth()->user()?->name ?? ($saved['name'] ?? ''));
        $this->customer_phone = (string) (auth()->user()?->phone ?? ($saved['phone'] ?? ''));
    }

    #[On('open-checkout-modal')]
    public function open(): void
    {
        // لا تسجيل دخول مطلوبًا: النافذة تعمل للزائر، وتجمع بياناته بنفسها
        $this->fillCustomer();

        if (CartService::count() === 0) {
            $this->dispatch('flash', message: __('cart.empty'));

            return;
        }

        $this->loadMethods();
        $this->resetValidation();
        $this->open = true;
        $this->step = 'wallets';
    }

    public function close(): void
    {
        $this->open = false;
        $this->reset(['step', 'payment_method_id', 'city', 'address', 'payment_reference', 'payment_sender_name', 'dynamicData', 'notes', 'orderCode', 'orderTotals']);
        $this->proof = null;
        $this->resetValidation();
    }

    protected function loadMethods(): void
    {
        $total = (float) ($this->totals['total_usd'] ?? 0);

        $this->paymentMethods = PaymentMethod::active()
            ->ordered()
            ->get()
            // الحد الأدنى **يُنفَّذ فعلًا**: الوسيلة تختفي إن كان الطلب أقل منه
            ->filter(fn (PaymentMethod $m) => $m->isAvailableFor($total))
            ->map(fn (PaymentMethod $m) => [
                ...$m->toArray(),

                'icon' => $m->icon(),
                'icon_url' => $m->iconUrl(),
                'barcode_url' => $m->barcodeUrl(),

                // التصنيف: يُعرض للعميل مجموعةً لا قائمة مسطّحة
                'group' => $m->group(),
                'group_label' => $m->groupLabel(),
                'group_icon' => $m->groupIcon(),

                // الحقول المُخفاة تُصفَّر هنا فلا يصل للقالب ما لا يُعرض.
                // والتصفير لا الحذف: القالب يقرأ المفتاح دائمًا فلا يحتاج فحصًا.
                'account_number' => $m->showsField('account_number') ? $m->account_number : null,
                'iban' => $m->showsField('iban') ? $m->iban : null,
                'account_name' => $m->showsField('account_name') ? $m->account_name : null,
                'instructions' => $m->showsField('instructions') ? $m->instructions : null,
                'conditions' => $m->showsField('conditions') ? $m->conditions : null,
                'barcode_path' => $m->showsField('barcode') ? $m->barcode_path : null,

                // الحقول المخصّصة: المرئية فقط
                'dynamic_fields' => $m->visibleCustomFields(),
            ])
            ->values()
            ->toArray();
    }

    public function getSelectedMethodProperty(): ?array
    {
        return collect($this->paymentMethods)
            ->first(fn ($m) => $m['id'] === $this->payment_method_id);
    }

    public function getRequiresProofProperty(): bool
    {
        return (bool) ($this->selectedMethod['requires_proof'] ?? false);
    }

    public function getTotalsProperty(): array
    {
        return CartService::totals($this->shipping_method);
    }

    /** اختيار محفظة → الانتقال لتفاصيلها */
    public function selectWallet(int $id): void
    {
        $this->payment_method_id = $id;
        $this->dynamicData = [];
        $this->step = 'details';
        $this->resetErrorBag();
    }

    /** رجوع خطوة للخلف */
    public function back(): void
    {
        if ($this->step === 'details') {
            $this->step = 'wallets';
            $this->resetErrorBag();
        }
    }

    public function confirm()
    {
        if (! $this->selectedMethod) {
            $this->addError('payment_method_id', 'اختر طريقة الدفع أولًا');

            return;
        }

        // قاعدة الإثبات من مصدرها الواحد في PaymentProof — كانت نسخة ثانية
        // من نفس الشرط، فتتباعد مع أول تعديل يقع في أحد المكوّنين وحده.
        $rules = array_merge([
            'shipping_method' => ['required', 'in:pickup,local'],
            // بيانات العميل إلزامية — وهي مصدر الاسم والرقم للطلب بلا حساب
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:6', 'max:30'],
            // والعنوان إلزامي دائمًا ولو كان الاستلام من المتجر
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required_if:shipping_method,local', 'nullable', 'string', 'max:100'],
            'payment_method_id' => ['required', 'in:'.implode(',', array_column($this->paymentMethods, 'id'))],
            'payment_sender_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], PaymentProof::proofRules($this->requiresProof));

        $messages = PaymentProof::proofMessages() + [
            'city.required_if' => 'المدينة مطلوبة للتوصيل المحلي',
            'customer_name.required' => 'الاسم مطلوب لإتمام الطلب',
            'customer_phone.required' => 'رقم الهاتف مطلوب لإتمام الطلب',
            'address.required' => 'العنوان مطلوب لإتمام الطلب',
            'payment_method_id.required' => 'اختر طريقة الدفع أولًا',
        ];

        $data = $this->validate($rules, $messages);

        // مطابقة اسم الحقل مع ما تتوقعه الخدمة — وإلا ضاع عنوان التوصيل المحلي
        $data['shipping_address'] = $data['address'] ?? null;

        // الحقول الديناميكية تُدمج في الملاحظات قبل إنشاء الطلب
        $dynFormatted = collect($this->selectedMethod['dynamic_fields'] ?? [])
            ->filter(fn ($f) => ! empty($this->dynamicData[$f['label']] ?? null))
            ->map(fn ($f) => $f['label'].': '.$this->dynamicData[$f['label']])
            ->implode(' | ');

        $data['notes'] = trim((string) ($data['notes'] ?? '').($dynFormatted !== '' ? "\n".$dynFormatted : '')) ?: null;

        // ارفع الإيصال **قبل** إنشاء الطلب — إن فشل الرفع لا يُنشأ طلب بلا إثبات
        if ($this->proof) {
            try {
                $data['payment_proof_path'] = $this->proof->store('payment-proofs', 'public');
            } catch (\Throwable $e) {
                report($e);
                $this->addError('proof', 'تعذّر رفع الإيصال — تحقق من الاتصال وأعد المحاولة');

                return;
            }
        }

        try {
            $order = CheckoutService::place(auth()->user(), [
                ...$data,
                'coupon_code' => '',
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages_) {
                foreach ($messages_ as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        $this->orderCode = $order->order_code;
        $this->orderTotals = [
            'total_usd' => $order->total_usd,
            'total_syp' => $order->total_syp,
        ];
        $this->step = 'success';
        $this->dispatch('cart-updated');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.checkout-modal', [
            'totals' => $this->totals,
            'selectedMethod' => $this->selectedMethod,
            'requiresProof' => $this->requiresProof,
            'cartItems' => $this->open && $this->step !== 'success' ? CartService::detailed() : collect(),
        ]);
    }
}
