<?php

namespace Base\Form;

use Base\Database\Factory\EntityHydratorInterface;
use Base\Entity\User\Notification;
use Base\Form\Traits\FormFlowTrait;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormProcessor implements FormProcessorInterface
{
    use FormFlowTrait;

    public FormFactoryInterface $formFactory;
    public CsrfTokenManagerInterface $csrfTokenManager;
    public RequestStack $requestStack;

    public function __construct(FormFactoryInterface $formFactory, EntityHydratorInterface $entityHydrator, CsrfTokenManagerInterface $csrfTokenManager, RequestStack $requestStack)
    {
        $this->formFactory      = $formFactory;
        $this->entityHydrator   = $entityHydrator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->requestStack     = $requestStack;
    }

    protected $onDefaultCallback = [];
    public function onDefault($callback)
    {
        $this->onDefaultCallback = $callback;
        return $this;
    }

    protected $onSubmitCallback;
    public function onSubmit($callback)
    {
        $this->onSubmitCallback[] = $callback;
        return $this;
    }

    /**
     * @var Form
     * */
    protected $form = null;
    public function get() { return $this->getForm(); }
    public function getForm()
    {
        return $this->form;
    }

    public function set(Form $form) { $this->setForm($form); }
    public function setForm(Form $form) { $this->form = $form; }

    public function getData() { return $this->data; }
    public function getFormType() { return $this->formType; }
    public function getOptions()
    {
        if (!$this->form)
            throw new Exception("No form provided in FormProcessor");

        return $this->form->getConfig()->getType()->getOptionsResolver()->resolve();
    }

    public function getSession() { return $this->formType::getSession($this->getOptions()); }

    public function getPost()    { return $this->getSession()["POST"] ?? []; }
    public function appendPost()  { return $this->formType::appendSessionFiles($this->getOptions()); }

    public function getFiles()   { return $this->getSession()["FILES"] ?? []; }
    public function appendFiles() { return $this->formType::appendSessionPost($this->getOptions()); }

    public function getExtras()  { return $this->getSession()["EXTRAS"] ?? []; }
    public function appendExtras($extras) { return $this->formType::appendSessionExtras($this->getOptions(), $extras); }

    public function getUploadedFiles()
    {
        $uploadedFiles = [];

        if ($session = $this->getSession()) {

            $files = $session["FILES"];
            foreach($files as $name => $file) {

                $uploadedFiles[$name] = new UploadedFile(
                    $file["tmp_name"], $file["name"], $file["type"], $file["error"]);
            }
        }

        return $uploadedFiles;
    }

    public function process(Request $request): Response
    {
        if(!$this->form)
            throw new Exception("No form provided in FormProcessor");

        $this->form->handleRequest($this->request);

        // Basic form arguments
        $options  = $this->getOptions();
        $formType = $this->getFormType();
        $data     = $this->getData();

        // Prepare possible multiple step forms
        $step        = $formType::getStep($options);
        $nextStep    = false;
        $stepMax     = $formType::getStepMax($options);
        $submitCount = count($this->onSubmitCallback);
        if($stepMax != $submitCount)
            throw new Exception("Number of FormProcessor::onSubmit() calls is not matching the number of steps in ".$formType);

        // Bind session to form (retrieve previous step information)
        $formType::bindSession($options, $this->requestStack->getSession());
        $formSession = $this->getSession();

        // Check if tmp files are still available..
        $fileExpirationTriggered = false;
        $formFiles = $formSession["FILES"] ?? [];
        foreach ($formFiles as $file)
            if ($fileExpirationTriggered = !file_exists($file["tmp_name"])) break;

        // If form expired/session got destroyed
        if ($step && ($fileExpirationTriggered || !$formType::hasSession($options))) {

            $notification = new Notification("Sorry, the form you were filling has expired. Please try again.");
            $notification->send("danger");

            $formType::setStep($options, $step = 0);
            $formType::removeAllSteps($options);

            $this->form = $this->formFactory->create($formType, $data, $options);

            // Determine if able to go to next step
        } else if($this->form->isSubmitted() && $this->form->isValid()) {

            $submittedToken = $this->request->request->get('_csrf_token');
            $tokenName      = array_key_exists("csrf_token_id", $options) ? $options["csrf_token_id"] : "";
            $isTokenValid   = !empty($tokenName) ? $this->csrfTokenManager->isTokenValid($tokenName, $submittedToken) : true;

            if ($isTokenValid) $nextStep = true;
            else {

                $notification = new Notification("Invalid CSRF token detected.");
                $notification->send("danger");
            }
        }

        // Go to next step
        if($nextStep) {

            // Handle multi-step form
            $step = $this->formType::getNextStep($options);
            $formType::setStep($options, $step);
            $formType::removeAllSteps($options);

            // Create new form if required
            $this->form = $this->formFactory->create($formType, $data, $options);
            $this->appendFiles();
            $this->appendPost();
        }

        // Prepare response either calling onDefault or onSubmit step
        if(!$step) $response = call_user_func($this->onDefaultCallback, $this);
        else $response    = call_user_func($this->onSubmitCallback[$step-1], $this, $this->request);

        if (!$response instanceof Response)
            throw new Exception("Unexpected returned value from " . get_class($this) . "::onSubmit()#" . ($step + 1) . ": an instance of Response() is expected");

        // If we reached the end, just kill the session..
        if($step == $stepMax)
            $formType::killSession($options);

        return $response;
    }
}
