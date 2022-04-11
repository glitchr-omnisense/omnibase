<?php

namespace Base\Field\Filter;

use Base\Traits\BaseTrait;
use Doctrine\ORM\Query\Expr;
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
   protected $joinClass;

   public static function new(string $propertyName, $label = null, string $entityFqcn = null): self
   {
      $filter = (new self());

      $discriminatorMap = self::getClassMetadataManipulator()->getDiscriminatorMap($entityFqcn);
      $discriminatorMap = array_filter($discriminatorMap, fn($e) => is_parent($e, $entityFqcn));
      $choices = array_flip(array_map(fn($d) => self::getTranslator()->entity($d), $discriminatorMap));

      $filter->setFormType(TextFilterType::class);
      if(!empty($discriminatorMap)) $filter
         ->setFormType(ChoiceFilterType::class)
         ->setFormTypeOption('value_type_options', ["choices" => $choices]);

      return $filter
         ->setFilterFqcn(__CLASS__)
         ->setProperty(str_replace('.','_',$propertyName))
         ->setLabel($label)
         ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
   }


   public function apply(QueryBuilder $qb, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
   {
      $parameterName = "filter_".$filterDataDto->getParameterName();
      $parameter = $filterDataDto->getValue();

      $em = $qb->getEntityManager();
      $meta = $em->getClassMetadata($entityDto->getFqcn());
      
      $className = $meta->discriminatorMap[$parameter] ?? null;
      $qb
         ->andWhere("entity INSTANCE OF :".$parameterName)
         ->setParameter($parameterName, $parameter);
   }
}