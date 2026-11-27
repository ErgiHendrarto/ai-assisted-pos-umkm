<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::PartiallyPaid => 'Partially Paid',
            self::Unpaid => 'Unpaid / Debt',
        };
    }
}
