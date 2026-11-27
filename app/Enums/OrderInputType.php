<?php

namespace App\Enums;

enum OrderInputType: string
{
    case Manual = 'manual';
    case Voice = 'voice';
    case Image = 'image';
}
