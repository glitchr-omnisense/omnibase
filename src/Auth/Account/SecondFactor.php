<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Screen\Template;
use Base\Service\SecurityPolicy;
use Base\Service\TranslatorInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Omniauth\Contract\TotpSecrets;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Tokens;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The account's authenticator application, written once for every host (plan "omnibase agnostic", step F5e, slice 5b;
 * formerly UserSettingsController's TwoFactorAuthentication*() and displayTotpQrCode()):
 * - a demonstration account changes nothing of its second factor (refused aloud, back to the settings);
 * - turning it on: refused where the administrator switched the feature off, nothing to do where it is on; a secret
 *   kept in the session until a code of the visitor's application confirms it, checked on a copy of the account
 *   (the account itself never carries an unconfirmed secret: another request's flush would keep it); confirmed, the
 *   secret kept (through omniauth's store, sealed at rest where the site says so), eight backup codes shown once;
 * - turning it off: the token, then the administrator's rule (a second factor required stays), then the password;
 * - the QR codes: the secret as the application scans it (opened by omniauth's store), or the pending one.
 *
 * The host's are the form, the TOTP authenticator (generating the secret, checking a code, the QR's content: scheb's
 * under Symfony) and the PNG's answer; every decision is here.
 */
final class SecondFactor
{
    public const PENDING = '2fa_pending_totp_secret';
    public const SETTINGS = 'user_settings';
    public const FORM_TEMPLATE = 'client/user/settings_2fa.html.twig';
    public const CODES_TEMPLATE = 'client/user/settings_2fa_backup_codes.html.twig';
    public const DISABLE_TOKEN = '2fa_disable';
    public const INVALID_CODE = '@notifications.settings.twoFactorInvalidCode';

    public function __construct(
        private readonly Access $access,
        private readonly SecurityPolicy $policy,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?Requests $requests = null,
        private readonly ?Tokens $tokens = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?UserPasswordHasherInterface $passwords = null,
        private readonly ?TotpSecrets $totpSecrets = null,
    ) {
    }

    /** A demonstration account's request refused (null for any other): said so, back to the settings. */
    public function refused(?object $user = null): ?Outcome
    {
        if (!$this->policy->isDemoAccount($user ?? $this->access->getUser())) {
            return null;
        }
        (new Notification('@notifications.demo.locked'))->send('warning');

        return Outcome::route(self::SETTINGS);
    }

    /** Whether the page that turns it on is shown (null) or where the visitor goes instead. */
    public function enrolment(): ?Outcome
    {
        $user = $this->access->getUser();
        if (!$user instanceof User) {
            return Outcome::denied();
        }
        if ($refused = $this->refused($user)) {
            return $refused;
        }
        // The administrator can switch the whole feature off: refused here as well as the button hidden, so a
        // bookmarked address is refused too.
        if (!$this->policy->canEnableTwoFactor()) {
            (new Notification('@notifications.settings.twoFactorUnavailable'))->send('warning');

            return Outcome::route(self::SETTINGS);
        }

        return $user->isTotpAuthenticationEnabled() ? Outcome::route(self::SETTINGS) : null;
    }

    /**
     * The secret waiting for its first code: the session's, else a new one (the host's authenticator makes it), kept.
     *
     * @param \Closure(): string $generate
     */
    public function pending(\Closure $generate): string
    {
        $secret = $this->requests?->session(self::PENDING);
        $secret = \is_string($secret) && '' !== $secret ? $secret : $generate();
        $this->requests?->remember(self::PENDING, $secret);

        return $secret;
    }

    /** The account as the code is checked against it (or its QR drawn): a copy carrying $secret. */
    public static function carrying(User $user, ?string $secret): User
    {
        $copy = clone $user;
        $copy->setTotpSecret($secret);

        return $copy;
    }

    /**
     * The code checked by the host's authenticator on carrying(): turned on, its backup codes shown once (an Outcome);
     * a wrong code, null - the form says so (INVALID_CODE, invalidCode()).
     */
    public function confirm(User $user, string $secret, bool $codeValid): ?Outcome
    {
        if (!$codeValid) {
            return null;
        }
        // Kept through omniauth's store when there is one: sealed at rest when the site sets omniauth.yaml's
        // totp.secret_key (Marco's decision QA5), as it is otherwise.
        if (null !== $this->totpSecrets) {
            $this->totpSecrets->enable($user, $secret);
        } else {
            $user->setTotpSecret($secret);
        }
        $codes = self::backupCodes();
        $user->setBackupCodes($codes);
        $this->unitOfWork->flush();
        $this->requests?->forget(self::PENDING);
        (new Notification('@notifications.settings.twoFactorEnabled'))->send('success');

        return Outcome::page(new Template(self::CODES_TEMPLATE, ['backupCodes' => $codes]));
    }

    /** What the form says of a wrong code. */
    public function invalidCode(): string
    {
        return (string) ($this->translator?->trans(self::INVALID_CODE) ?? self::INVALID_CODE);
    }

    /**
     * Turned off: refused to a demonstration account before anything is checked, then the token, then the
     * administrator's rule (while a second factor is required, the account cannot give its up, password or not),
     * then the password; the secret, the backup codes and the trusted devices forgotten.
     */
    public function disable(?string $token, ?string $password): Outcome
    {
        $user = $this->access->getUser();
        if ($refused = $this->refused($user)) {
            return $refused;
        }
        if (!$user instanceof User || !$this->tokens?->isValid(self::DISABLE_TOKEN, $token)) {
            return Outcome::denied();
        }
        if (!$this->policy->canDisableTwoFactor($user)) {
            (new Notification('@notifications.settings.twoFactorMandatory'))->send('warning');

            return Outcome::route(self::SETTINGS);
        }
        if (!$this->passwords?->isPasswordValid($user, (string) $password)) {
            (new Notification('@notifications.settings.wrongPassword'))->send('danger');

            return Outcome::route(self::SETTINGS);
        }
        $user->setTotpSecret(null);
        $user->setBackupCodes([]);
        $user->invalidateTrustedDevices();
        $this->unitOfWork->flush();
        (new Notification('@notifications.settings.twoFactorDisabled'))->send('success');

        return Outcome::route(self::SETTINGS);
    }

    /** The account as its application scans it (the secret opened by omniauth's store), null where it has none on. */
    public function enabled(): ?User
    {
        $user = $this->access->getUser();
        if (!$user instanceof User || !$user->isTotpAuthenticationEnabled()) {
            return null;
        }

        return self::carrying($user, null !== $this->totpSecrets ? $this->totpSecrets->secret($user) : $user->getTotpSecret());
    }

    /** The account carrying the secret waiting for its first code, null where none waits. */
    public function waiting(): ?User
    {
        $user = $this->access->getUser();
        $secret = $this->requests?->session(self::PENDING);

        return $user instanceof User && \is_string($secret) && '' !== $secret ? self::carrying($user, $secret) : null;
    }

    /** A QR code of $content, as a PNG (200 pixels, no margin). */
    public static function png(string $content): string
    {
        return (new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }

    /** @return list<string> plain-text codes (only ever shown once, right after they are made) */
    public static function backupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $codes[] = strtoupper(bin2hex(random_bytes(3))).'-'.strtoupper(bin2hex(random_bytes(3)));
        }

        return $codes;
    }
}
