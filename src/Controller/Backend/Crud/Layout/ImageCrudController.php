<?php

namespace Base\Controller\Backend\Crud\Layout;

use Base\Backend\Config\Extension;
use Base\Controller\Backend\AbstractCrudController;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Field\CollectionField;
use Base\Field\ImageField;
use Base\Field\QuadrantField;
use Base\Field\Type\CropperType;
use Base\Field\Type\SlugType;
use Base\Service\Translator;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;

class ImageCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureExtensionWithResponseParameters(Extension $extension, KeyValueStore $responseParameters): Extension
    {
        if ($entity = $this->getEntity()) {
            $extension->setImage($entity->getSource(), ["style" => "object-position: ".$entity->getQuadrantPosition().";"]);

            $class = mb_strtolower(camel2snake($entity));
            $entityLabel = $this->translator->trans($class.".".Translator::NOUN_SINGULAR, [], AbstractDashboardController::TRANSLATION_ENTITY);
            if ($entityLabel == $class.".".Translator::NOUN_SINGULAR) {
                $entityLabel = null;
            } else {
                $extension->setTitle(mb_ucwords($entityLabel));
            }

            $entityLabel = $entityLabel ?? $this->getCrud()->getAsDto()->getEntityLabelInSingular() ?? "";
            $entityLabel = !empty($entityLabel) ? mb_ucwords($entityLabel) : "";

            if ($this->getCrud()->getAsDto()->getCurrentAction() != "new") {
                $extension->setText($entityLabel." #".$entity->getId());
            }
        }

        return $extension;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield QuadrantField::new('quadrant')->setColumns(2);
            yield ImageField::new('source')->setColumns(10)->setCropper();

            yield CollectionField::new('crops')
                    ->allowObject()
                    ->allowAdd()
                    ->showCollapsed(false)
                    ->setEntryLabel(function ($i, $e) {
                        if ($e === null) {
                            return false;
                        }
                        if ($i === "__prototype__") {
                            return false;
                        }

                        $id = " #".(((int) $i) + 1);

                        return $this->getTranslator()->transEntity($e).$id;
                    })

                    ->setEntryType(CropperType::class)
                    ->setEntryOptions([
                        "target" => "source",
                        "quadrant"  => "quadrant.wind",
                        "fields" => [
                            "label"   => ["form_type" => TextType::class, "label" => "Label", "required"  => false],
                            "slug"    => ["form_type" => SlugType::class, "label" => false, "lock" => true, "keep" => ":", "target" => ".label"]
                        ]
                    ]);
        }, $args);
    }
}
