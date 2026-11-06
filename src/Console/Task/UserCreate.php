<?php

namespace Base\Console\Task;

use App\Entity\User;
use Base\Console\Report;
use Base\Database\UnitOfWorks;
use Base\Entity\User\Token;
use Base\Enum\UserRole;
use Base\Notifier\NotifierInterface;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * user:create (plan "omnibase agnostic", step F5e, slice 3): an account made from the command line, verified and
 * approved, with a password it did not choose - generated and shown once, or given -, to be changed at its first
 * sign-in (a PASSWORD_CHANGE token, which omnibridge/symfony's PasswordChangeSubscriber asks) unless kept; e-mailed
 * its username and password (Notifier::accountCreated) unless not.
 */
final class UserCreate
{
    /** The token an account must change its password under (omnibridge/symfony's PasswordChangeSubscriber::TOKEN). */
    public const PASSWORD_CHANGE = 'change-password';

    /** The generated password's alphabet: letters and digits without the ones read alike (0/O, 1/l/I). */
    public const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly UnitOfWork $unitOfWork, private readonly ?NotifierInterface $notifier = null)
    {
    }

    public function run(UserCreateInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        if (!$input->noEmail && null === $this->notifier) {
            throw new \LogicException('user:create e-mails the account through omnibase\'s Notifier, which this host has not: pass --no-email.');
        }
        $users = UnitOfWorks::repositories($this->unitOfWork)->of(User::class);
        if ($users->findOneBy(['email' => $input->email]) || (property_exists(User::class, 'username') && $users->findOneBy(['username' => $input->username]))) {
            $report->error(\sprintf('An account already has the username "%s" or the address %s.', $input->username, $input->email));

            return $report->fail();
        }

        $roles = [];
        foreach ($input->roles as $role) {
            $role = strtoupper((string) preg_replace('/^ROLE_/i', '', (string) $role));
            if (!UserRole::hasKey($role)) {
                $report->error(\sprintf('Unknown role "%s".', $role));

                return $report->fail();
            }
            $roles[] = UserRole::getValue($role);
        }

        // Generated: four groups of four.
        $password = $input->password ?: implode('-', array_map(static fn () => implode('', array_map(static fn () => self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)], range(1, 4))), range(1, 4)));

        $user = new User();
        // A username where the application's user has one (Base\Entity\User has none).
        $accessor = PropertyAccess::createPropertyAccessor();
        if ($accessor->isWritable($user, 'username')) {
            $accessor->setValue($user, 'username', $input->username);
        }
        $user->setEmail($input->email);
        $user->setRoles($roles);
        $user->setPlainPassword($password);
        $user->verify();
        $user->approve();
        if (!$input->keepPassword) {
            (new Token(self::PASSWORD_CHANGE))->setUser($user);
        }

        $this->unitOfWork->persist($user);
        $this->unitOfWork->flush();

        // Its owner told: username, temporary password, the way in.
        if (!$input->noEmail) {
            $this->notifier->sendAccountCreated($user, $password);
        }

        $report->success(\sprintf('%s (%s), %s%s.', $input->username, $input->email, implode(', ', $roles), $input->noEmail ? '' : ' - e-mailed'));

        return $report->line(\sprintf('Password: <info>%s</info>%s', $password, $input->keepPassword ? '' : ' - to be changed at the first sign-in'));
    }
}
