<?php

namespace App\Enums;

enum VirtualCardStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
