<?php

namespace App\Enums;

enum EmployeeTypeEnum: string
{
    case MANAGER = 'manager';
    case EMPLOYEE = 'employee';
    case DRIVER_REPRESENTATIVE = 'driver_representative';

    public function label(): string
    {
        return match ($this) {
            self::MANAGER => 'مدير',
            self::EMPLOYEE => 'موظف عادي',
            self::DRIVER_REPRESENTATIVE => 'سائق / مندوب',
        };
    }

    public function roleName(): string
    {
        return match ($this) {
            self::MANAGER => 'manager',
            self::EMPLOYEE => 'employee',
            self::DRIVER_REPRESENTATIVE => 'driver',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
