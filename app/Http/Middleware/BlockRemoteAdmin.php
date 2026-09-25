<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لوحة الإدارة محصورة على الشبكة المحلية في بيئة التطوير.
 *
 * ── لماذا ──
 * لفتح المتجر لعميل في دولة أخرى نفتح «نفقًا» من الجهاز إلى الإنترنت. والنفق
 * يفتح **كل** ما على السيرفر لا المتجر وحده — فيصير `/admin` متاحًا للعالم
 * على عنوان عام. وهو أخطر ما يمكن تسريبه من مشروع: لوحة تحكم كاملة.
 *
 * فما دام `APP_ENV=local`، يُرفض الوصول إلى `/admin` من أي مضيف غير محلي.
 * ويردّ **404 لا 403**: لا نكشف حتى وجود اللوحة.
 *
 * وعلى الإنتاج (`APP_ENV=production`) لا أثر لهذا الوسيط إطلاقًا — فالمتجر
 * على نطاقه الحقيقي وتُحمى اللوحة بكلمة مرورها وصلاحياتها.
 */
class BlockRemoteAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        if ($this->isLocalHost($request->getHost())) {
            return $next($request);
        }

        abort(404);
    }

    /**
     * المضيف المحلي: العروة · localhost · أي عنوان خاص (10.x · 192.168.x ·
     * 172.16-31.x) — فالشبكة الداخلية موثوقة، وما بعدها لا.
     */
    private function isLocalHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            // اسم نطاق لا عنوان — لا يُعدّ محليًّا (وهو حال النفق)
            return false;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
