<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductMedia extends Model
{
    /**
     * تحويل الفراغ إلى القيمة الافتراضية عند الإسناد.
     *
     * `sort_order` و`is_main` عمودان `NOT NULL` لهما افتراضي في قاعدة البيانات،
     * لكن الافتراضي **لا يُنقذ** حين يُسند `null` صراحةً — وهو ما يفعله نموذج
     * المنتج حين يُترك حقل الترتيب فارغًا:
     *     NOT NULL constraint failed: product_media.sort_order
     * فالحل على مستوى النموذج لا النموذج (Form).
     */
    protected function sortOrder(): Attribute
    {
        return Attribute::make(set: fn ($value) => ($value === null || $value === '') ? 0 : (int) $value);
    }

    protected function isMain(): Attribute
    {
        return Attribute::make(set: fn ($value) => (bool) $value);
    }

    protected $fillable = [
        'product_id',
        'type',
        'file_path',
        'duration',
        'is_main',
        'sort_order',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    protected static function booted(): void
    {
        // تحديد النوع والمدة تلقائيًا من الملف
        static::creating(function (ProductMedia $media) {
            $path = $media->file_path;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'])) {
                $media->type = 'video';
                if (! $media->duration && $media->fileExists()) {
                    $media->duration = round(media_duration($media->absolutePath()), 1) ?: null;
                }
            } else {
                $media->type = 'image';
            }
        });

        // ضمان صورة رئيسية واحدة لكل منتج
        static::saving(function (ProductMedia $media) {
            if ($media->is_main && $media->product_id) {
                ProductMedia::where('product_id', $media->product_id)
                    ->where('id', '!=', $media->id ?? 0)
                    ->update(['is_main' => false]);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isPublicPath(): bool
    {
        return str_starts_with($this->file_path, 'images/');
    }

    public function url(): string
    {
        return $this->isPublicPath()
            ? asset($this->file_path)
            : Storage::url($this->file_path);
    }

    public function fileExists(): bool
    {
        return $this->isPublicPath()
            ? file_exists(public_path($this->file_path))
            : Storage::disk('public')->exists($this->file_path);
    }

    public function absolutePath(): string
    {
        return $this->isPublicPath()
            ? public_path($this->file_path)
            : Storage::disk('public')->path($this->file_path);
    }
}
