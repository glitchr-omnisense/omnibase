<?php

namespace Base\Controller;
use Base\Service\BaseService;

use Base\Entity\User\Notification;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class ExceptionController extends AbstractController
{
    private $baseService;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
        $this->translator  = $baseService->getTranslator();
    }

    /**
     * @var FlattenException
     */
    protected $exception;
    public function Main(FlattenException $exception) {

        $this->exception = $exception;
        foreach($this->getList() as $exception) {

            $notification = new Notification($exception);
            if ( !empty($notification->getContent()) ) {

                if ($this->baseService->isDevelopment()) dump($exception);
                if ($this->baseService->isDevelopment()) $notification->send("danger");
            }
        }

        return $this->render("@Base/exception.html.twig", ['exception' => $this]);
    }

    public function getList() {

        $exception = $this->exception;

        $exceptionList   = [$exception];
        while( $exception->getPrevious() ) {

            $exception = $exception->getPrevious();
            if(!$exception) break;

            $exceptionList[] = $exception;
        }

        return $exceptionList;
    }

    public function getCode(FlattenException $exception = null)
    {
        $exception = $exception ?? $this->exception;
        return $exception->getStatusCode();
    }

    public function getMessage(FlattenException $exception = null)
    {
        $exception = $exception ?? $this->exception;
        $code = $this->getCode($exception);
	dump($exception->getStatusText());
        if (!$this->isKnown($exception)) return $exception->getStatusText();
        return $this->translator->trans("exception.".$code, [], "controllers");
    }

    public function isKnown(FlattenException $exception = null)
    {
        $exception = $exception ?? $this->exception;
        $code = $this->getCode($exception);

        return $this->translator->trans("exception.".$code, [], "controllers") != "exception.".$code;
    }
}
