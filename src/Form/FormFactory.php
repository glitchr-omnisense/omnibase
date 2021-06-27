<?php

namespace Base\Form;

use Base\Entity\User\Notification;
use Base\Form\Traits\FlowFormTrait;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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

class FormFactory extends \Symfony\Component\Form\FormFactory
{
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param EntityManager $em
     * @param string|object $class
     *
     * @return bool
     */
    public function isEntity($class): bool
    {
        if (is_object($class)) {
            $class = ($class instanceof Proxy)
                ? get_parent_class($class)
                : get_class($class);
        }

        return !$this->entityManager->getMetadataFactory()->isTransient($class);
    }

    public function create(string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType', $data = null, array $options = [])
    {
        // I recommend not using entity data..
        // NB: https://blog.martinhujer.cz/symfony-forms-with-request-objects/
        if ($this->isEntity($data))
            throw new Exception("Form data is an entity \"" . get_class($data) . "\". This is not recommended..");

        return parent::create($type, $data, $options);
    }
}
