<?php

namespace App\Enums;

enum ApprovalStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ESCALATED = 'escalated';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'معلق',
            self::APPROVED => 'معتمد',
            self::REJECTED => 'مرفوض',
            self::ESCALATED => 'مرفوع',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::ESCALATED => 'info',
        };
    }
}
