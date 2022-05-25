<?php

namespace Base\Controller;
use Base\Service\BaseService;

use Base\Entity\User\Notification;
use Error;
use ErrorException;
use Exception;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;

class ErrorController extends AbstractController
{
    private $baseService;
    public function __construct(HtmlErrorRenderer $htmlErrorRenderer, BaseService $baseService)
    {
        $this->baseService = $baseService;
        $this->htmlErrorRenderer = $htmlErrorRenderer;
    }

    public function Main(\Throwable $exception) {

        $response = null;
        
        try {

            if ($this->baseService->isDevelopment()) return $this->Rescue($exception);
            return $this->render("@Base/exception.html.twig", ['flattenException' => FlattenException::createFromThrowable($exception)]);

        } catch(Error|Exception|ErrorException $fatalException) {

            throw new Exception("Twig rendering engine failed (".trim($fatalException->getMessage(), ".").") following a first exception. (see below)", 500, $exception);
            $response = $this->Rescue($exception);
        }

        $notification = new Notification($exception);
        if ($this->baseService->isDevelopment()) $notification->send("danger");
        if ($this->baseService->isDevelopment()) dump($exception);

        return $response;
    }


    public function Rescue(\Throwable $exception): Response
    {
        $flattenException = $this->htmlErrorRenderer->render($exception);

        ob_start();
        echo $flattenException->getAsString();
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
