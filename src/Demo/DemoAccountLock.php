<?php

namespace Base\Demo;

use Base\Entity\User;
use Base\Enum\UserState;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A demonstration account stays what its declaration says (plan "omnibase
 * agnostic", step F5a: the logic of Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\DemoAccountLockSubscriber,
 * on no persistence nor host): its identifier, its address, its password,
 * its second factor are put back as the database had them, it is not
 * disabled. The persistence's listener gives each account's original row and
 * how to read and write its columns; the host says the message.
 */
final class DemoAccountLock
{
    /** The columns put back as they were (the identifier's and the password's are handled apart). */
    public const LOCKED_FIELDS = ['email', 'secret', 'backupCodes', 'emailAuthEnabled'];

    public function __construct(
        private readonly DemoAccountRegistry $accounts,
        private readonly PasswordHasherFactoryInterface $hashers,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * The declaration of this account as the database knows it: by the identifier it was loaded with, not the one just typed.
     *
     * @param array<string, mixed> $original the row as loaded, empty for an account not written yet
     */
    public function declarationOf(User $user, array $original): ?DemoAccount
    {
        if (!$original) {
            return null; // not written yet: an account being created is nobody's demonstration account
        }

        return $this->accounts->get((string) ($original[$user::getUserIdentifierField()] ?? ''));
    }

    /**
     * Puts back what may not change; true when something had.
     *
     * @param array<string, mixed>                 $original the row as loaded
     * @param list<string>                         $fields   the columns the account's class maps
     * @param \Closure(string $field): mixed       $read     a column's value on the object
     * @param \Closure(string $field, mixed): void $write    a column's value put back on the object
     */
    public function restore(User $user, DemoAccount $account, array $original, array $fields, \Closure $read, \Closure $write): bool
    {
        $refused = false;
        foreach (array_unique([$user::getUserIdentifierField(), ...self::LOCKED_FIELDS]) as $field) {
            if (!\in_array($field, $fields, true) || !\array_key_exists($field, $original)) {
                continue;
            }
            if ($read($field) != $original[$field]) {
                $write($field, $original[$field]);
                $refused = true;
            }
        }

        // A new password typed in a form (hashed when the row is written, #[Hashify])...
        $plain = $user->getPlainPassword();
        if (null !== $plain && '' !== $plain) {
            $user->erasePlainPassword();
            $refused = $refused || $plain !== $account->getPassword();
        }

        // ...or a hash set directly. The same password hashed again (a stronger algorithm, at sign-in) is no change.
        if (\array_key_exists('password', $original) && $user->getPassword() !== $original['password']) {
            $hash = (string) $user->getPassword();
            if ('' === $hash || !$this->hashers->getPasswordHasher($user)->verify($hash, $account->getPassword())) {
                $write('password', $original['password']);
                $refused = true;
            }
        }

        // Disabled ("delete my account") while it was not.
        $states = (array) ($original['states'] ?? []);
        if (\in_array(UserState::ENABLED, $states) && $user->isDisabled()) {
            $user->enable();
            $refused = true;
        }

        return $refused;
    }

    /** The words of a refusal: "deleted" (an account cannot be deleted), "locked" (what was changed was put back). */
    public function message(string $key): string
    {
        return $this->translator?->trans('@notifications.demo.'.$key) ?? 'A demonstration account cannot be changed.';
    }
}
