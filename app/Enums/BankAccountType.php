<?php

namespace App\Enums;

enum BankAccountType: string
{
    case Savings = 'savings';
    case Current = 'current';
}
