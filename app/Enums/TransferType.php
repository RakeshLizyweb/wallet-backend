<?php

namespace App\Enums;

enum TransferType: string
{
    case WalletToWallet = 'wallet_to_wallet';
    case WalletToBank = 'wallet_to_bank';
    case BankToWallet = 'bank_to_wallet';
    case AccountToAccount = 'account_to_account';
    case AccountToWallet = 'account_to_wallet';
}
