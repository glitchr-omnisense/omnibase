<?php

namespace Base\Field;

use Base\Field\Type\RelationType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

final class RelationField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_AUTOCOMPLETE = 'autocomplete';
    public const OPTION_CRUD_CONTROLLER = 'crudControllerFqcn';
    public const OPTION_WIDGET = 'widget';
    public const OPTION_QUERY_BUILDER_CALLABLE = 'queryBuilderCallable';
    public const OPTION_SHOWFIRST = 'showFirst';

    /** @internal this option is intended for internal use only */
    public const OPTION_RELATED_URL = 'relatedUrl';
    /** @internal this option is intended for internal use only */
    public const OPTION_DOCTRINE_ASSOCIATION_TYPE = 'associationType';

    public const WIDGET_AUTOCOMPLETE = 'autocomplete';
    public const WIDGET_NATIVE = 'native';

    /** @internal this option is intended for internal use only */
    public const PARAM_AUTOCOMPLETE_CONTEXT = 'autocompleteContext';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/association')
            ->setTemplatePath("@EasyAdmin/crud/field/relation.html.twig")
            ->setFormType(RelationType::class)
            ->addCssClass('field-association')
            ->setCustomOption(self::OPTION_SHOWFIRST, false)
            ->setCustomOption(self::OPTION_AUTOCOMPLETE, false)
            ->setCustomOption(self::OPTION_CRUD_CONTROLLER, null)
            ->setCustomOption(self::OPTION_WIDGET, self::WIDGET_AUTOCOMPLETE)
            ->setCustomOption(self::OPTION_QUERY_BUILDER_CALLABLE, null)
            ->setCustomOption(self::OPTION_RELATED_URL, null)
            ->setCustomOption(self::OPTION_DOCTRINE_ASSOCIATION_TYPE, null);
    }

    public function showFirst($show = true)
    {
        $this->setCustomOption(self::OPTION_SHOWFIRST, $show);
        return $this;
    }

    public function setFilter($class)
    {
        $this->setFormTypeOption("class", $class);
        return $this;
    }

    public function setFormattedValues(AdminUrlGenerator $adminUrlGenerator): self
    {
        $funcName = "get".ucfirst($this->getProperty());
        $funcId = "getId";
        $this->formatValue(function ($value, $entity) use ($adminUrlGenerator, $funcId, $funcName) {

            $str = "";
            for ($i = 0; $i < $entity->$funcName()->count(); $i++) {

                if(!method_exists($entity->$funcName()[$i], $funcId) || !$this->getCrudController())
                    $entry = $entity->$funcName()[$i];
                else {

                    $id  = $entity->$funcName()[$i]->$funcId();
                    $url = $adminUrlGenerator
                        ->setController($this->getCrudController())
                        ->setAction(Action::DETAIL)->setEntityId($id)
                        ->generateUrl();

                    $entry = "<a href='" . $url . "'>" . $entity->$funcName()[$i] . "</a>";
                }

                if(empty($str)) $str = $entry."</span>";
                else $str .= "</span> <span class='badge badge-secondary'>" . $entry;
            }

            return $str;
        });

        return $this;
    }

    public function autocomplete(): self
    {
        $this->setCustomOption(self::OPTION_AUTOCOMPLETE, true);

        return $this;
    }

    public function renderAsNativeWidget(bool $asNative = true): self
    {
        $this->setCustomOption(self::OPTION_WIDGET, $asNative ? self::WIDGET_NATIVE : self::WIDGET_AUTOCOMPLETE);

        return $this;
    }

    public function getProperty():string {
       return $this->dto->getProperty();
    }
    public function getCustomOption(string $optionName)
    {
        return $this->dto->getCustomOption($optionName);
    }

    public function getCrudController(): ?string
    {
        return $this->getCustomOption(self::OPTION_CRUD_CONTROLLER) ?? null;
    }

    public function setCrudController(string $crudControllerFqcn): self
    {
        $this->setCustomOption(self::OPTION_CRUD_CONTROLLER, $crudControllerFqcn);

        return $this;
    }

    public function setQueryBuilder(\Closure $queryBuilderCallable): self
    {
        $this->setCustomOption(self::OPTION_QUERY_BUILDER_CALLABLE, $queryBuilderCallable);

        return $this;
    }
}
