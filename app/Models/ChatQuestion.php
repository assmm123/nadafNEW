<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatQuestion extends Model
{
    protected $fillable = [
        'question',
        'answer',
        'keywords',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
    ];

    /** مطابقة نص الزائر مع الأسئلة/الكلمات المفتاحية — أجوبة مضمونة 100% */
    public static function match(string $text): ?self
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return null;
        }

        $questions = static::where('is_active', true)->orderBy('sort_order')->get();

        // 1) أفضل مطابقة كلمات مفتاحية: أطول كلمة مطابقة تفوز (تدق أعلى من الكلمات القصيرة)
        $bestKeyword = null;
        $bestLength = 0;
        foreach ($questions as $q) {
            $keywords = $q->keywords;
            if (is_string($keywords)) {
                $keywords = json_decode($keywords, true) ?: [];
            }

            foreach ((array) $keywords as $kw) {
                $kw = mb_strtolower(trim((string) $kw));
                if ($kw !== '' && str_contains($text, $kw) && mb_strlen($kw) > $bestLength) {
                    $bestLength = mb_strlen($kw);
                    $bestKeyword = $q;
                }
            }
        }

        if ($bestKeyword) {
            return $bestKeyword;
        }

        // 2) تشابه جزئي مع نص السؤال — عتبة عالية لتجنب أخطاء «بتسلموا/التسليم»
        $bestSimilar = null;
        $bestPercent = 0;
        foreach ($questions as $q) {
            similar_text($text, mb_strtolower($q->question), $percent);
            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $bestSimilar = $q;
            }
        }

        return $bestPercent >= 68 ? $bestSimilar : null;
    }
}
