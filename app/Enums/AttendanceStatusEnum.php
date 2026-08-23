<?php

namespace App\Enums;

enum AttendanceStatusEnum: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case LATE = 'late';
    case EARLY_LEAVE = 'early_leave';
    case ON_LEAVE = 'on_leave';
    case EXCUSED = 'excused';

    public function label(): string
    {
        return match($this) {
            self::PRESENT => 'حاضر',
            self::ABSENT => 'غائب',
            self::LATE => 'متأخر',
            self::EARLY_LEAVE => 'مغادرة مبكرة',
            self::ON_LEAVE => 'في إجازة',
            self::EXCUSED => 'معذور',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PRESENT => 'success',
            self::ABSENT => 'danger',
            self::LATE => 'warning',
            self::EARLY_LEAVE => 'warning',
            self::ON_LEAVE => 'info',
            self::EXCUSED => 'secondary',
        };
    }
}
