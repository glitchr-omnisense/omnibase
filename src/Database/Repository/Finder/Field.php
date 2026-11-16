<?php

namespace Base\Database\Repository\Finder;

/**
 * Another field of the same row (a path, one association deep or more), as a criterion's value: the two compared in
 * the database.
 *
 *     $desks->count(['desk' => $desks, 'conversation.lastSender' => Field::of('author')]);
 *     $participants->count([Group::of(Parser::SEPARATOR_AND, Criterion::of('lastReadAt', Field::of('conversation.lastMessageAt'), Parser::OPTION_LOWER))]);
 */
final class Field
{
    private function __construct(public readonly string $path)
    {
    }

    public static function of(string $path): self
    {
        return new self($path);
    }
}
