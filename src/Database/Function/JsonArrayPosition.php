<?php

namespace Base\Database\Function;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\TokenType;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class JsonArrayPosition extends FunctionNode
{
    public $jsonExpr;
    public $searchExpr;

	const FUNCTION_NAME = 'JSON_EXTRACT';
    
    public function __construct() {
        parent::__construct(self::FUNCTION_NAME);
    }

    public function parse(Parser $parser): void
    {
        // JSON_ARRAY_POSITION(jsonExpr, searchExpr)
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonExpr   = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->searchExpr = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $walker): string
    {
        return sprintf(
            'JSON_ARRAY_POSITION(%s, %s)',
            $this->jsonExpr->dispatch($walker),
            $this->searchExpr->dispatch($walker)
        );
    }
}