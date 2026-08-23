<?php

namespace App\Enums;

enum SalaryStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case ON_HOLD = 'on_hold';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'مسودة',
            self::PENDING_APPROVAL => 'في انتظار الموافقة',
            self::APPROVED => 'معتمد',
            self::PAID => 'مدفوع',
            self::REJECTED => 'مرفوض',
            self::ON_HOLD => 'معلق',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT, self::PENDING_APPROVAL => 'warning',
            self::APPROVED => 'info',
            self::PAID => 'success',
            self::REJECTED => 'danger',
            self::ON_HOLD => 'secondary',
        };
    }
}
