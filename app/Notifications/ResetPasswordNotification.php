<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * إشعار استعادة كلمة المرور بالعربية.
 *
 * يعيد استخدام صنف Laravel الأساسي كاملًا (توليد الرابط الموقّع، صلاحيته،
 * وتخزين الرمز) ويستبدل النص الإنجليزي فقط. لا يحتاج ملف Blade: القالب
 * يُبنى بـ MailMessage ويُعرض بثيم البريد المرفق مع الإطار.
 */
class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $store = (string) setting('store_name_ar', 'نداف');

        $minutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60
        );

        return (new MailMessage)
            ->subject("استعادة كلمة المرور — {$store}")
            ->greeting('مرحبًا '.($notifiable->name ?? ''))
            ->line("وصلنا طلب لإعادة تعيين كلمة المرور لحسابك في {$store}.")
            ->action('إعادة تعيين كلمة المرور', $this->resetUrl($notifiable))
            ->line("الرابط صالح لمدة {$minutes} دقيقة، ويُستخدم مرة واحدة فقط.")
            ->line('إن لم تكن أنت من طلب ذلك فتجاهل هذه الرسالة — كلمة مرورك لم تتغيّر.');
    }
}
