<?php

namespace Base\Traits;

use Omnibridge\Doctrine\Omnibase\DoctrineLocator;
use Base\Bridge\Symfony\FrameworkLocator;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

/**
 * BaseTrait's accessors of the framework's services, from before BaseTrait
 * named contracts only (plan "omnibase agnostic", step F2f). BaseTrait still
 * uses this trait, so that the bundles and the applications that call them
 * keep working; each call is deprecated (E_USER_DEPRECATED) and answers as
 * before - the service, or null where the host has none.
 *
 * What to call instead, in an application or a bundle:
 * - getRouter()->generate($name, $parameters, $referenceType): Base\Contract\Link::to()
 *   (the same string under Symfony), or Runtime::urls();
 * - getTwig(): Omnibridge\Contract\Views (Runtime::views()) where the host answers it;
 * - getEntityManager(), getDoctrine(), getObjectManager(), getRepository(): the unit of
 *   work (Runtime::unitOfWork(), an injected Omnibridge\Persistence\UnitOfWork), the
 *   repositories by interface or Base\Database\UnitOfWorks::repositories();
 * - getClassMetadataManipulator(): Base\Database\UnitOfWorks::shapes(); BaseTrait::isEntity();
 * - getTokenStorage(), getRequestStack(), getFirewallMap(): Runtime::access(),
 *   Runtime::sessions(), Runtime::routes();
 * - getService(): the contracts the facade answered (Runtime::environment(), ::access()...);
 * - getSlugger(): symfony/string's AsciiSlugger, or an injected slugger.
 * Host code of the core and the Symfony half of a bundle: omnibridge/symfony's Omnibridge\Symfony\Omnibase\FrameworkLocator
 * (formerly Base\Bridge\Symfony\FrameworkLocator, step F5c; each accessor answers null without the bridge),
 * the same services without the deprecation.
 *
 * @deprecated since omnisense/omnibase 4.0 (F2f): removed with the former names at 6.0, after
 *             the sites have moved (step S of the plan).
 */
trait FrameworkAccessorsTrait
{
    private static function deprecatedAccessor(string $accessor, string $instead): void
    {
        @trigger_error(sprintf('Since omnisense/omnibase 4.0: BaseTrait::%s() is deprecated, BaseTrait names contracts only (step F2f); use %s.', $accessor, $instead), \E_USER_DEPRECATED);
    }

    /** @deprecated since omnisense/omnibase 4.0: the contracts the facade answered */
    public static function getService(): ?BaseService
    {
        self::deprecatedAccessor('getService', 'the contracts the facade answered (Runtime::environment(), Runtime::access()...)');

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::service() : null;
    }

    /** @deprecated since omnisense/omnibase 4.0: the unit of work, the repositories */
    public static function getDoctrine(): ?ManagerRegistry
    {
        self::deprecatedAccessor('getDoctrine', 'the unit of work (Runtime::unitOfWork()) and the repositories');

        return DoctrineLocator::doctrine();
    }

    /** @deprecated since omnisense/omnibase 4.0: the unit of work */
    public static function getObjectManager(mixed $entity): ?ObjectManager
    {
        self::deprecatedAccessor('getObjectManager', 'the unit of work (Runtime::unitOfWork())');

        return DoctrineLocator::objectManager($entity);
    }

    /** @deprecated since omnisense/omnibase 4.0: the unit of work */
    public static function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        self::deprecatedAccessor('getEntityManager', 'the unit of work (Runtime::unitOfWork())');

        return DoctrineLocator::entityManager($reopen);
    }

    /** @deprecated since omnisense/omnibase 4.0: the repository by its interface, or UnitOfWorks::repositories() */
    public static function getRepository(mixed $object = null): ?ObjectRepository
    {
        self::deprecatedAccessor('getRepository', 'the repository by its interface, or Base\Database\UnitOfWorks::repositories()');

        return DoctrineLocator::repository($object ?? static::class);
    }

    /** @deprecated since omnisense/omnibase 4.0: UnitOfWorks::shapes(), BaseTrait::isEntity() */
    public static function getClassMetadataManipulator(): ?ClassMetadataManipulator
    {
        self::deprecatedAccessor('getClassMetadataManipulator', 'Base\Database\UnitOfWorks::shapes() or BaseTrait::isEntity()');

        return DoctrineLocator::classMetadataManipulator();
    }

    /** @deprecated since omnisense/omnibase 4.0: Runtime::access() */
    public static function getTokenStorage(): ?TokenStorageInterface
    {
        self::deprecatedAccessor('getTokenStorage', 'Runtime::access()');

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::tokenStorage() : null;
    }

    /** @deprecated since omnisense/omnibase 4.0: Runtime::sessions(), Runtime::locales() */
    public static function getRequestStack(): ?RequestStack
    {
        self::deprecatedAccessor('getRequestStack', 'Runtime::sessions() or Runtime::locales()');

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::requestStack() : null;
    }

    /** @deprecated since omnisense/omnibase 4.0: Runtime::routes() */
    public static function getFirewallMap(): ?FirewallMapInterface
    {
        self::deprecatedAccessor('getFirewallMap', 'Runtime::routes()');

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::firewallMap() : null;
    }

    /** @deprecated since omnisense/omnibase 4.0: Omnibridge\Contract\Views (Runtime::views()) */
    public static function getTwig(): ?Environment
    {
        self::deprecatedAccessor('getTwig', 'Omnibridge\Contract\Views (Runtime::views())');

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::twig() : null;
    }

    /** @deprecated since omnisense/omnibase 4.0: symfony/string's AsciiSlugger, or an injected slugger */
    public static function getSlugger(): ?SluggerInterface
    {
        self::deprecatedAccessor('getSlugger', "symfony/string's AsciiSlugger, or an injected slugger");

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::slugger() : null;
    }

    /** @deprecated since omnisense/omnibase 4.0: Base\Contract\Link::to(), Runtime::urls() */
    public static function getRouter(): ?AdvancedRouterInterface
    {
        self::deprecatedAccessor('getRouter', 'Base\Contract\Link::to() or Runtime::urls()');

        return class_exists(FrameworkLocator::class) ? FrameworkLocator::router() : null;
    }
}
