<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title_ar',
        'title_en',
        'content_ar',
        'content_en',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'en'
            ? ($this->title_en ?: $this->title_ar)
            : $this->title_ar;
    }

    public function getContentAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->content_en ?: $this->content_ar)
            : $this->content_ar;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function sections()
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }
}
