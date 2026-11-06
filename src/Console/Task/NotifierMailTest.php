<?php

namespace Base\Console\Task;

use App\Entity\User;
use Base\Console\Report;
use Base\Database\UnitOfWorks;
use Base\Notifier\NotifierInterface;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * notifier:mail:test (plan "omnibase agnostic", step F5e, slice 3): a test e-mail sent to an account (by its id,
 * e-mail address or username), else to the technical recipient's account, else to an account made up for the
 * technical recipient's address (not stored), else to the first account.
 */
final class NotifierMailTest
{
    public function __construct(private readonly NotifierInterface $notifier, private readonly UnitOfWork $unitOfWork)
    {
    }

    public function run(NotifierMailTestInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $users = UnitOfWorks::repositories($this->unitOfWork)->of(User::class);
        if ($this->notifier->hasLoopback() && $this->notifier->getTechnicalRecipient()) {
            $report->notice('Notifier loopback is enabled. Notifications will be sent to the technical recipient: '.PHP_EOL.$this->notifier->getTechnicalRecipient());
        }

        $userId = $input->user;
        // The finders answer a query whose result is a list: its first account (the list itself was handed to the
        // notifier, which refused it).
        $user = self::one($users->findByIdOrEmailOrUsername($userId)?->getResult());
        if ($userId && !$user) {
            $report->warning("No user found by ID, email or username using `$userId`.");
        }

        $mail = mailparse($this->notifier->getTechnicalRecipient()->getEmail());
        if (!$user) {
            $user = self::one($users->findByEmail(first($mail))?->getResult());
            if (!$user) {
                $report->warning('No user found in database as technical recipient: '.$this->notifier->getTechnicalRecipient());
                $user = new User();
                // A username where the application's user has one (Base\Entity\User has none).
                $accessor = PropertyAccess::createPropertyAccessor();
                if ($accessor->isWritable($user, 'username')) {
                    $accessor->setValue($user, 'username', first($mail));
                }
                $user->setEmail(first(array_keys($mail)));
                $userId = 0;
            }
        }

        $notification = $this->notifier->testEmail($user);
        $notification->send();
        $name = User::nameOf($user);

        return $report->success("Sent test email to {$user->getEmail()} (Name: {$name}, ID: {$userId}) using async transport.");
    }

    private static function one(mixed $result): ?object
    {
        $one = \is_array($result) ? first($result) : $result;

        return \is_object($one) ? $one : null;
    }
}
