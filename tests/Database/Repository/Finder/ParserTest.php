<?php

namespace Tests\Base\Database\Repository\Finder;

use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Parser;
use PHPUnit\Framework\TestCase;

/**
 * The finder language read without Doctrine (plan "omnibase agnostic", 2026-11-01, step F1): each
 * word of the language on an entity described by its fields alone. The parse equals the one of
 * before F1 on Doctrine (tests/Database/Repository/FinderParityTest); here, what the Query says.
 */
final class ParserTest extends TestCase
{
    private static function parser(?\Closure $routeParameters = null): Parser
    {
        return new Parser(new Fields(Article::class, ['id', 'slug', 'state', 'publishedAt', 'parent', 'tags', 'email', 'username']), $routeParameters);
    }

    public function testEqualityAndTheSeparators(): void
    {
        $query = self::parser()->parse('findOneByEmailAndUsername', ['a@example.org', 'a']);
        self::assertSame('__findOneBy', $query->method);
        self::assertSame(Parser::REQUEST_FIND, $query->request);
        self::assertSame(Parser::SPECIAL_ONE, $query->special);
        self::assertSame(Parser::SEPARATOR_AND, $query->separator);
        self::assertEquals([
            new Criterion('email', 0, 'a@example.org', [Parser::OPTION_EQUAL]),
            new Criterion('username', 0, 'a', [Parser::OPTION_EQUAL]),
        ], $query->criterions());

        $query = self::parser()->parse('findByEmailOrUsername', ['a@example.org', 'a']);
        self::assertSame('__findBy', $query->method);
        self::assertSame(Parser::SEPARATOR_OR, $query->separator);
        self::assertNull($query->special);

        $this->expectExceptionMessage('AND/OR ambiguity');
        self::parser()->parse('findByEmailAndUsernameOrSlug', ['a', 'b', 'c']);
    }

    public function testTheOptionsOfACriterion(): void
    {
        $query = self::parser()->parse('cacheOneByStateAndSlugStartingWith', [['publish'], 'a-']);
        self::assertSame('__findOneBy', $query->method);
        [$state, $slug] = $query->criterions();
        self::assertTrue($state->is(Parser::OPTION_EQUAL));
        self::assertSame(['publish'], $state->value);
        self::assertSame('slug:0', $slug->key());
        self::assertTrue($slug->is(Parser::OPTION_STARTING_WITH));

        $criterions = self::parser()->parse('findByInsensitivePartialSlugAndPublishedAtYoungerThanAndIdNotEqualTo', ['Slug', '-1 day', 5])->criterions();
        self::assertSame([Parser::OPTION_PARTIAL, Parser::OPTION_INSENSITIVE, Parser::OPTION_EQUAL], $criterions[0]->options);
        self::assertSame([Parser::OPTION_YOUNGER], $criterions[1]->options);
        self::assertSame([Parser::OPTION_NOT_EQUAL], $criterions[2]->options);

        // The words without an argument give their own value.
        $criterions = self::parser()->parse('findByParentNullAndSlugEmptyAndPublishedAtIsOver', [])->criterions();
        self::assertNull($criterions[0]->value);
        self::assertSame('', $criterions[1]->value);
        self::assertSame('CURDATE()', $criterions[2]->value);
    }

    public function testTheClausesThatTakeTheirOwnArgument(): void
    {
        $criterions = self::parser()->parse('findByStateAndInstanceOfAndBut', ['publish', Article::class, 7])->criterions();
        self::assertSame(['state', 'instanceOf', 'id'], array_map(static fn (Criterion $c) => $c->field, $criterions));
        self::assertTrue($criterions[1]->is(Parser::OPTION_INSTANCEOF));
        self::assertSame(7, $criterions[2]->value);
        self::assertTrue($criterions[2]->is(Parser::OPTION_NOT_EQUAL));

        // A Query-by-Example: the entity's short name (or Model) takes an object's set fields.
        $select = new \stdClass();
        $select->state = 'publish';
        $select->slug = null;
        $criterions = self::parser()->parse('findByArticle', [$select])->criterions();
        self::assertSame('model', $criterions[0]->field);
        self::assertSame(['state' => 'publish'], $criterions[0]->value);
    }

