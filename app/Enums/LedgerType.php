<?php

namespace App\Enums;

enum LedgerType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
