<?php

namespace App\Enums;

enum OrderInputStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reviewed = 'reviewed';
}