    public function testWithRouteReadsTheRouteParametersThroughTheResolver(): void
    {
        $query = self::parser()->parse('cacheOneBySlugWithRouteAndState', [['slug' => 'a-slug'], 'slug', ['publish']]);
        self::assertSame('a-slug', $query->criterions()[0]->value);

        $request = new \ArrayObject(['slug' => 'from-the-host']);
        $query = self::parser(static fn (mixed $argument) => $argument instanceof \ArrayObject ? $argument->getArrayCopy() : false)->parse('findOneBySlugWithRoute', [$request, 'slug']);
        self::assertSame('from-the-host', $query->criterions()[0]->value);

        $this->expectExceptionMessage('requires route parameter');
        self::parser()->parse('findOneBySlugWithRoute', [$request, 'slug']);
    }

    public function testTheSpecials(): void
    {
        $query = self::parser()->parse('findLast3ByState', ['publish']);
        self::assertSame('__findLastBy', $query->method);
        self::assertSame(['last' => '3'], $query->extras());

        $query = self::parser()->parse('findAtMost2ByState', ['publish']);
        self::assertSame('__findAtMostBy', $query->method);
        self::assertSame(['atMost' => '2'], $query->extras());

        $query = self::parser()->parse('cachePreviousOneByClassOfAndState', [['id' => 5], Article::class, 'publish']);
        self::assertSame('__findPreviousOneBy', $query->method);
        self::assertSame(['prevKeys' => ['id']], $query->extras());
        self::assertTrue($query->criterions()[0]->is(Parser::OPTION_LOWER), 'before the one given');

        $query = self::parser()->parse('findNextOneByState', [['publishedAt' => '2026-05-01'], 'publish']);
        self::assertSame('__findNextOneBy', $query->method);
        self::assertTrue($query->criterions()[0]->is(Parser::OPTION_GREATER));

        self::assertSame('__findRandomlyBy', self::parser()->parse('findRandomlyByState', ['publish'])->method);

        $query = self::parser()->parse('findAll', [['id' => 'DESC']]);
        self::assertSame('__findAll', $query->method);
        self::assertSame([['id' => 'DESC']], $query->arguments);
        self::assertSame([], $query->criterions());
    }

    public function testCountsAndLengths(): void
    {
        $query = self::parser()->parse('countByStateAndPublishedAtYoungerThan', ['publish', '-1 month']);
        self::assertSame('__countBy', $query->method);
        self::assertSame(Parser::REQUEST_COUNT, $query->request);

        $query = self::parser()->parse('countForParentByState', ['publish']);
        self::assertSame('parent', $query->column);

        self::assertSame('__distinctCountBy', self::parser()->parse('distinctCountByState', ['publish'])->method);

        $query = self::parser()->parse('lengthOfTagsByState', ['publish']);
        self::assertSame('__lengthOfBy', $query->method);
        self::assertSame(Parser::REQUEST_LENGTH, $query->request);
        self::assertSame('tags', $query->column);
    }

    public function testTheCriteriaGivenBesideTheName(): void
    {
        $query = self::parser()->parse('findOneBySlug', ['a-slug', ['image.source' => 'a.jpg'], ['id' => 'DESC']]);
        self::assertSame(['image.source' => 'a.jpg', 'slug:0' => 'a-slug'], $query->arguments[0]);
        self::assertSame(['id' => 'DESC'], $query->arguments[1]);
        self::assertEquals([new Criterion('image.source', null, 'a.jpg'), new Criterion('slug', 0, 'a-slug', [Parser::OPTION_EQUAL])], $query->criterions());
    }

    public function testAFieldHoldingAWordOfTheLanguageIsRefused(): void
    {
        $this->expectExceptionMessage('has a field called "postedOrDrafted"');
        (new Parser(new Fields(Article::class, ['postedOrDrafted'])))->parse('findAll', []);
    }

    public function testAnUnknownRequestIsRefused(): void
    {
        $this->expectExceptionMessage('Undefined method "fetchBySlug"');
        self::parser()->parse('fetchBySlug', ['a']);
    }
}
