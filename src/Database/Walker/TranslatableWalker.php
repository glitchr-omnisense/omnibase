<?php

namespace Base\Database\Walker;

use Base\Database\Mapping\NamingStrategy;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Base\DatabaseSubscriber\IntlSubscriber;
use Base\Service\Localizer;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST;
use RuntimeException;
use Doctrine\ORM\Events;

class TranslatableWalker extends SqlWalker
{
    /**
     * @var Localizer
     */
    protected $localizer;

    /**
     * @var string
     */
    public const LOCALE = 'locale';
    public const FOREIGN_KEY = 'translatable_id';
    public const COLUMN_NAME = 'translations';
    public const SALT = "unique_translation";

    public function __construct($query, $parserResult, array $queryComponents)
    {
        parent::__construct($query, $parserResult, $queryComponents);
        $this->localizer = $this->getLocalizer();
    }

    public function walkFromClause($fromClause): string
    {
        $sql = parent::walkFromClause($fromClause);

        $statements = explodeByArray([" LEFT JOIN", " INNER JOIN", self::FOREIGN_KEY], $sql, true);
        $statementsIntls = [];

        $translatableIds = array_keys($statements, self::FOREIGN_KEY);
        for ($i = 0, $N = count($translatableIds); $i < $N; $i++) {
            $offset = $i > 0 ? $translatableIds[$i-1]+1 : 0;
            $length = $i > 0 ? $translatableIds[$i]-$translatableIds[$i-1]-1 : $translatableIds[$i];

            $statementsIntl = array_slice($statements, $offset, $length);
            $statementsIntl[count($statementsIntl) - 1] .= self::FOREIGN_KEY;

            $statementsIntls[] = $statementsIntl;
        }

        if (empty($statementsIntls)) {
            return $sql;
        }
        if (count($statements) != end($translatableIds)) {
            $statementsIntls[] = array_slice($statements, $translatableIds[$i-1]+1);
        }

        $sql = "";
        foreach (array_filter($statementsIntls) as $statementsIntl) {
            usort_startsWith($statementsIntl, [" FROM", " INNER JOIN", " LEFT JOIN"]);

            $mask = array_map(fn ($s) => str_starts_with($s, " LEFT JOIN") && str_contains($s, NamingStrategy::TABLE_I18N_SUFFIX), $statementsIntl);
            $offset = 0;
            $length = 0;

            $lastPos = count($mask)-1;

            $submask = array_fill(0, count($mask), false);
            foreach ($mask as $pos => $bit) {
                $submask[$pos] |= $bit;
                if ($bit && $length < 1) {
                    $offset = $pos;
                }
                if ($bit) {
                    $length++;
                }

                if (!$bit || $pos == $lastPos) {
                    if ($length < 1) {
                        continue;
                    }
                    array_splice($submask, 0, $offset, array_fill(0, $offset, 0));
                    array_splice($submask, $offset, $length, array_fill($offset, $length, 1));

                    $statementsIntl = array_reverseByMask($statementsIntl, $submask, $statementsIntl);
                    $offset = $pos;
                    $length = 0;
                }
            }

            $sql .= implode("", $statementsIntl);
        }

        return $sql;
    }

    protected function getLocalizer(): Localizer
    {
        foreach ($this->getEntityManager()->getEventManager()->getListeners(Events::loadClassMetadata) as $listener) {
            if ($listener instanceof IntlSubscriber) {
                return $listener->getLocalizer();
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
        if ($relation === null) {
            return $sql;
        }

        // Extract source class information
        $sourceClass      = $this->getEntityManager()->getClassMetadata($relation['sourceEntity']);

        if (!class_implements_interface($sourceClass->getName(), TranslatableInterface::class)) {
            return $sql;
        }

        // Get target class
        $targetClass     = $this->getEntityManager()->getClassMetadata($relation['targetEntity']);
        if (!class_implements_interface($targetClass->getName(), TranslationInterface::class)) {
            return $sql;
        }

        //
        // Check whether target class is the root translation entity or not
        $assoc = !$relation['isOwningSide'] ? $targetClass->associationMappings[$relation['mappedBy']] : $relation;

        // Get root target class to replace target class 'translation_id'
        $rootTargetClass = $this->getEntityManager()->getClassMetadata($assoc['inherited'] ?? $relation['targetEntity']);
        if (!class_implements_interface($rootTargetClass->getName(), TranslationInterface::class)) {
            return $sql;
        }

        // Get source alias and its intl alias
        $sourceTableAlias = $this->getSQLTableAlias($sourceClass->getTableName(), $dqlAlias);
        $sourceIntlTableAlias = $this->getSQLTableAlias($targetClass->getTableName(), $joinedDqlAlias);

        //
        // Use `translatable_id` from root translation entity.
        $rootIntlTableAlias = $this->getSQLTableAlias($rootTargetClass->getTableName(), $joinedDqlAlias);
        $sql = str_replace(
            $sourceIntlTableAlias.".id = ".$rootIntlTableAlias.".id",
            $sourceTableAlias.".id = ".$rootIntlTableAlias.".".self::FOREIGN_KEY,
            $sql
        );

        //
        // Replace target family clause accordingly to root translation entity ID.
        $intlFamilyClass = array_map(fn ($c) => $this->getEntityManager()->getClassMetadata($c), get_family_class($targetClass->getName()));
        foreach ($intlFamilyClass as $currentClass) {
            $intlTableAlias = $this->getSQLTableAlias($currentClass->getTableName(), $joinedDqlAlias);

            if ($intlTableAlias == $rootIntlTableAlias) {
                continue;
            }
            if ($intlTableAlias == $sourceIntlTableAlias) {
                $sql = str_replace(
                    $sourceTableAlias.".id = ".$sourceIntlTableAlias.".".self::FOREIGN_KEY,
                    $sourceIntlTableAlias.".id = ".$rootIntlTableAlias.".id",
                    $sql
                );
            } else {
                $sql = str_replace(
                    $sourceIntlTableAlias.".id = ".$intlTableAlias.".id",
                    $intlTableAlias.".id = ".$rootIntlTableAlias.".id",
                    $sql
                );
            }
        }

        return $sql;
    }
}
