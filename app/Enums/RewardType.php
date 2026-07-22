<?php

namespace App\Enums;

enum RewardType: string
{
    case Cashback = 'cashback';
    case Points = 'points';
    case Coupon = 'coupon';
    case Lucky = 'lucky';
}
