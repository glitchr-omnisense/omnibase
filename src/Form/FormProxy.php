<?php

namespace Base\Form;

use Base\Entity\User\Notification;
use Base\Form\Traits\FlowFormTrait;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Base\Traits\SingletonTrait;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManager;

class FormProxy implements FormProxyInterface
{
    use SingletonTrait;

    public function __construct()
    {
        self::$_instance = $this;
    }

    protected array $forms = [];
    public function getForms()
    {
        return $this->forms;
    }

    public function addForm(string $name, ?FormInterface $form): self
    {
        if (!$form) return $this;

        if (array_key_exists($name, $this->forms))
            throw new Exception("Form identifier \"$name\" already exists.");

        // Create dummy view to avoid error during twig rendering..
        $form->createView();

        if (!in_array($form, $this->forms))
            $this->forms[$name] = $form;

        return $this;
    }

    public function removeForm(string $name): self
    {
        if (array_key_exists($name, $this->forms))
            unset($this->forms[$name]);

        return $this;
    }

    public function getForm(string $name)
    {
        if(array_key_exists($name, $this->forms))
            return $this->forms[$name];

        throw new Exception("No form \"$name\" found.");
    }

    public function hasForm(string $name):bool
    {
        return array_key_exists($name, $this->forms);
    }
}
