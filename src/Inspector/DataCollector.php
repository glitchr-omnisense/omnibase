<?php

namespace Base\Inspector;

use Base\BaseBundle;
use Base\Service\BaseService;
use Base\Service\ParameterBagInterface;
use Composer\InstalledVersions;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\DBAL\Connection;

use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;

use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;

use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 *
 */
class DataCollector extends AbstractDataCollector
{
    /** @var AdminContextProvider */
    private AdminContextProvider $adminContextProvider;

    /** @var ManagerRegistry */
    private ManagerRegistry $doctrine;

    /** @var RouterInterface */
    private RouterInterface $router;

    /** @var ParameterBagInterface */
    private ParameterBagInterface $parameterBag;

    /** @var BaseService */
    private BaseService $baseService;

    public array $dataBundles = [];

    public function __construct(AdminContextProvider $adminContextProvider, ManagerRegistry $doctrine, ParameterBagInterface $parameterBag, RouterInterface $router, BaseService $baseService)
    {
        $this->adminContextProvider = $adminContextProvider;
        $this->doctrine = $doctrine;
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->baseService = $baseService;
    }

    public function getName(): string
    {
        return 'base';
    }

    public static function getTemplate(): ?string
    {
        return '@Base/inspector/data_collector.html.twig';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataBundle(string $bundle): ?array
    {
        if (!array_key_exists($bundle, $this->dataBundles)) {
            $this->collectDataBundle($bundle);
        }

        return $this->dataBundles[$bundle] ?? null;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->data['method'];
    }

    /**
     * @param string $bundle
     * @param string|null $bundleSuffix
     * @return bool
     */
    public function collectDataBundle(string $bundle, ?string $bundleSuffix = null)
    {
        $bundleIdentifier = $this->getBundleIdentifier($bundle);
        if (!$bundleIdentifier) {
            return false;
        }

        $bundleLocation = InstalledVersions::getRootPackage()["install_path"];
        $bundleLocation = realpath($bundleLocation . "vendor/" . $bundleIdentifier);

        $bundleVersion = InstalledVersions::getPrettyVersion($bundleIdentifier);
        $bundleDevRequirements = !InstalledVersions::isInstalled($bundleIdentifier, false);
        $bundleSuffix = $bundleSuffix ? "@" . $bundleSuffix : "";

        $this->dataBundles[$bundle] = [
            "identifier" => $bundleIdentifier,
            "name" => trim(str_rstrip(mb_ucwords(camel2snake(class_basename($bundle), " ")), "Bundle")),
            "location" => $bundleLocation,
            "version" => str_lstrip($bundleVersion, "v") . $bundleSuffix,
            "dev_requirements" => $bundleDevRequirements,
        ];

        return true;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $exception
     * @return void
     */
    public function collect(Request $request, Response $response, $exception = null)
    {
        $context = $this->adminContextProvider->getContext();
        $dbname = $this->doctrine->getConnection($this->doctrine->getDefaultConnectionName())->getParams()["dbname"] ?? null;
        $dbname = str_shorten($dbname, 10, SHORTEN_MIDDLE, "[..]");

        $this->collectDataBundle(BaseBundle::class);
        $this->collectDataBundle(TwigBundle::class);
        $this->collectDataBundle(ApiPlatformBundle::class);
        $this->collectDataBundle(DoctrineBundle::class, $dbname);
        $this->collectDataBundle(EasyAdminBundle::class);

        $this->data = array_map_recursive(fn($v) => $this->cloneVar($v), $this->collectData($context));
        $this->data["_bundles"] = $this->dataBundles;
    }

    /**
     * @param string $bundle
     * @return mixed|string|null
     */
    protected function getBundleIdentifier(string $bundle)
    {
        if (!class_exists($bundle)) {
            return null;
        }

        if (array_key_exists($bundle, $this->dataBundles)) {
            return $this->dataBundles[$bundle]["identifier"];
        }

        $reflector = new ReflectionClass($bundle);
        $bundleRoot = dirname($reflector->getFileName());

        foreach (InstalledVersions::getInstalledPackages() as $bundleIdentifier) {
            $bundleLocation = InstalledVersions::getRootPackage()["install_path"];
            $bundleLocation = realpath($bundleLocation . "vendor/" . $bundleIdentifier);

            if ($bundleLocation && str_starts_with($bundleRoot, $bundleLocation)) {
                return $bundleIdentifier;
            }
        }

        return null;
    }

    /**
     * @param Connection $connection
     * @return string
     */
    protected function getFormattedConnection(Connection $connection)
    {
        $params = $connection->getParams();

        $host = $params["host"] ?? "";
        if (!$host) {
            return "";
        }

        $driver = $params["driver"] ?? null;
        $driver = $driver ? $driver . "://" : "";

        $user = $params["user"] ?? null;
        $user = $user ? $user . "@" : "";

        $port = $params["port"] ?? null;
        $port = $port ? ":" . $port : "";

        $dbname = $params["dbname"] ?? null;
        $dbname = $dbname ? "/" . $dbname : "";

        $charset = $params["charset"] ?? null;
        $charset = $charset ? " (" . $params["charset"] . ")" : "";

        return $driver . $user . $host . $port . $dbname . $charset;
    }

    /**
     * @return array
     */
    private function getDoctrineConnections()
    {
        $defaultConnectionName = $this->doctrine->getDefaultConnectionName();

        $connections = [];
        foreach ($this->doctrine->getConnectionNames() as $connectionName => $_) {
            $connection = $this->doctrine->getConnection($connectionName);

            $isDefaultName = $defaultConnectionName == $connectionName;
            $connectionName = $isDefaultName ? $connectionName . " (*)" : $connectionName;

            $connections[$connectionName] = $this->getFormattedConnection($connection);
        }

        return $connections;
    }

    /**
     * @param string $bundle
     * @return string
     */
    private function getBundleFormattedName(string $bundle)
    {
        $bundleName = $this->getDataBundle($bundle)["name"] ?? null;
        $bundleVersion = $this->getDataBundle($bundle)["version"] ?? null;
        $bundleVersion = ($bundleVersion ? " (" . $bundleVersion . ")" : "");
        return $bundleName . $bundleVersion;
    }

    private function collectData(?AdminContext $context): array
    {
        $data = [];
        if (class_exists(BaseBundle::class)) {
            $data[$this->getBundleFormattedName(BaseBundle::class)] = [
                'Environment name' => $this->baseService->getEnvironment(),
                'Development mode' => $this->baseService->isDevelopment(),
                'Technical support' => $this->parameterBag->get("base.notifier.technical_support"),
                'Router Class' => get_class($this->router),
                'Parameter Bag Class' => get_class($this->parameterBag),
            ];
        }

        if (class_exists(DoctrineBundle::class)) {
            $data[$this->getBundleFormattedName(DoctrineBundle::class)] = $this->getDoctrineConnections();
        }

        if (class_exists(EasyAdminBundle::class)) {
            $data[$this->getBundleFormattedName(EasyAdminBundle::class)] = $context ? [
                'CRUD Controller FQCN' => $context->getCrud()?->getControllerFqcn(),
                'CRUD Action' => $context->getRequest()->get(EA::CRUD_ACTION),
                'Entity ID' => $context->getRequest()->get(EA::ENTITY_ID),
                'Sort' => $context->getRequest()->get(EA::SORT),
            ] : [];
        }

        if (class_exists(ApiPlatformBundle::class)) {
            $data[$this->getBundleFormattedName(ApiPlatformBundle::class)] = [];
        }

        if (class_exists(TwigBundle::class)) {
            $data[$this->getBundleFormattedName(TwigBundle::class)] = [
                'Custom Twig Loader' => $this->parameterBag->get("base.twig.use_custom"),
                'Twig Autoappending' => $this->parameterBag->get("base.twig.autoappend"),
                'Form2 Override' => $this->parameterBag->get("base.twig.use_form2"),
                'Bootstrap Support' => $this->parameterBag->get("base.twig.use_bootstrap"),
                'Font Awesome icons' => $this->parameterBag->get("base.vendor.fontawesome.metadata"),
            ];
        }

        return $data;
    }
}
