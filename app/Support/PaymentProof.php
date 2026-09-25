<?php

namespace App\Support;

use App\Models\PaymentMethod;
use Illuminate\Validation\ValidationException;

/**
 * بوابة إثبات الدفع — مصدر الحقيقة الوحيد.
 *
 * ── العلّة التي تحلّها ──
 * كان التحقق مكتوبًا **في واجهة الدفع وحدها**، ومكرّرًا حرفيًا في مكوّنين
 * (`CheckoutForm` و`CheckoutModal`). و`CheckoutService::place()` — الخدمة التي
 * تُولّد كود الطلب — **لا تتحقق من شيء إطلاقًا**. فكل ما يصل إليها يُنشئ طلبًا،
 * وطلبٌ بلا إثبات دفع صار ممكنًا بلا أن يمنعه أحد.
 *
 * ── القاعدة ──
 * وسيلة الدفع التي `requires_proof` عندها صحيحة لا تُتمّ الطلب إلا بـ**رقم
 * إيصال أو ملف إثبات — أحدهما يكفي**. والإعفاء الوحيد بطبيعة الحال هو «الدفع
 * عند التسليم»: لا حوالة تُرفق به أصلًا.
 *
 * ── ولماذا في `app/Support` لا في المكوّن ──
 * لأن الواجهة تجميل والخدمة حارس. الواجهة تُخبر العميل مبكرًا، والبوابة
 * تمنع الفعل. ولو بقي الحكم في المكوّن لمرّ أي مسار آخر لا يمرّ به.
 */
class PaymentProof
{
    /** الأنواع المعفاة بطبيعتها — لا حوالة تُرفق بالدفع عند التسليم */
    public const EXEMPT_TYPES = ['cash_on_delivery'];

    /** هل تتطلب هذه الوسيلة إثباتًا قبل توليد كود الطلب؟ */
    public static function requiresProof(?int $paymentMethodId): bool
    {
        if (! $paymentMethodId) {
            return false;
        }

        return (bool) PaymentMethod::whereKey($paymentMethodId)->value('requires_proof');
    }

    /**
     * البوابة — تُنادى داخل `CheckoutService::place()` **قبل** `Order::create`.
     *
     * @param  array<string, mixed>  $data  بيانات الطلب كما وصلت للخدمة
     *
     * @throws ValidationException
     */
    public static function assert(?int $paymentMethodId, array $data): void
    {
        $method = $paymentMethodId ? PaymentMethod::find($paymentMethodId) : null;

        // وسيلة الدفع إلزامية ما دام في المتجر وسائل معروضة. وتُركت مرنة حين
        // لا توجد وسائل أصلًا (تركيب جديد) فلا يُقفل الشراء بلا سبب.
        if (! $method) {
            if (PaymentMethod::where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'لم تُحدَّد وسيلة دفع صحيحة — لا يمكن إتمام الطلب.',
                ]);
            }

            return;
        }

        if (! $method->requires_proof) {
            return;
        }

        $reference = trim((string) ($data['payment_reference'] ?? ''));
        $file = $data['payment_proof_path'] ?? null;

        // أحدهما يكفي — رقم الإيصال أو صورة الإثبات
        if ($reference === '' && blank($file)) {
            throw ValidationException::withMessages([
                'payment_reference' => 'لا يمكن إتمام الطلب: «'.$method->name
                    .'» تتطلب رقم إيصال أو صورة إثبات دفع.',
            ]);
        }
    }

    /**
     * قواعد حقول الإثبات في واجهة الدفع.
     *
     * تُبنى هنا لا في المكوّن، فيبقى المكوّنان (`CheckoutForm` و`CheckoutModal`)
     * متطابقين بحكم البناء لا بحكم النسخ — وكانا قبل ذلك نصًّا مكرّرًا يتباعد
     * مع أول تعديل يقع في أحدهما.
     *
     * @return array<string, array<int, string>>
     */
    public static function proofRules(bool $required): array
    {
        return [
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'payment_reference' => $required
                ? ['required_without:proof', 'nullable', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public static function proofMessages(): array
    {
        return [
            'proof.file' => 'ملف الإثبات غير صالح — ارفع صورة أو PDF',
            'proof.mimes' => 'النوع المسموح: صورة (JPG/PNG/WEBP) أو ملف PDF',
            'proof.max' => 'حجم الملف يتجاوز 5MB',
            'payment_reference.required_without' => 'أدخل رقم الحوالة أو ارفع صورة الإيصال — أحدهما مطلوب',
        ];
    }
}
