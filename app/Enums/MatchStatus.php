<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ambiguous = 'ambiguous';
}
