<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * صفحة الطلب — الأزرار في الأعلى بنفس ترتيب النموذج:
 * الفاتورة · تغيير الحالة · تم قبض الدفع · تم تسليم الطلب · إلغاء الطلب
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::invoiceAction(false),
            OrderResource::changeStatusAction(false),
            OrderResource::confirmPaymentAction(false),
            OrderResource::deliverAction(false),
            OrderResource::cancelAction(false),
        ];
    }
}
