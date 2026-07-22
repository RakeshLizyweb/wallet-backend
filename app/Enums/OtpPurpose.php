<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case Registration = 'registration';
    case Login = 'login';
    case ResetPin = 'reset_pin';
    case DeleteAccount = 'delete_account';
    case AdminPasswordReset = 'admin_password_reset';
}
