<?php

namespace App\Filament\Pages;

use App\Models\BotMessageLog;
use App\Models\BotPendingEdit;
use App\Models\ChatLog;
use App\Models\ChatQuestion;
use App\Models\Setting;
use Filament\Pages\Page;

/** دليل البوت التفاعلي — تعليمات كاملة بالأوامر وأمثلة الحالات */
class BotGuide extends Page
{
    /** دليل بوت تيليجرام — إعدادات، للمالك وحده */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('settings.manage');
    }

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 8;

    /** قسم موحّد مع الأسئلة والأجوبة وسجل المحادثات */
    protected static ?string $navigationGroup = 'الدردشة والبوت';

    protected static string $view = 'filament.pages.bot-guide';

    public function mount(): void
    {
        if (! Setting::get('bot_username')) {
            // توكن البوت التفاعلي هو الصحيح هنا؛ توكن الإشعارات بديل احتياطي فقط
            $token = Setting::get('telegram_command_bot_token') ?: Setting::get('telegram_bot_token');
            if ($token) {
                try {
                    $res = \Illuminate\Support\Facades\Http::timeout(8)
                        ->get("https://api.telegram.org/bot{$token}/getMe")->json();
                    if ($res['ok'] ?? false) {
                        Setting::set('bot_username', $res['result']['username'] ?? '');
                    }
                } catch (\Throwable $e) {
                    // الكشف تكميلي لا يُسقط الصفحة، لكنه لا يُبتلع صامتاً
                    report($e);
                }
            }
        }
    }

    public static function getNavigationLabel(): string
    {
        return 'دليل البوت التفاعلي';
    }

    public function getTitle(): string
    {
        return 'دليل البوت التفاعلي — قائمة الأوامر';
    }

    public function botUsername(): ?string
    {
        return Setting::get('bot_username');
    }

    protected function getViewData(): array
    {
        return [
            'botUsername' => $this->botUsername(),
            'pollingEnabled' => Setting::bool('bot_polling_enabled'),
            // البوت التفاعلي يقرأ telegram_command_bot_token — كان الدليل يفحص
            // توكن الإشعارات فيعرض «موجود ✓» والبوت متوقف فعليًا.
            'tokenExists' => (bool) Setting::get('telegram_command_bot_token'),
            'notifyTokenExists' => (bool) Setting::get('telegram_bot_token'),
            'adminChatId' => Setting::get('telegram_admin_chat_id'),
            'verifySsl' => Setting::bool('telegram_verify_ssl', true),
            'scheduledReports' => Setting::bool('bot_scheduled_reports'),
            'abandonedAlerts' => Setting::bool('bot_abandoned_alerts'),
            // حالة تشغيلية حقيقية بدل دليل نصي فقط
            'lastMessageAt' => BotMessageLog::max('created_at'),
            'messagesToday' => BotMessageLog::whereDate('created_at', today())->count(),
            'pendingEdits' => BotPendingEdit::valid()->count(),
            // جسر مع قسم الدردشة: الأسئلة المفعلة وما ينتظر إجابة
            'chatQuestionsCount' => ChatQuestion::where('is_active', true)->count(),
            'chatPendingCount' => ChatLog::where('was_helpful', false)->count(),
        ];
    }
}
