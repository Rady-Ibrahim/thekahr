<?php

namespace App\Enums;

enum EmployeeStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case RESIGNED = 'resigned';
    case ON_LEAVE = 'on_leave';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'موقوف',
            self::RESIGNED => 'استقال',
            self::ON_LEAVE => 'في إجازة',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'warning',
            self::SUSPENDED => 'danger',
            self::RESIGNED => 'secondary',
            self::ON_LEAVE => 'info',
        };
    }
}
