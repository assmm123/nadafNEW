<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name_ar',
        'name_en',
        'slug',
        'image',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug($category->name_en ?: $category->name_ar);
            }
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'category';
        $slug = Str::limit($slug, 90, '');
        $attempt = $slug;
        $i = 1;
        while (static::where('slug', $attempt)->exists()) {
            $attempt = $slug.'-'.(++$i);
        }

        return $attempt;
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /** اسم القسم حسب لغة الواجهة الحالية */
    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en'
            ? ($this->name_en ?: $this->name_ar)
            : $this->name_ar;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'images/')
            ? asset($this->image)
            : \Illuminate\Support\Facades\Storage::url($this->image);
    }
}
