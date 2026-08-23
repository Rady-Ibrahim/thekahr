<?php

namespace App\Enums;

enum DeliveryStatusEnum: string
{
    case PENDING = 'pending';
    case IN_TRANSIT = 'in_transit';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PARTIALLY_DELIVERED = 'partially_delivered';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'معلق',
            self::IN_TRANSIT => 'في الطريق',
            self::COMPLETED => 'مكتمل',
            self::FAILED => 'فشل',
            self::PARTIALLY_DELIVERED => 'تسليم جزئي',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'secondary',
            self::IN_TRANSIT => 'primary',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::PARTIALLY_DELIVERED => 'warning',
        };
    }
}
