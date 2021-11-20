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
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public function Main(\Throwable $exception) {

        $response = $this->render("@Base/exception.html.twig", ['exception' => $exception]);

        $notification = new Notification($exception);
        if ($this->baseService->isDevelopment()) $notification->send("danger");
        if ($this->baseService->isDevelopment()) dump($exception->getMessage());

        return $response;
    }
}
