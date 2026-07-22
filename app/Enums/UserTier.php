<?php

namespace App\Enums;

enum UserTier: string
{
    case Basic = 'basic';
    case Verified = 'verified';
    case Premium = 'premium';
}
