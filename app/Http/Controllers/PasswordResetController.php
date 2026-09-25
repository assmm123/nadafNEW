<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * استعادة كلمة المرور للعملاء.
 *
 * قبل هذا الملف لم يكن هناك أي مسار لاستعادة كلمة المرور: العميل الذي ينسى
 * كلمة مروره كان مقفولًا نهائيًا، والحل الوحيد أن يضبطها المالك يدويًا من
 * /admin/users. (جدول password_reset_tokens كان موجودًا في الـ migrations
 * منذ البداية — أي أن النية كانت موجودة والنصف الخلفي ناقص.)
 *
 * ملاحظتان تصميميتان:
 *
 * 1) لا نكشف إن كان البريد مسجّلًا أم لا: الرد واحد في الحالتين، وإلا صار
 *    النموذج أداة لتعداد حسابات العملاء.
 *
 * 2) القوالب (auth.forgot-password و auth.reset-password) من نطاق الواجهة
 *    الأمامية. لذلك يُفحص وجود القالب في **مساري العرض فقط** (GET) ويُعاد
 *    توجيه برسالة واضحة بدل خطأ 500. أما مسارا التنفيذ (POST) فلا يعتمدان
 *    على القالب إطلاقًا — يعملان ويردّان بتوجيه، فتظل المنطق قابلًا للاختبار
 *    قبل وجود الواجهة.
 */
class PasswordResetController extends Controller
{
    private const REQUEST_VIEW = 'auth.forgot-password';
    private const RESET_VIEW = 'auth.reset-password';

    /** نموذج طلب الرابط */
    public function showRequestForm()
    {
        if (! view()->exists(self::REQUEST_VIEW)) {
            return redirect()->route('login')->withErrors(['email' => self::pendingMessage()]);
        }

        return view(self::REQUEST_VIEW);
    }

    /** إرسال رابط الاستعادة بالبريد */
    public function sendResetLink(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'أُرسل رابط قبل قليل — انتظر دقيقة ثم أعد المحاولة.',
            ]);
        }

        // نفس الرد سواء كان البريد مسجّلًا أو لا (منع تعداد الحسابات)
        return back()->with('status', self::genericSentMessage());
    }

    /** نموذج تعيين كلمة مرور جديدة */
    public function showResetForm(Request $request, string $token)
    {
        if (! view()->exists(self::RESET_VIEW)) {
            return redirect()->route('login')->withErrors(['email' => self::pendingMessage()]);
        }

        return view(self::RESET_VIEW, [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /** تعيين كلمة المرور فعليًا */
    public function reset(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                // حقل password مضبوط بتحويل 'hashed' في موديل User،
                // فإسناد النص الصريح يكفي — لا داعي لـ Hash::make هنا.
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'تم تعيين كلمة المرور بنجاح — يمكنك الدخول الآن.');
        }

        throw ValidationException::withMessages([
            'email' => match ($status) {
                Password::INVALID_TOKEN => 'رابط الاستعادة غير صالح أو انتهت صلاحيته — اطلب رابطًا جديدًا.',
                Password::INVALID_USER => 'لم نجد حسابًا بهذا البريد.',
                default => 'تعذّر إعادة التعيين — أعد المحاولة أو اطلب رابطًا جديدًا.',
            },
        ]);
    }

    private static function genericSentMessage(): string
    {
        return 'إن كان هذا البريد مسجّلًا لدينا فسيصلك رابط إعادة التعيين خلال دقائق. تحقّق من صندوق الوارد والبريد المزعج.';
    }

    private static function pendingMessage(): string
    {
        return 'خدمة استعادة كلمة المرور قيد التجهيز — تواصل مع المتجر لتعيين كلمة مرور جديدة.';
    }
}
