<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Edited = 'edited';
    case Rejected = 'rejected';
}
