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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ErrorController as EE;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorController extends AbstractController
{
    private $baseService;
    public function __construct(TranslatorInterface $translator, HtmlErrorRenderer $htmlErrorRenderer, BaseService $baseService)
    {
        $this->baseService = $baseService;
        $this->translator  = $translator;
        $this->htmlErrorRenderer = $htmlErrorRenderer;
    }

    protected $exception;
    public function Main(\Throwable $exception) {

        $this->exception = $exception;

        foreach($this->getList() as $exception) {

            $notification = new Notification($exception);
            if ( !empty($notification->getContent()) ) {
        
                if ($this->baseService->isDevelopment()) dump($exception);
                if ($this->baseService->isDevelopment()) $notification->send("danger");
            }
        }

        try { return $this->render("@Base/exception.html.twig", ['exception' => $this]); }
        catch(Exception $_) {

            $flattenException = $this->htmlErrorRenderer->render($exception);
            
            ob_start();
            echo $flattenException->getAsString();
            return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
        }
    }

    protected function getList() {

        $exception = $this->exception;

        $exceptionList   = [$exception];
        while( $exception->getPrevious() ) {

            $exception = $exception->getPrevious();
            if(!$exception) break;

            $exceptionList[] = $exception;
        }

        return $exceptionList;
    }

    protected function getCode(FlattenException $exception = null)
    {
        $exception = $exception ?? $this->exception;
        return $exception->getStatusCode();
    }

    protected function getMessage(FlattenException $exception = null)
    {
        $exception = $exception ?? $this->exception;
        $code = $this->getCode($exception);
        
        if (!$this->isKnown($exception)) return $exception->getStatusText();
        return $this->translator->trans("exception.".$code, [], "controllers");
    }

    protected function isKnown(FlattenException $exception = null)
    {
        $exception = $exception ?? $this->exception;
        $code = $this->getCode($exception);

        return $this->translator->trans("exception.".$code, [], "controllers") != "exception.".$code;
    }
}
