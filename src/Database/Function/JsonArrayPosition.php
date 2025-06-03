<?php
declare(strict_types=1);

namespace Base\Database\Function;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use PDO;

class JsonArrayPosition extends FunctionNode
{

    public const FUNCTION_NAME = 'JSON_ARRAY_POSITION';
    public function __construct() {
        parent::__construct(self::FUNCTION_NAME);
    }

    /** @var PathExpression */  public $jsonExpr;
    /** @var PathExpression */  public $searchExpr;

    public function parse(Parser $parser): void
    {
        // The DQL parser has matched “JSON_ARRAY_POSITION”
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        // First argument: JSON column or literal
        $this->jsonExpr = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_COMMA);

        // Second argument: value or field to search
        $this->searchExpr = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $walker): string
    {
        // Convert DQL PathExpressions to SQL fragments:
        $jsonSql   = $this->jsonExpr->dispatch($walker);   // e.g. "t0_.owner_orders"
        $searchSql = $this->searchExpr->dispatch($walker); // e.g. "o1_.id"

        // Determine the platform and server version:
        $em         = $walker->getEntityManager();
        $conn       = $em->getConnection();
        $platform   = $conn->getDatabasePlatform();

        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            return sprintf(
                '(SELECT jt.idx - 1 ' .
                ' FROM JSON_TABLE(%1$s, \'$[*]\' COLUMNS (' .
                '     idx FOR ORDINALITY,' .
                '     val JSON PATH \'$\'' .
                ' )) AS jt ' .
                ' WHERE jt.val = %2$s)',
                $jsonSql,
                $searchSql
            );
        }

        if ($platform instanceof PostgreSQLPlatform) {
            return sprintf(
                '(SELECT elem_index - 1 ' .
                '   FROM jsonb_array_elements(%1$s) WITH ORDINALITY AS elems(elem_value, elem_index) ' .
                '  WHERE elems.elem_value = to_jsonb(%2$s))',
                $jsonSql,
                $searchSql
            );
        }

        // 4) Fallback: emit JSON_ARRAY_POSITION(...) (hoping a stored function exists)
        return sprintf(
            'JSON_ARRAY_POSITION(%s, %s)',
            $jsonSql,
            $searchSql
        );
    }
}