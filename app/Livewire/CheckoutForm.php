<?php

namespace App\Livewire;

use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\PaymentProof;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class CheckoutForm extends Component
{
    use WithFileUploads;

    public string $shipping_method = 'pickup';
    public string $city = '';
    public string $address = '';
    public ?int $payment_method_id = null;
    public ?string $notes = null;
    public string $coupon_code = '';

    /**
     * بيانات العميل — إلزامية للجميع، مسجَّلًا كان أو ضيفًا.
     * وكانت غير موجودة أصلًا: الطلب كان يتطلب حسابًا ليأخذ الاسم والرقم منه.
     * فلمّا صار الطلب بلا حساب، صارت هذه الحقول مصدرهما.
     */
    public string $customer_name = '';
    public string $customer_phone = '';

    public $proof = null;                 // صورة/ملف إثبات الدفع
    public ?string $payment_reference = null;   // رقم الحوالة/الإشعار
    public ?string $payment_sender_name = null; // اسم مرسل الحوالة

    public ?int $appliedCouponId = null;
    public string $couponMessage = '';

    public array $paymentMethods = [];

    /** الخطوة الحالية: 1 الطلب · 2 وسيلة الدفع · 3 التأكيد (النجاح صفحة مستقلة) */
    public int $step = 1;

    public function mount()
    {
        // المسجَّل تُعبَّأ بياناته مسبقًا فلا يعيد كتابتها — والضيف يكتبها.
        // ويُقدَّم ما حفظه في الجلسة (من نافذة بيانات العميل) على الفراغ.
        $saved = (array) session('customer_details', []);

        $this->customer_name = (string) (auth()->user()?->name ?? ($saved['name'] ?? ''));
        $this->customer_phone = (string) (auth()->user()?->phone ?? ($saved['phone'] ?? ''));
        $this->address = (string) ($saved['address'] ?? '');
        $this->city = (string) ($saved['city'] ?? '');

        $total = (float) (CartService::totals()['total_usd'] ?? 0);

        $this->paymentMethods = PaymentMethod::active()
            ->ordered()
            ->get()
            // الحد الأدنى يُنفَّذ فعلًا كما في نافذة الدفع
            ->filter(fn (PaymentMethod $m) => $m->isAvailableFor($total))
            ->map(fn (PaymentMethod $m) => [
                ...$m->toArray(),

                'icon' => $m->icon(),
                'icon_url' => $m->iconUrl(),
                'barcode_url' => $m->barcodeUrl(),

                // التصنيف للعرض المجموعة
                'group' => $m->group(),
                'group_label' => $m->groupLabel(),
                'group_icon' => $m->groupIcon(),

                // الحقول المُخفاة تُصفَّر فلا تصل للقالب
                'account_number' => $m->showsField('account_number') ? $m->account_number : null,
                'iban' => $m->showsField('iban') ? $m->iban : null,
                'account_name' => $m->showsField('account_name') ? $m->account_name : null,
                'instructions' => $m->showsField('instructions') ? $m->instructions : null,
                'conditions' => $m->showsField('conditions') ? $m->conditions : null,
                'barcode_path' => $m->showsField('barcode') ? $m->barcode_path : null,

                'dynamic_fields' => $m->visibleCustomFields(),
            ])
            ->values()
            ->toArray();
    }

    /** الخطوة 1 ← 2 — تحقق حقول الطلب فقط */
    public function gotoPayment()
    {
        $this->validate([
            'shipping_method' => ['required', 'in:pickup,local'],
            'city' => ['required_if:shipping_method,local', 'nullable', 'string', 'max:100'],
            'address' => ['required_if:shipping_method,local', 'nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'city.required_if' => 'المدينة مطلوبة للتوصيل المحلي',
            'address.required_if' => 'العنوان مطلوب للتوصيل المحلي',
        ]);

        $this->step = 2;
        $this->dispatch('nad-scroll-top');
    }

    public function backToFields()
    {
        $this->step = 1;
    }

    /** اختيار وسيلة الدفع → فتح غرفتها (بدل نمط radio) */
    public function selectMethod($id)
    {
        $this->payment_method_id = (int) $id;
        $this->resetErrorBag('payment_method_id');
    }

    public function backToMethods()
    {
        $this->payment_method_id = null;
    }

    /** الخطوة 2 ← 3 — تحقق وسيلة الدفع والإثبات فقط */
    public function gotoSummary()
    {
        $rules = ['payment_method_id' => ['required', 'in:'.implode(',', array_column($this->paymentMethods, 'id'))]];
        $messages = ['payment_method_id.required' => 'اختر وسيلة الدفع أولًا'];

        if ($this->requiresProof) {
            $rules['payment_reference'] = ['required_without:proof', 'nullable', 'string', 'max:100'];
            $messages['payment_reference.required_without'] = 'أدخل رقم الحوالة أو ارفع صورة الإيصال (أحدهما مطلوب على الأقل)';
        }

        $this->validate($rules, $messages);
        $this->step = 3;
        $this->dispatch('nad-scroll-top');
    }

    public function backToPayment()
    {
        $this->step = 2;
    }

    public function getTotalsProperty()
    {
        return CartService::totals($this->shipping_method, $this->coupon);
    }

    public function getCouponProperty(): ?Coupon
    {
        return $this->appliedCouponId ? Coupon::find($this->appliedCouponId) : null;
    }

    public function getSelectedMethodProperty(): ?array
    {
        return collect($this->paymentMethods)
            ->first(fn ($m) => $m['id'] === $this->payment_method_id);
    }

    /** هل تتطلب وسيلة الدفع المختارة إثبات دفع قبل توليد الكود؟ */
    public function getRequiresProofProperty(): bool
    {
        return (bool) ($this->selectedMethod['requires_proof'] ?? false);
    }

    public function applyCoupon()
    {
        $coupon = Coupon::where('code', trim($this->coupon_code))->first();

        if ($coupon && $coupon->isValid($this->totals['subtotal_usd'])) {
            $this->appliedCouponId = $coupon->id;
            $this->couponMessage = __('cart.coupon_applied');
        } else {
            $this->appliedCouponId = null;
            $this->couponMessage = __('checkout.invalid_coupon');
        }
    }

    /** «إتمام الطلب» — يُنشئ الطلب ثم ينتقل إلى صفحة النجاح */
    public function confirm()
    {
        $this->placeOrder(whatsapp: false);
    }

    /**
     * «إتمام الطلب + إرسال واتساب» — يُنشئ الطلب **ثم** يفتح واتساب بالتفاصيل.
     *
     * والطلب يُنشأ أولًا: فلا يضيع إن أغلق العميل واتساب أو انقطع. والرسالة
     * تُبنى من الطلب المحفوظ لا من السلة — فتطابق ما سُجّل فعلًا.
     */
    public function confirmWithWhatsapp()
    {
        $this->placeOrder(whatsapp: true);
    }

    private function placeOrder(bool $whatsapp): void
    {
        $rules = [
            'shipping_method' => ['required', 'in:pickup,local'],
            // بيانات العميل إلزامية — وهي مصدر الاسم والرقم للطلب بلا حساب
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:6', 'max:30'],
            // العنوان إلزامي دائمًا: ولو كان الاستلام من المتجر يبقى وسيلة تواصل
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required_if:shipping_method,local', 'nullable', 'string', 'max:100'],
            'payment_method_id' => ['required', 'in:'.implode(',', array_column($this->paymentMethods, 'id'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];

        // قاعدة الإثبات تُقرأ من مصدرها الواحد في PaymentProof — لا نسخة ثانية
        // هنا. كانت مكتوبة حرفيًا في هذا المكوّن وفي CheckoutModal، فتتباعد
        // مع أول تعديل يقع في أحدهما وحده.
        $rules = array_merge($rules, PaymentProof::proofRules($this->requiresProof));
        $rules['payment_sender_name'] = ['nullable', 'string', 'max:100'];

        $messages = PaymentProof::proofMessages() + [
            'city.required_if' => 'المدينة مطلوبة للتوصيل المحلي',
            'customer_name.required' => 'الاسم مطلوب لإتمام الطلب',
            'customer_phone.required' => 'رقم الهاتف مطلوب لإتمام الطلب',
            'address.required' => 'العنوان مطلوب لإتمام الطلب',
        ];

        $data = $this->validate($rules, $messages);

        // مطابقة اسم الحقل مع ما تتوقعه الخدمة
        $data['shipping_address'] = $data['address'] ?? null;

        $data['coupon_code'] = $this->appliedCouponId
            ? Coupon::find($this->appliedCouponId)?->code
            : $this->coupon_code;

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
            // `auth()->user()` قد تكون null — طلب ضيف بلا حساب
            $order = CheckoutService::place(auth()->user(), $data);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key === 'coupon_code' ? 'coupon_code' : 'general', $message);
                }
            }

            return;
        }

        // رمز الطلب في الجلسة: به يفتح الضيف صفحة النجاح — فلا حاجة إلى حساب
        session()->push('placed_orders', $order->order_code);

        session()->flash('success', 'شكرًا '.$order->customerName().' لثقتك بشركة النداف 💛 — طلبك قيد المراجعة وسيتم الرد عليك في أسرع وقت ممكن.');

        $this->redirect(
            route('checkout.success', $order->order_code).($whatsapp ? '?wa=1' : ''),
            navigate: false,
        );
    }

    public function render()
    {
        return view('livewire.checkout-form', [
            'totals' => $this->totals,
            'requiresProof' => $this->requiresProof,
            'selectedMethod' => $this->selectedMethod,
        ]);
    }
}
