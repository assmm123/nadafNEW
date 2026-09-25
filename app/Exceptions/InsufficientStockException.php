<?php

namespace App\Exceptions;

use App\Models\ProductVariant;
use RuntimeException;

/**
 * تُرمى عند محاولة حركة مخزون تُنتج رصيدًا سالبًا.
 * المخزون لا يقبل السالب مطلقًا — أي محاولة تُرفض وتُلغى المعاملة بالكامل.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $balance,
        public readonly int $delta,
        public readonly string $variantLabel = '',
    ) {
        $after = $balance + $delta;

        parent::__construct(
            'حركة مخزون مرفوضة: الرصيد '.$balance.'، المطلوب '.$delta.
            ' → الناتج '.$after.' (لا يقبل السالب)'.
            ($variantLabel !== '' ? ' — '.$variantLabel : '')
        );
    }

    public static function for(ProductVariant $variant, int $balance, int $delta): self
    {
        return new self($variant->id, $balance, $delta, (string) ($variant->label() ?? ''));
    }
}
