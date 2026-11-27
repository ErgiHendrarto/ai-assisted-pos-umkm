<?php

namespace App\Enums;

enum StockMovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case CancellationReversal = 'cancellation_reversal';
}
