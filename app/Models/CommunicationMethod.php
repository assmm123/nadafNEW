<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationMethod extends Model
{
    public const TYPES = [
        'whatsapp' => ['ar' => 'واتساب', 'en' => 'WhatsApp', 'icon' => 'whatsapp'],
        'telegram' => ['ar' => 'تيليجرام', 'en' => 'Telegram', 'icon' => 'telegram'],
        'messenger' => ['ar' => 'ماسنجر', 'en' => 'Messenger', 'icon' => 'messenger'],
        'email' => ['ar' => 'البريد الإلكتروني', 'en' => 'Email', 'icon' => 'envelope'],
        'phone' => ['ar' => 'الهاتف', 'en' => 'Phone', 'icon' => 'phone'],
        'custom' => ['ar' => 'أخرى', 'en' => 'Other', 'icon' => 'globe'],
    ];

    protected $fillable = [
        'type',
        'label',
        'value',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function typeLabel(): string
    {
        return static::TYPES[$this->type][app()->getLocale()] ?? $this->type;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** رابط جاهز للنقر حسب نوع الوسيلة */
    public function link(): string
    {
        $value = trim($this->value);

        return match ($this->type) {
            'whatsapp' => 'https://wa.me/'.preg_replace('/\D/', '', $value),
            'telegram' => str_contains($value, 't.me') || str_starts_with($value, 'http')
                ? $value
                : 'https://t.me/'.ltrim($value, '@'),
            'messenger' => str_starts_with($value, 'http') ? $value : 'https://m.me/'.ltrim($value, '/'),
            'email' => "mailto:$value",
            'phone' => 'tel:'.preg_replace('/[^\d+]/', '', $value),
            default => $value,
        };
    }
}
