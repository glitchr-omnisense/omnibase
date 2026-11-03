<?php

namespace Base\Service;

use App\Entity\User;
use Base\Contract\Runtime;
use Base\Database\Entity\EntityHydrator;
use Base\Exception\MissingContractException;
use Base\Service\Model\DevelopmentEnvironment;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment as EnvironmentContract;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The facade of omnibase's services for an application's code (plan "omnibase
 * agnostique", C9): kept, not deprecated, with BaseTrait. Framework-neutral
 * since the plan "omnibase agnostic", step F5c, item 3: its constructor takes
 * nothing of a framework, every host builds it from the core's list
 * (Base\Services), and each method asks omnisense/omnibridge's contracts
 * through Base\Contract\Runtime - the settings, the request's session and
 * route (Requests), the addresses and assets (Urls), the environment
 * (Environment), the visitor (Access), the forms' tokens (Tokens), the unit of
 * work (UnitOfWork), the entity hydrator.
 *
 * What only a framework has - its kernel and container, its console, its form
 * factory, its profiler, its request, route and response objects - is the
 * host's facade, a subclass of this one: under Symfony omnibridge/symfony's
 * Omnibridge\Symfony\Omnibase\Service\SymfonyBaseService, which the container
 * hands out as `base.service` and Base\Service\BaseService, so the
 * applications' code and templates calling those methods keep them, with
 * Symfony's services as the fallback of the methods below.
 *
 * Under Symfony it is a lazy service - a ghost of the class until one of its
 * methods reads a property: the methods a contract answers read none of its
 * properties, called on the ghost they build nothing.
 *
 * It no longer declares Twig's RuntimeExtensionInterface (plan "omnibase
 * agnostic", F4: Twig is a suggestion of the core): no Twig function was ever
 * answered by it, and the marker made the class unloadable without Twig.
 */
class BaseService extends Locator
{
    /**
     * @param EnvironmentContract|null   $environment  what the application runs as (its name kept for BaseTrait's getEnvironment())
     * @param ParameterBagInterface|null $parameterBag the parameters (kept for BaseTrait's getParameterBag(); base.user.identifier read)
     * @param string|null                $projectDir   the application's folder
     * @param float|null                 $startTime    when the request (or the process) started; now by default
     */
    public function __construct(
        ?EnvironmentContract $environment = null,
        ?ParameterBagInterface $parameterBag = null,
        ?string $projectDir = null,
        ?float $startTime = null,
    ) {
        $this->setInstance($this);
        $this->startTime($startTime);

        if (null !== $projectDir) {
            $this->setProjectDir($projectDir);
        }
        if (null !== $environment) {
            $this->setEnvironment($environment->name());
        }
        if (null !== $parameterBag) {
            $this->setParameterBag($parameterBag);
            if (\is_string($identifier = $parameterBag->get('base.user.identifier'))) {
                $this->setUserIdentifier($identifier);
            }
        }
    }

    /**
     * @return array
     */
    public function getSite()
    {
        return [
            "title" => $this->getSettingBag()->getScalar("base.settings.title"),
            "slogan" => $this->getSettingBag()->getScalar("base.settings.slogan"),
            "logo" => $this->getSettingBag()->getScalar("base.settings.logo")
        ];
    }

    /**
     * @return array
     */
    public function getAdmin()
    {
        return [
            "title" => $this->getSettingBag()->getScalar("base.settings.title.admin"),
            "slogan" => $this->getSettingBag()->getScalar("base.settings.slogan.admin"),
            "logo" => $this->getSettingBag()->getScalar("base.settings.logo.admin")
        ];
    }

    /**
     * @return array
     */
    public function getEmail()
    {
        return [
            "title" => $this->getSettingBag()->getScalar("base.settings.title.email") ?? $this->getSettingBag()->getScalar("base.settings.title"),
            "slogan" => $this->getSettingBag()->getScalar("base.settings.slogan.email") ?? $this->getSettingBag()->getScalar("base.settings.slogan"),
            "logo" => $this->getSettingBag()->getScalar("base.settings.logo.email") ?? $this->getSettingBag()->getScalar("base.settings.logo"),
            "address" => $this->getSettingBag()->getScalar("base.settings.mail.contact")
        ];
    }

    public function getMeta(?string $locale = null): array
    {
        return $this->getSettingBag()->get("base.settings.meta", $locale) ?? [];
    }

    /**
     * @return SettingBag|null
     */
    public function settings()
    {
        return $this->getSettingBag();
    }

    /*
     * How long the request (or the process) has run.
     */
    protected float $startTime = 0;

    public function getExecutionTime(): float
    {
        return round(microtime(true) - $this->startTime, 2);
    }

    public function startTime(?float $startTime = null)
    {
        $this->startTime = $startTime ?? 0;
        if (!$this->startTime || is_infinite($this->startTime)) {
            $this->startTime = microtime(true);
        }
    }

    /**
     * @return bool
     */
    public function hasGet()
    {
        return isset($_GET);
    }

    /**
     * @return bool
     */
    public function hasPost()
    {
        return isset($_POST);
    }

    /**
     * @return bool
     */
    public function hasSession()
    {
        return isset($_SESSION);
    }

    /** The request being answered, through the Requests contract: null outside one, or where the host binds none. */
    protected static function requests(): ?Requests
    {
        $requests = Runtime::find(Requests::class);

        return ($requests && $requests->has()) ? $requests : null;
    }

    /**
     * A value kept in the visitor's session (Requests::remember()); nothing outside a request.
     *
     * @param $name
     * @param $value
     * @return void
     */
    public function addSession($name, $value)
    {
        self::requests()?->remember($name, $value);
    }

    /**
     * A value of the visitor's session, taken out of it (Requests::session(), forget()).
     *
     * @param $name
     * @return mixed|null
     */
    public function removeSession($name)
    {
        if (!$requests = self::requests()) {
            return null;
        }

        $value = $requests->session($name);
        $requests->forget($name);

        return $value;
    }

    /**
     * A value of the visitor's session (Requests::session()). Without a name, the host's session object, which
     * only the host's facade hands out (Symfony's, omnibridge/symfony): none here.
     *
     * @param $name
     * @return mixed|null
     */
    public function getSession($name = null)
    {
        if (!$name) {
            return null;
        }

        return self::requests()?->session($name);
    }

    /**
     * @param string|null $locale
     * @return string
     */
    public function getLocale(?string $locale = null)
    {
        return self::getLocalizer()->getLocale($locale);
    }

    /**
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public function getSalt()
    {
        return $this->getSecret();
    }

    /**
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public function getSecret()
    {
        return Runtime::find(EnvironmentContract::class)?->secret() ?? $this->getParameterBag("kernel.secret");
    }

    /** A parameter of the application, from its parameters (the host's facade reads its container's). */
    public function getParameter(string $name): array|bool|string|int|float|null
    {
        $value = $this->getParameterBag($name);

        return $value instanceof \UnitEnum ? null : $value;
    }

    public function hasParameter(string $name): bool
    {
        return (bool) self::findParameterBag()?->has($name);
    }

    /** An asset's address (Urls::asset()). */
    public function getAsset(string $url): string
    {
        $urls = Runtime::find(Urls::class) ?? throw MissingContractException::for(Urls::class);

        return $urls->asset($url);
    }

    /** The route of the request being answered (Requests::route()), null outside one. */
    public function getCurrentRouteName(): ?string
    {
        return self::requests()?->route();
    }

    /** A route's address, its absolute path (Urls::path()). */
    public function generateUrl(string $routeName, array $routeParameters = []): string
    {
        $urls = Runtime::find(Urls::class) ?? throw MissingContractException::for(Urls::class);

        return $urls->path($routeName, $routeParameters);
    }

    /**
     * @return bool
     */
    public function isDevelopment()
    {
        if ($environment = Runtime::find(EnvironmentContract::class)) {
            return DevelopmentEnvironment::is($environment);
        }

        return DevelopmentEnvironment::named((string) self::getEnvironment(), (bool) self::findParameterBag()?->get('kernel.debug'));
    }

    /**
     * @return bool
     */
    public function isProduction()
    {
        return !$this->isDevelopment();
    }

    /**
     * @return bool
     */
    public function isCli()
    {
        return is_cli();
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        return Runtime::find(EnvironmentContract::class)?->isDebug() ?? (bool) self::findParameterBag()?->get('kernel.debug');
    }

    /**
     *
     * Security related methods
     *
     */

    public function setUserIdentifier(string $userIdentifier)
    {
        if (property_exists(User::class, "identifier")) {
            (new \ReflectionProperty(User::class, "identifier"))->setValue(null, $userIdentifier);
        }

        return $this;
    }

    /**
     * Whether a token sent back is the visitor's token of that intent (Tokens::isValid()). The host's facade also
     * takes a form and its request (Symfony's).
     *
     * @param string $id
     * @param $tokenOrForm
     * @param mixed $request
     * @param string $csrfFieldId
     * @return bool
     * @throws \Exception
     */
    public function isCsrfTokenValid(string $id, $tokenOrForm, $request = null, string $csrfFieldId = "_csrf_token"): bool
    {
        if ($tokenOrForm !== null && !is_string($tokenOrForm)) {
            throw new \Exception("Unexpected token value provided: string expected");
        }

        $tokens = Runtime::find(Tokens::class) ?? throw MissingContractException::for(Tokens::class);

        return $tokens->isValid($id, $tokenOrForm);
    }

    /**
     * The visitor signed in, null for none (Access::getUser()).
     *
     * @return object|null
     */
    public function getUser()
    {
        return Runtime::find(Access::class)?->getUser();
    }

    /**
     * Whether the visitor is granted that attribute (Access::isGranted()).
     *
     * @param $attribute
     * @param $subject
     * @return bool
     */
    public function isGranted($attribute, $subject = null): bool
    {
        $access = Runtime::find(Access::class) ?? throw MissingContractException::for(Access::class);

        return $access->isGranted($attribute, $subject);
    }

    /**
     *
     * Persistence related methods
     *
     */

    /** Whether the unit of work manages that entity (UnitOfWork::contains()). */
    public function inDoctrine($entity): bool
    {
        if (!is_object($entity)) {
            return false;
        }

        return (bool) Runtime::find(UnitOfWork::class)?->contains($entity);
    }

    /**
     * @return bool
     */
    public function inDoctrineStack()
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
            if (isset($trace["class"]) && str_starts_with($trace["class"], "Doctrine")) {
                return true;
            }
        }

        return false;
    }

    /**
     * An entity's values as read or last flushed - of the entity, or of a lifecycle event's, a PreUpdate's changes
     * read as the values before -: the entity hydrator's, on the unit of work's Snapshots.
     *
     * @param $eventOrEntity
     * @param bool $inDoctrineStack
     * @param bool $reopen the host's facade reopens a closed entity manager (Symfony's, on Doctrine)
     * @return mixed[]
     * @throws \Exception
     */
    public function getOriginalEntityData($eventOrEntity, bool $inDoctrineStack = false, bool $reopen = false)
    {
        $hydrator = self::getEntityHydrator();
        if (!$hydrator instanceof EntityHydrator) {
            throw new \LogicException(sprintf('The entity hydrator %s does not read original values.', get_debug_type($hydrator)));
        }

        if ($inDoctrineStack === true && !self::isPreUpdate($eventOrEntity) && $this->inDoctrineStack()) {
            throw new \Exception("Achtung ! You are trying to access data object within a Doctrine method.." .
                "Original entity might have already been updated.");
        }

        return $hydrator->getOriginalEntityData($eventOrEntity);
    }

    /**
     * The entity given (or the lifecycle event's) hydrated with its original values.
     *
     * @param $eventOrEntity
     * @param bool $inDoctrineStack
     * @param bool $reopen
     * @return mixed
     * @throws \Exception
     */
    public function getOriginalEntity($eventOrEntity, bool $inDoctrineStack = false, bool $reopen = false)
    {
        $data = $this->getOriginalEntityData($eventOrEntity, $inDoctrineStack, $reopen);

        $entity = match (true) {
            $eventOrEntity instanceof \Omnibridge\Persistence\Lifecycle\EntityEvent => $eventOrEntity->entity,
            is_a($eventOrEntity, 'Doctrine\Persistence\Event\LifecycleEventArgs') => $eventOrEntity->getObject(),
            default => $eventOrEntity,
        };

        return self::getEntityHydrator()->hydrate($entity, $data);
    }

    /** A PreUpdate, omnisense/omnibridge's or Doctrine's (by name). */
    private static function isPreUpdate(mixed $event): bool
    {
        return $event instanceof \Omnibridge\Persistence\Lifecycle\PreUpdate
            || (\is_object($event) && (is_a($event, 'Doctrine\Persistence\Event\PreUpdateEventArgs') || is_a($event, 'Doctrine\ORM\Event\PreUpdateEventArgs')));
    }
}
