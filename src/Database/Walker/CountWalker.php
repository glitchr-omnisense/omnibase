<?php

namespace Base\Database\Walker;

use Doctrine\ORM\Query\AST\AggregateExpression;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\AST\SelectExpression;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\TreeWalkerAdapter;

class CountWalker extends TreeWalkerAdapter
{
    /**
     * Walks down a SelectStatement AST node, thereby generating the appropriate SQL.
     *
     * @return string The SQL.
     */
    public function walkSelectStatement(SelectStatement $AST)
    {
        $parent = null;
        $parentName = null;

        foreach ($this->getQueryComponents() as $dqlAlias => $qComp) {
            // skip mixed data in query
            if (isset($qComp['resultVariable'])) {
                continue;
            }

            if ($qComp['parent'] === null && $qComp['nestingLevel'] == 0) {
                $parent = $qComp;
                $parentName = $dqlAlias;
                break;
            }
        }

        $identifier = $parent['metadata']->getSingleIdentifierFieldName();
        $pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD | PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION, $parentName, $identifier);
        $pathExpression->type = PathExpression::TYPE_STATE_FIELD;

        $AST->selectClause->selectExpressions = array(
            new SelectExpression(
                new AggregateExpression('count', $pathExpression, true),
                null
            )
        );

        // ORDER BY is not needed, only increases query execution through unnecessary sorting.
        $AST->orderByClause = null;

        // GROUP BY will break things, we are trying to get a count of all
        $AST->groupByClause = null;
    }
}
