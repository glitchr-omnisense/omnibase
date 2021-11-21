<?php

namespace Base\Controller;
use Base\Service\BaseService;

use Base\Entity\User\Notification;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ErrorController as EE;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\RuntimeError;

class ErrorController extends AbstractController
{
    private $baseService;
    public function __construct(HtmlErrorRenderer $htmlErrorRenderer, BaseService $baseService)
    {
        $this->baseService = $baseService;
        $this->htmlErrorRenderer = $htmlErrorRenderer;
    }

    public function Main(\Throwable $exception) {
        
        try { 
        
            if ($this->baseService->isDevelopment()) return $this->rescue($exception);
            return $this->render("@Base/exception.html.twig", ['exception' => $exception]);
        
        } catch(Exception $fatalException) {

            throw new Exception("Twig rendering engine failed (".trim($fatalException->getMessage(), ".").") following a first exception. (see below)", 500, $exception);
            $response = $this->rescue($exception);
        }

        $notification = new Notification($exception);
        if ($this->baseService->isDevelopment()) $notification->send("danger");
        if ($this->baseService->isDevelopment()) dump($exception);

        return $response;
    }


    public function rescue(\Throwable $exception): Response
    {
        $flattenException = $this->htmlErrorRenderer->render($exception);

        ob_start();
        echo $flattenException->getAsString();
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
