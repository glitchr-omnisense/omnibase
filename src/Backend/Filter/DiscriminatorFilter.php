<?php

namespace Base\Backend\Filter;

use Base\Traits\BaseTrait;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;

class DiscriminatorFilter implements FilterInterface
{
    use FilterTrait;
    use BaseTrait;

    protected $alias;

    public static function new(string $propertyName, $label = null, string $entityFqcn = null): self
    {
        $filter = (new self());

        $discriminatorMap = self::getClassMetadataManipulator()->getDiscriminatorMap($entityFqcn);
        $discriminatorMap = array_filter($discriminatorMap, fn($e) => is_instanceof($e, $entityFqcn));
        $choices = array_flip(array_map(fn($d) => self::getTranslator()->transEntity($d), $discriminatorMap));

        $filter->setFormType(TextFilterType::class);
        if (!empty($discriminatorMap)) {
            $filter
                ->setFormType(ChoiceFilterType::class)
                ->setFormTypeOption('value_type_options', ["choices" => $choices]);
        }

        return $filter
            ->setFilterFqcn(__CLASS__)
            ->setProperty(str_replace('.', '_', $propertyName))
            ->setLabel($label)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }


    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $parameterName = "filter_" . $filterDataDto->getParameterName();
        $parameter = $filterDataDto->getValue();

        $em = $queryBuilder->getEntityManager();
        $meta = $em->getClassMetadata($entityDto->getFqcn());

        $className = $meta->discriminatorMap[$parameter] ?? null;
        $queryBuilder
            ->andWhere("entity INSTANCE OF :" . $parameterName)
            ->setParameter($parameterName, $parameter);
    }
}
