<?php

namespace Base\Controller;

use Base\BaseBundle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\BaseService;

class MainController extends AbstractController
{
    public static $foundBaseService       = false;
    public static $foundBaseSubscriber    = false;
    public static $foundBaseTwigExtension = false;

    private $service;
    public function __construct(BaseService $service) {

        $this->service = $service;
    }

    /**
     * Controller example
     *
     * @Route("/", name="index")
     */
    public function Index(): Response
    {
        $version = Kernel::VERSION;
        $projectDir = \dirname(__DIR__, 5);
        $docVersion = mb_substr(Kernel::VERSION, 0, 3);
        $baseVersion = BaseBundle::VERSION;

        ob_start();
        include \dirname(__DIR__).'/Resources/views/welcome.html.php';
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
