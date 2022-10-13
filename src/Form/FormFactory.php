<?php

namespace Base\Form;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Form\FormFlow;
use Base\Form\Traits\FormGuessTrait;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormRegistryInterface;

use Symfony\Component\Form\FormFactory as SymfonyFormFactory;

class FormFactory extends SymfonyFormFactory implements FormFactoryInterface
{
    use FormGuessTrait;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(FormRegistryInterface $registry, ClassMetadataManipulator $classMetadataManipulator)
    {
        parent::__construct($registry);
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function create(string $type = FormType::class, mixed $data = null, array $options = []) : FormInterface
    {
        // I recommend not using entity data..
        // NB: https://blog.martinhujer.cz/symfony-forms-with-request-objects/
        if ($this->classMetadataManipulator->isEntity($data))
            throw new Exception("An entity \"" . get_class($data) . "\" is passed as data in \"".$type."\".\nThis is not recommended due to possible database flush conflict. Please use a DTO model.");

        return cast(parent::create($type, $data, $options), FormFlow::class);
    }

    public function createProcessor(FormInterface $form): FormProcessorInterface
    {
        return new FormProcessor($form);
    }
}