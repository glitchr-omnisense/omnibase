<?php

namespace Base\Contract;

/*
 * What Base\Entity\User declares to a two-factor sign-in (plan "omnibase
 * agnostique", C7b): with scheb/2fa installed, Base\Bridge\Symfony\TwoFactorUser
 * - scheb/2fa's TOTP and e-mail TwoFactorInterface, BackupCodeInterface and
 * TrustedDeviceInterface -, under this name; elsewhere (no scheb/2fa, or a host
 * that runs without the framework, see Base\Contract\Framework) an interface
 * that asks nothing. The user's methods (the TOTP secret and configuration,
 * the e-mail code, the backup codes, the trusted token's version) are the same
 * either way; getTotpAuthenticationConfiguration() answers null without
 * scheb/2fa's TotpConfiguration.
 */
if (Framework::declares(
    'Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface',
    'Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface',
    'Scheb\TwoFactorBundle\Model\BackupCodeInterface',
    'Scheb\TwoFactorBundle\Model\TrustedDeviceInterface',
)) {
    class_alias(\Base\Bridge\Symfony\TwoFactorUser::class, TwoFactorUser::class);
} else {
    /** A user, to a host that has no two-factor sign-in to declare it to. */
    interface TwoFactorUser
    {
    }
}
