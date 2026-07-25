<?php

return [

    'currency' => env('WALLET_CURRENCY', 'XOF'),

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    ],

    'pin' => [
        'length' => (int) env('PIN_LENGTH', 6),
        'max_attempts' => (int) env('PIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('PIN_LOCKOUT_MINUTES', 15),
    ],

    'qr' => [
        'domain' => env('WALLET_QR_DOMAIN', 'wallet'),
    ],

    'limits' => [
        'basic' => [
            'monthly' => (float) env('LIMIT_BASIC_MONTHLY', 150000),
        ],
        'verified' => [
            'monthly' => (float) env('LIMIT_VERIFIED_MONTHLY', 1000000),
        ],
        'premium' => [
            'monthly' => null,
        ],
    ],

    // wallet_to_wallet and account_to_account are peer payments to another
    // user: the fee is deducted from what the recipient receives (sender
    // pays exactly the amount they typed). account_to_wallet is a self-move
    // and wallet_to_bank is a withdrawal — neither carries this platform fee.
    'fees' => [
        'wallet_to_wallet' => (float) env('FEE_WALLET_TO_WALLET', 0.01),
        'wallet_to_bank' => (float) env('FEE_WALLET_TO_BANK', 0.005),
        'bank_to_wallet' => (float) env('FEE_BANK_TO_WALLET', 0),
        'account_to_account' => (float) env('FEE_ACCOUNT_TO_ACCOUNT', 0.01),
        'account_to_wallet' => (float) env('FEE_ACCOUNT_TO_WALLET', 0),
    ],

    'rewards' => [
        'cashback_rate' => (float) env('REWARD_CASHBACK_RATE', 0.01),
        'points_per_transaction' => (int) env('REWARD_POINTS_PER_TXN', 10),
        'min_transaction_for_reward' => (float) env('REWARD_MIN_TXN_AMOUNT', 100),
    ],

    'verification' => [
        'passport_expiry_buffer_months' => (int) env('PASSPORT_EXPIRY_BUFFER_MONTHS', 6),
    ],
];
