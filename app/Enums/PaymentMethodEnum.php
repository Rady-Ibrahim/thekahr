<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CHECK = 'check';
    case INSTAPAY = 'instapay';
    case FAWRY = 'fawry';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'نقدي',
            self::BANK_TRANSFER => 'تحويل بنكي',
            self::CHECK => 'شيك',
            self::INSTAPAY => 'إنستاباي',
            self::FAWRY => 'فوري',
        };
    }
}
