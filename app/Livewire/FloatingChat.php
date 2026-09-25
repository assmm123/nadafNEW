<?php

namespace App\Livewire;

use App\Models\ChatLog;
use App\Models\ChatQuestion;
use App\Models\CommunicationMethod;
use Livewire\Component;

/**
 * الشات العائم — أجوبة جاهزة يديرها الأدمن 100% (بلا ذكاء اصطناعي).
 *
 * سياسة عرض وسائل التواصل البشري — صريحة وواحدة:
 *  ١) سؤال بلا جواب جاهز → تُعرض فورًا.
 *  ٢) نية صريحة (تواصل / مدير / أسعار) → تُعرض بعد الجواب الجاهز.
 * ولا تُعرض لمجرد تكرار السؤال — فالتكرار ليس دليل حاجة إلى بشر.
 */
class FloatingChat extends Component
{
    /** نوايا صريحة تطلب تواصلًا بشريًا أو سؤالًا عن الأسعار */
    private const HUMAN_INTENT = [
        'تواصل', 'اتصال', 'اتصل', 'كلمني', 'كلموني', 'رقم', 'هاتف', 'جوال',
        'واتساب', 'واتس', 'تلغرام', 'تيليجرام', 'تلجرام',
        'مدير', 'المدير', 'مسؤول', 'المسؤول', 'إدارة', 'ادارة',
        'سعر', 'أسعار', 'اسعار', 'بكم', 'تكلفة',
    ];

    public bool $open = false;

    public string $message = '';

    public array $messages = [];

    public string $sessionId;

    public function mount()
    {
        $this->sessionId = (string) (session('floating_chat_session') ?? session()->getId());
        session(['floating_chat_session' => $this->sessionId]);

        // استباق: من فشل معه الدفع مرتين يُفتح له الشات برسالة مطمئنة
        if (session()->has('payment_error_count') && session('payment_error_count') >= 2) {
            $this->open = true;
            $this->messages[] = [
                'from' => 'bot',
                'text' => 'لاحظنا أنك واجهت صعوبة في إتمام الدفع. اختر سؤالًا من القائمة أو اكتب استفسارك وسنساعدك فورًا.',
            ];
        }
    }

    public function toggle()
    {
        $this->open = ! $this->open;

        // التحية والاقتراحات تُضاف إن لم تكن هناك رسائل بعد
        if ($this->open && count($this->messages) === 0) {
            $this->messages[] = [
                'from' => 'bot',
                'text' => "مرحبًا بك في متجر نداف 👋\nكيف نقدر نساعدك؟ اكتب سؤالك أو اختر من الأوامر:",
                'suggestions' => ChatQuestion::where('is_active', true)->orderBy('sort_order')->take(4)->pluck('question')->all(),
            ];
        }
    }

    public function ask(string $question = '')
    {
        $text = trim($question !== '' ? $question : $this->message);
        $this->message = '';

        if ($text === '') {
            return;
        }

        $this->messages[] = ['from' => 'user', 'text' => $text];

        $match = ChatQuestion::match($text);
        $wantsHuman = self::wantsHuman($text);

        ChatLog::create([
            'session_id' => $this->sessionId,
            'visitor_name' => auth()->user()?->name,
            'message' => $text,
            'matched_answer' => $match?->answer,
            'was_helpful' => $match !== null,
            'trigger' => 'user',
        ]);

        // ١) لا جواب جاهز → كروت التواصل مباشرة (أول حالة مسموحة)
        if (! $match) {
            $this->messages[] = [
                'from' => 'bot',
                'text' => 'لم أجد جوابًا جاهزًا لسؤالك — تواصل معنا مباشرة عبر إحدى الوسائل التالية وسنرد بأسرع وقت:',
                'show_contact' => self::contactExists(),
            ];

            return;
        }

        // ٢) جواب جاهز موجود — يُعرض دائمًا
        $this->messages[] = ['from' => 'bot', 'text' => $match->answer];

        // ٣) نية صريحة → كروت التواصل بعد الجواب (الحالة الثانية المسموحة)
        if ($wantsHuman) {
            $this->messages[] = [
                'from' => 'bot',
                'text' => 'وإن أردت حديثًا مباشرًا مع فريق نداف — اختر الوسيلة الأنسب لك:',
                'show_contact' => self::contactExists(),
            ];
        }
    }

    /** هل يطلب الزائر تواصلًا بشريًا أو يسأل عن الأسعار صراحةً؟ */
    private static function wantsHuman(string $text): bool
    {
        $text = mb_strtolower($text);

        foreach (self::HUMAN_INTENT as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** هل توجد وسيلة تواصل مفعّلة يعرضها الأدمن؟ */
    private static function contactExists(): bool
    {
        return CommunicationMethod::where('is_active', true)->exists();
    }

    public function render()
    {
        return view('livewire.floating-chat');
    }
}
