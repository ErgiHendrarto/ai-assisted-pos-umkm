<?php

namespace App\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Abandoned = 'abandoned';
}
