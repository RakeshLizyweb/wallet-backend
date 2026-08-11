<?php

namespace App\Providers;

use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\AccountTransactionRepositoryInterface;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use App\Repositories\Contracts\IdentityVerificationRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\ReferralRepositoryInterface;
use App\Repositories\Contracts\ScratchCardRepositoryInterface;
use App\Repositories\Contracts\TransferRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VirtualCardRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Contracts\WalletTransactionRepositoryInterface;
use App\Repositories\Eloquent\AccountRepository;
use App\Repositories\Eloquent\AccountTransactionRepository;
use App\Repositories\Eloquent\BankAccountRepository;
use App\Repositories\Eloquent\DeviceRepository;
use App\Repositories\Eloquent\IdentityVerificationRepository;
use App\Repositories\Eloquent\OtpRepository;
use App\Repositories\Eloquent\ReferralRepository;
use App\Repositories\Eloquent\ScratchCardRepository;
use App\Repositories\Eloquent\TransferRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VirtualCardRepository;
use App\Repositories\Eloquent\WalletRepository;
use App\Repositories\Eloquent\WalletTransactionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * The repository bindings for the application. Laravel auto-registers
     * a public $bindings property on service providers.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        DeviceRepositoryInterface::class => DeviceRepository::class,
        WalletRepositoryInterface::class => WalletRepository::class,
        WalletTransactionRepositoryInterface::class => WalletTransactionRepository::class,
        AccountRepositoryInterface::class => AccountRepository::class,
        AccountTransactionRepositoryInterface::class => AccountTransactionRepository::class,
        BankAccountRepositoryInterface::class => BankAccountRepository::class,
        TransferRepositoryInterface::class => TransferRepository::class,
        ScratchCardRepositoryInterface::class => ScratchCardRepository::class,
        ReferralRepositoryInterface::class => ReferralRepository::class,
        IdentityVerificationRepositoryInterface::class => IdentityVerificationRepository::class,
        VirtualCardRepositoryInterface::class => VirtualCardRepository::class,
    ];
}
