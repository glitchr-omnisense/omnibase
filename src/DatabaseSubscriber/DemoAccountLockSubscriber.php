<?php

namespace Base\DatabaseSubscriber;

use Base\Demo\DemoAccountLock;
use Base\Demo\DemoAccountRegistry;
use Base\Entity\User;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A demonstration account stays what its declaration says, whoever asks and
 * through whichever form (registered in the `demo` environment only): its
 * identifier, its address, its password, its second factor are put back
 * before anything is written, it is not disabled, it is not deleted.
 *
 * The pages of omnibase that change these refuse first, with a message
 * (SecurityPolicy::isDemoAccount()); this is what holds for the others - the
 * application's own "my account" page, the back office's users. A visitor
 * signed in as "docteur" who could change that password would lock the next
 * one out until the night's reset.
 *
 * Only during a request: the fixtures and `demo:reset` write these accounts
 * from the console.
 *
 * The rule itself is Base\Demo\DemoAccountLock's (plan "omnibase agnostic",
 * step F5a); this listener is its Doctrine and Symfony side - Doctrine's
 * identity map and original rows, the request, the flash message - and
 * leaves for omnibridge/doctrine and omnibridge/symfony with them.
 */
class DemoAccountLockSubscriber
{
    private bool $told = false;

    private readonly DemoAccountLock $lock;

    public function __construct(
        DemoAccountRegistry $accounts,
        private readonly RequestStack $requestStack,
        PasswordHasherFactoryInterface $hashers,
        ?TranslatorInterface $translator = null,
        ?DemoAccountLock $lock = null,
    ) {
        $this->lock = $lock ?? new DemoAccountLock($accounts, $hashers, $translator);
    }

    public function preFlush(PreFlushEventArgs $event): void
    {
        if (null === $this->requestStack->getMainRequest()) {
            return;
        }

        $manager = $event->getObjectManager();
        $unitOfWork = $manager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof User && $this->lock->declarationOf($entity, $unitOfWork->getOriginalEntityData($entity))) {
                throw new AccessDeniedHttpException($this->lock->message('deleted'));
            }
        }

        $refused = false;
        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (!$entity instanceof User) {
                    continue;
                }
                $original = $unitOfWork->getOriginalEntityData($entity);
                if ($account = $this->lock->declarationOf($entity, $original)) {
                    $metadata = $manager->getClassMetadata($entity::class);
                    $refused = $this->lock->restore(
                        $entity,
                        $account,
                        $original,
                        $metadata->getFieldNames(),
                        static fn (string $field): mixed => $metadata->getFieldValue($entity, $field),
                        static function (string $field, mixed $value) use ($metadata, $entity): void {
                            $metadata->setFieldValue($entity, $field, $value);
                        },
                    ) || $refused;
                }
            }
        }

        if ($refused) {
            $this->tell();
        }
    }

    private function tell(): void
    {
        if ($this->told) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        if (!$request?->hasSession()) {
            return;
        }

        $request->getSession()->getFlashBag()->add('warning', $this->lock->message('locked'));
        $this->told = true;
    }

    /** One message a request: the service lives across requests in a worker. */
    public function reset(): void
    {
        $this->told = false;
    }
}
