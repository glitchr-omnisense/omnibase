<?php

namespace Base\Controller;

use Base\Service\BaseService;

use Base\Routing\RouterInterface;
use Error;
use ErrorException;
use Exception;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Throwable;

class ErrorController extends AbstractController
{
    private BaseService $baseService;
    private RouterInterface $router;
    private HtmlErrorRenderer $htmlErrorRenderer;
    private ?Profiler $profiler;

    public function __construct(HtmlErrorRenderer $htmlErrorRenderer, RouterInterface $router, BaseService $baseService, ?Profiler $profiler = null)
    {
        $this->baseService = $baseService;
        $this->router = $router;
        $this->htmlErrorRenderer = $htmlErrorRenderer;
        $this->profiler = $profiler;
    }

    public function Main(Throwable $exception)
    {
        try {
            $isPreview = $this->router->getRouteName() === "_preview_error";

            if ($this->baseService->isDevelopment() && !$isPreview) {
                $response = $this->Rescue($exception);
            } else {
                $response = $this->render("exception.html.twig", ['flattenException' => FlattenException::createFromThrowable($exception)]);
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


    public function Rescue(Throwable $exception): Response
    {
        $this->profiler?->disable();

        $flattenException = $this->htmlErrorRenderer->render($exception);

        ob_start();
        echo $flattenException->getAsString();
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
