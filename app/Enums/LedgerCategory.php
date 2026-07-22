<?php

namespace App\Enums;

enum LedgerCategory: string
{
    case WalletToWallet = 'wallet_to_wallet';
    case WalletToBank = 'wallet_to_bank';
    case BankToWallet = 'bank_to_wallet';
    case Reward = 'reward';
    case Refund = 'refund';
    case Fee = 'fee';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}
