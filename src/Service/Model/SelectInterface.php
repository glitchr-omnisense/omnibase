<?php

namespace Base\Service\Model;

interface SelectInterface
{
    public static function getIcon(string $id, int $index = -1): ?string;
    public static function getText(string $id): ?string;
    public static function getHtml(string $id): ?string;
    public static function getData(string $id): ?array;
}
