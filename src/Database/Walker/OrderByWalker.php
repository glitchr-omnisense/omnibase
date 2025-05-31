<?php

namespace Base\Database\Walker;

use Base\Database\Function\JsonArrayPosition;
use Doctrine\ORM\Query\AST\OrderByClause;
use Doctrine\ORM\Query\AST\OrderByItem;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\TreeWalkerAdapter;
use Doctrine\ORM\Query\AST\Join;
use Doctrine\ORM\Query\AST\JoinAssociationPathExpression;

class OrderByWalker extends TreeWalkerAdapter
{
    public const HINT_ORDER_ARRAY = "order_by.array";

    public function walkSelectStatement(SelectStatement $AST): void
    {
        $q = $this->_getQuery();
        if (! $q->hasHint(self::HINT_ORDER_ARRAY)) {
            return;
        }

        // pull our hint values:
        $orderArray = $q->getHint(self::HINT_ORDER_ARRAY);
        foreach ($orderArray as $prop => $orders) {

            [$orderBy, $sort] = $orders;            
            
            $identVarDecl = $AST->fromClause->identificationVariableDeclarations[0];
            $rootDecl   = $identVarDecl->rangeVariableDeclaration;
            $rootAlias  = $rootDecl->aliasIdentificationVariable;

            $joinAlias = null;
            foreach ($identVarDecl->joins as $join) {

                $assocDecl = $join->joinAssociationPathExpression;
                if ($assocDecl->joinAssociationPathExpression->field === $prop) {
                    $joinAlias = $assocDecl->aliasIdentificationVariable;
                    break;
                }
            }

            if ($joinAlias === null) {

                $initial = strtolower($prop[0]);

                $usedIndexes = [];
                foreach ($identVarDecl->joins as $join) {
                    $alias = $join->joinAssociationPathExpression->aliasIdentificationVariable;
                    if (stripos($alias, $initial) === 0) {
                        $suffix = substr($alias, strlen($initial));
                        $usedIndexes[] = (int) $suffix;
                    }
                }

                $index = 1;
                while (in_array($index, $usedIndexes, true)) {
                    $index++;
                }

                $joinAlias = $initial . $index;
                $pathExpr  = new PathExpression(PathExpression::TYPE_STATE_FIELD, $rootAlias, $prop);
                $assocDecl = new JoinAssociationPathExpression( $pathExpr, $joinAlias, null);
                $newJoin   = new Join(Join::JOIN_TYPE_LEFT, $assocDecl);
                $identVarDecl->joins[] = $newJoin;
            }

            $func = new JsonArrayPosition();
            $func->jsonExpr   = new PathExpression(PathExpression::TYPE_STATE_FIELD, $rootAlias, $orderBy);
            $func->searchExpr = new PathExpression(PathExpression::TYPE_STATE_FIELD, $joinAlias, 'id');

            $orderItem = new OrderByItem($func);
            $orderItem->type = $sort;

            if ($AST->orderByClause === null) {
                $AST->orderByClause = new OrderByClause([$orderItem]);
            } else {
                $AST->orderByClause->orderByItems[] = $orderItem;
            }
        }
    }
}
