<?php

namespace Base\Database\Walker;

use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Base\DatabaseSubscriber\IntlSubscriber;
use Base\Service\LocaleProvider;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\Exec\SingleSelectExecutor;
use RuntimeException;

class TranslatableWalker extends SqlWalker
{
    public function __construct($query, $parserResult, array $queryComponents)
    {
        parent::__construct($query, $parserResult, $queryComponents);
        $this->localeProvider = $this->getLocaleProvider();
    }

    /**
     * @return Query\Exec\AbstractSqlExecutor
     */
    public function getExecutor($AST)
    {
        if (!$AST instanceof SelectStatement)
            return parent::getExecutor($AST);

        return new SingleSelectExecutor($AST, $this);
    }


    public function walkFromClause($fromClause): string
    {
        $sql = parent::walkFromClause($fromClause);

        $explodeLeftJoin = explode(" LEFT JOIN ", $sql);
        $fromClause = array_shift($explodeLeftJoin);

        $explodeLeftJoin = array_reverse($explodeLeftJoin);

        array_unshift($explodeLeftJoin, $fromClause);
        return implode(" LEFT JOIN ", $explodeLeftJoin);
    }

    protected function getLocaleProvider(): LocaleProvider
    {
        foreach ($this->getEntityManager()->getEventManager()->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {

                if ($listener instanceof IntlSubscriber)
                    return $listener->getLocaleProvider();
            }
        }

        throw new RuntimeException('Locale provider not found.');
    }

    public function walkJoinAssociationDeclaration($joinAssociationDeclaration, $joinType = AST\Join::JOIN_TYPE_INNER, $condExpr = null): string
    {
        $sql = parent::walkJoinAssociationDeclaration($joinAssociationDeclaration, $joinType, $condExpr);

        $dqlAlias       = $joinAssociationDeclaration->joinAssociationPathExpression->identificationVariable;
        $joinedDqlAlias = $joinAssociationDeclaration->aliasIdentificationVariable;

        $relation       = $this->getQueryComponent($joinedDqlAlias)['relation'] ?? null;
        if($relation === null) return $sql;

        // Extract source class information
        $sourceClass      = $this->getEntityManager()->getClassMetadata($relation['sourceEntity']);
        if(!class_implements_interface($sourceClass->getName(), TranslatableInterface::class))
            return $sql;

        // Get target class
        $targetClass     = $this->getEntityManager()->getClassMetadata($relation['targetEntity']);
        if(!class_implements_interface($targetClass->getName(), TranslationInterface::class))
            return $sql;

        // Check whether target class is the root translation entity or not
        $assoc = ! $relation['isOwningSide'] ? $targetClass->associationMappings[$relation['mappedBy']] : $relation;
        if(!array_key_exists("inherited", $assoc))
            return $sql;

        // Get root target class to replace target class 'translation_id'
        $rootTargetClass = $this->getEntityManager()->getClassMetadata($assoc['inherited']);
        if(!class_implements_interface($rootTargetClass->getName(), TranslationInterface::class))
            return $sql;

        $sourceTableAlias = $this->getSQLTableAlias($sourceClass->getTableName(), $dqlAlias);
        $targetTableAlias = $this->getSQLTableAlias($targetClass->getTableName(), $joinedDqlAlias);
        $rootTargetTableAlias = $this->getSQLTableAlias($rootTargetClass->getTableName(), $joinedDqlAlias);

        $sql = str_replace(
            $targetTableAlias.".id = ".$rootTargetTableAlias.".id",
            $rootTargetTableAlias.".translatable_id = ".$sourceTableAlias.".id", $sql
        );

        $sql = str_replace(
                $sourceTableAlias.".id = ".$targetTableAlias.".translatable_id",
                $targetTableAlias.".id = ".$rootTargetTableAlias.".id", $sql
            );

        /*
        * IMPLEMENT JOIN FOR INHERITING TRANSLATIONS
        */
        return $sql;
    }
}