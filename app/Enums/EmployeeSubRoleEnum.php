<?php

namespace App\Enums;

enum EmployeeSubRoleEnum: string
{
    case DRIVER = 'driver';
    case DELEGATE = 'delegate';

    public function label(): string
    {
        return match ($this) {
            self::DRIVER => 'سائق',
            self::DELEGATE => 'مندوب',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
