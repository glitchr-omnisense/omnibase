<?php

namespace Base\Database\Walker;

use Doctrine\ORM\Query\AST\GroupByClause;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\TreeWalkerAdapter;
use InvalidArgumentException;

class GroupByWalker extends TreeWalkerAdapter
{
    public const HINT_GROUP_ARRAY = "group_by.array";

    /**
     * Walks down a SelectStatement AST node, thereby generating the appropriate SQL.
     *
     * @return string The SQL.
     */
    public function walkSelectStatement(SelectStatement $AST)
    {
        $parentName = null;
        foreach ($this->getQueryComponents() AS $dqlAlias => $qComp) {

            // skip mixed data in query
            if (isset($qComp['resultVariable'])) {
                continue;
            }

            if ($qComp['parent'] === null && $qComp['nestingLevel'] == 0) {
                $parentName = $dqlAlias;
                break;
            }
        }

        $groupBy = $this->_getQuery()->getHint(self::HINT_GROUP_ARRAY);
        if($groupBy !== null && !is_array($groupBy))
            throw new InvalidArgumentException("Invalid hint \"".self::HINT_GROUP_ARRAY."\" type provided. Array expected");

        foreach($groupBy as $columnName) {

            $pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD | PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION, $parentName, $columnName);
            $pathExpression->type = PathExpression::TYPE_STATE_FIELD;
        }

        $AST->groupByClause ??= new GroupByClause([]);
        $AST->groupByClause->groupByItems[] = $pathExpression;
    }
}