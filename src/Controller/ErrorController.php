<?php

namespace Base\Controller;

use Base\Service\BaseService;

use Base\Routing\AdvancedRouterInterface;
use Error;
use ErrorException;
use Exception;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Error\LoaderError;
use Throwable;

class ErrorController extends AbstractController
{
    private BaseService $baseService;
    private AdvancedRouterInterface $router;
    private HtmlErrorRenderer $htmlErrorRenderer;
    private RequestStack $requestStack;
    private ?Profiler $profiler;

    public function __construct(HtmlErrorRenderer $htmlErrorRenderer, AdvancedRouterInterface $router, BaseService $baseService, RequestStack $requestStack, ?Profiler $profiler = null)
    {
        $this->baseService = $baseService;
        $this->router = $router;
        $this->htmlErrorRenderer = $htmlErrorRenderer;
        $this->requestStack = $requestStack;
        $this->profiler = $profiler;
    }

    /**
     * @param Throwable $exception
     * @return Response
     * @throws Exception
     */
    public function Main(Throwable $exception)
    {
        try {
            $isPreview = $this->router->getRouteName() === "_preview_error";

            if ($this->baseService->isDevelopment() && !$isPreview) {
                $response = $this->Rescue($exception);
            } else {
                $response = $this->renderException(FlattenException::createFromThrowable($exception));
            }
        } catch (Error|Exception|ErrorException $fatalException) {
            throw new Exception("Twig rendering engine failed (" . trim($fatalException->getMessage(), ".") . ") following a first exception. (see below)", 500, $exception);
            $response = $this->Rescue($exception);
        }

        // NB: Remember this might be annoying sometimes..
        // $notification = new Notification($exception);
        // if ($this->baseService->isDevelopment()) $notification->send("danger");
        // if ($this->baseService->isDevelopment()) dump($exception);

        return $response;
    }


    /**
     * @admin/* path errors get the admin's own chrome instead of the
     * front-end's exception.html.twig - matched by request PATH, not the
     * resolved route name: a genuinely unmatched 404 never gets a
     * "_route" attribute set at all, so route-name-based admin detection
     * (AdvancedRouter::isAdmin()) silently misses exactly the most
     * common admin error case (a bad/typo'd /admin/... URL). Falls back
     * to the front-end template if base-bundle-admin isn't installed (no
     * "@Admin" Twig namespace registered) or its error.html.twig is
     * missing for any other reason.
     */
    private function renderException(FlattenException $flattenException): Response
    {
        $path = $this->requestStack->getCurrentRequest()?->getPathInfo() ?? '';

        if (str_starts_with($path, '/admin')) {
            try {
                return $this->render('@Admin/error.html.twig', ['flattenException' => $flattenException]);
            } catch (LoaderError $loaderError) {
                // no-op: fall through to the front-end template below
            }
        }

        return $this->render('exception.html.twig', ['flattenException' => $flattenException]);
    }

    public function Rescue(Throwable $exception): Response
    {
        $this->profiler?->disable();

        $flattenException = $this->htmlErrorRenderer->render($exception);

        ob_start();
        echo $flattenException->getAsString();
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
