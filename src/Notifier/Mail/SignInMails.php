<?php

namespace Base\Notifier\Mail;

use Base\Entity\User;
use Base\Entity\User\Connection;
use Base\Entity\User\Token;
use Base\Service\Model\Duration;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The e-mails of signing in - the sign-in link, the second factor's code,
 * "welcome back" to a disabled account, a sign-in from a new device - with
 * the words and templates omnibase's Notifier gives them (plan "omniauth",
 * step AU8b; R26 of the master plan's gaps).
 *
 * Their words are said once, here: Base\Notifier\Notifier::loginLink(),
 * emailCode() and userWelcomeBack() take their template's parameters from
 * the *Parameters() functions, as Base\Auth\Listener\WarnNewDevice takes the
 * new device's template. Under Symfony the Notifier sends them (its
 * channels, its loopback); under a host without Symfony's Notifier
 * (omnibridge/laravel) this class writes them with EmailComposer - the same
 * template, parameters, frame and subject - and sends them through
 * symfony/mailer's MailerInterface, the contract both hosts' mailers answer
 * (Laravel's mail is Symfony's Mailer: omnibridge/laravel's adapter).
 */
final class SignInMails
{
    public const LINK_TEMPLATE = 'email.html.twig';
    public const NEW_DEVICE_TEMPLATE = '@Base/client/email/security_newDevice.html.twig';
    public const LINK_ROUTE = 'security_loginWithToken';
    public const WELCOME_BACK_ROUTE = 'security_accountWelcomeBackWithToken';

    public function __construct(
        private readonly EmailComposer $composer,
        private readonly MailerInterface $mailer,
        private readonly Translations $translations,
        private readonly Urls $urls,
    ) {
    }

    /**
     * The sign-in link's template parameters (Notifier::loginLink()).
     *
     * @param \Closure(string, array<int|string, mixed>=): string $trans
     * @param \Closure(int): string                               $duration a duration spelled (Translator::transTime())
     *
     * @return array<string, string>
     */
    public static function loginLinkParameters(\Closure $trans, \Closure $duration, string $url, \DateTimeInterface $until): array
    {
        return [
            "subject" => $trans("@emails.loginToken.subject"),
            "content" => $trans("@emails.loginToken.content"),
            "action_text" => $trans("@emails.loginToken.action_text"),
            "action_url" => $url,
            "footer_text" => $trans("@emails.loginToken.expiry", [$duration(max(1, $until->getTimestamp() - time()))]),
        ];
    }

    /**
     * The second factor's code's template parameters (Notifier::emailCode()).
     *
     * @param \Closure(string, array<int|string, mixed>=): string $trans
     * @param \Closure(int): string                               $duration
     *
     * @return array<string, string>
     */
    public static function emailCodeParameters(\Closure $trans, \Closure $duration, string $code, \DateTimeInterface $until): array
    {
        return [
            "subject" => $trans("@emails.emailCode.subject"),
            "content" => $trans("@emails.emailCode.content", [$code]),
            "footer_text" => $trans("@emails.emailCode.expiry", [$duration(max(1, $until->getTimestamp() - time()))]),
        ];
    }

    /**
     * "Welcome back"'s template parameters (Notifier::userWelcomeBack()).
     *
     * @param \Closure(string, array<int|string, mixed>=): string $trans
     *
     * @return array<string, string>
     */
    public static function welcomeBackParameters(\Closure $trans, User $user, string $url): array
    {
        // Both texts name the account ("Bon retour, {0} !", "Bienvenue {0} !"): given their parameter.
        return [
            "subject" => $trans("@emails.accountWelcomeBack.subject", [$user]),
            "content" => $trans("@emails.accountWelcomeBack.content"),
            "action_text" => $trans("@emails.accountWelcomeBack.action_text"),
            "action_url" => $url,
        ];
    }

    /**
     * The new device's template parameters (WarnNewDevice).
     *
     * @return array<string, mixed>
     */
    public static function newDeviceParameters(User $user, Connection $connection): array
    {
        return ['connection' => $connection, 'recipient' => $user];
    }

    public function loginLink(User $user, string $token, \DateTimeInterface $until): void
    {
        $this->send($user, self::LINK_TEMPLATE, self::loginLinkParameters($this->trans(...), $this->duration(...), $this->urls->url(self::LINK_ROUTE, ['token' => $token]), $until), $this->trans('@notifications.loginToken.confirmation'));
    }

    public function emailCode(User $user, string $code, \DateTimeInterface $until): void
    {
        $this->send($user, self::LINK_TEMPLATE, self::emailCodeParameters($this->trans(...), $this->duration(...), $code, $until), $this->trans('@notifications.emailCode.subject'));
    }

    public function welcomeBack(User $user, Token $token): void
    {
        $this->send($user, self::LINK_TEMPLATE, self::welcomeBackParameters($this->trans(...), $user, $this->urls->url(self::WELCOME_BACK_ROUTE, ['token' => $token->get()])), $this->trans('@notifications.accountWelcomeBack.success', [$user]));
    }

    public function newDevice(User $user, Connection $connection): void
    {
        $this->send($user, self::NEW_DEVICE_TEMPLATE, self::newDeviceParameters($user, $connection), $this->trans('@notifications.newDevice.subject', [$user]));
    }

    /** @param array<string, mixed> $parameters */
    private function send(User $user, string $template, array $parameters, string $content): void
    {
        $email = $user->getEmail();
        if (!\is_string($email) || '' === $email) {
            return;
        }
        // The address as the account's own Notifier recipient writes it (User::getRecipient(), which an
        // application overrides: lesringostars names its members by their full name).
        $this->mailer->send($this->composer->compose($user->getRecipient()->getEmail(), $template, $parameters, $content));
    }

    /** @param array<int|string, mixed> $parameters */
    private function trans(string $id, array $parameters = []): string
    {
        return $this->translations->trans($id, array_map(static fn (mixed $p) => $p instanceof \Stringable ? (string) $p : $p, $parameters));
    }

    private function duration(int $seconds): string
    {
        return Duration::spell($seconds, $this->translations);
    }
}
