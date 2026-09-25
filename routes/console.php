<?php

use Illuminate\Support\Facades\Schedule;

// الجدولة اليومية — تُشغَّل من cron على الاستضافة أو من سكربت الدورة المحلية
// الأرشفة كل ساعة — لاحترام نافذة الـ34 ساعة للطلبات المختومة بدقة
Schedule::command('orders:archive')->hourly()->withoutOverlapping();

// أتمتة البوتات — تحتاج cron نشطًا على الاستضافة
// استقبال أوامر البوت التفاعلي كل دقيقة
Schedule::command('bot:automation poll')->everyMinute()
    ->when(fn () => \App\Models\Setting::bool('bot_polling_enabled') && \App\Models\Setting::get('telegram_command_bot_token'))
    ->withoutOverlapping();

// تقرير صباحي 9:00 ومسائي 21:00
Schedule::command('bot:automation morning')->daily()->at('09:00');
Schedule::command('bot:automation evening')->daily()->at('21:00');

// تنبيه السلات المتروكة مرتان يوميًا
Schedule::command('bot:automation abandoned')->twiceDaily(10, 18);

// تنظيف ملفات إثبات الدفع اليتيمة — أسبوعيًا 3 فجرًا (يحذف الأقدم من 24 ساعة فقط)
Schedule::command('orders:prune-proofs --hours=24')->weekly()->at('03:00')->withoutOverlapping();

