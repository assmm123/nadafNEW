<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $fillable = [
        'page_id',
        'heading_ar',
        'heading_en',
        'body_ar',
        'body_en',
        'images',
        'videos',
        'layout',
        'sort_order',
    ];

    protected $casts = [
        'images' => 'array',
        'videos' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function getHeadingAttribute(): string
    {
        return app()->getLocale() === 'en'
            ? ($this->heading_en ?: (string) $this->heading_ar)
            : (string) $this->heading_ar;
    }

    public function getBodyAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->body_en ?: $this->body_ar)
            : $this->body_ar;
    }

    public function mediaUrls(?array $paths): array
    {
        return collect($paths ?? [])->map(function ($p) {
            return str_starts_with($p, 'images/')
                ? asset($p)
                : \Illuminate\Support\Facades\Storage::disk('public')->url($p);
        })->all();
    }
}
