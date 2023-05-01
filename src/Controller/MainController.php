<?php

namespace Base\Controller;

use Base\BaseBundle;

use Base\Enum\UserRole;
use Base\Form\FormProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\BaseService;
use  Base\Service\SettingBag;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use function dirname;

/**
 * @Route(priority = -1)
 * */
class MainController extends AbstractController
{
    protected BaseService $service;
    protected SettingBag $settingBag;

    public function __construct(BaseService $service, SettingBag $settingBag)
    {
        $this->service = $service;
        $this->settingBag = $settingBag;
    }

    /**
     * Controller example
     *
     * @Route("/", name="app_welcome")
     */
    public function Index(): Response
    {
        $version = Kernel::VERSION;
        $projectDir = dirname(__DIR__, 5);
        $docVersion = substr(Kernel::VERSION, 0, 3);
        $baseVersion = BaseBundle::VERSION;

        ob_start();
        include dirname(__DIR__) . '/Resources/views/welcome.html.php';
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
