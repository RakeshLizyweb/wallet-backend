<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Reversed = 'reversed';
}
