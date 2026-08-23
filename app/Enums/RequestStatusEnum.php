<?php

namespace App\Enums;

enum RequestStatusEnum: string
{
    case DRAFT = 'draft';
    case PREPARED = 'prepared';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case IN_DELIVERY = 'in_delivery';
    case DELIVERED = 'delivered';
    case COLLECTED = 'collected';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'مسودة',
            self::PREPARED => 'تم التحضير',
            self::UNDER_REVIEW => 'تحت المراجعة',
            self::APPROVED => 'معتمد',
            self::REJECTED => 'مرفوض',
            self::READY_FOR_DELIVERY => 'جاهز للتسليم',
            self::IN_DELIVERY => 'في الطريق',
            self::DELIVERED => 'تم التسليم',
            self::COLLECTED => 'تم التحصيل',
            self::CLOSED => 'مغلق',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT, self::PREPARED => 'secondary',
            self::UNDER_REVIEW => 'warning',
            self::APPROVED, self::READY_FOR_DELIVERY => 'info',
            self::REJECTED => 'danger',
            self::IN_DELIVERY => 'primary',
            self::DELIVERED, self::COLLECTED, self::CLOSED => 'success',
        };
    }

    public static function editableStatuses(): array
    {
        return [
            self::DRAFT->value,
            self::PREPARED->value,
        ];
    }

    public static function approvableStatuses(): array
    {
        return [
            self::UNDER_REVIEW->value,
        ];
    }
}
