<?php

namespace Acme\Shop\Enum;

use Base\Database\Type\EnumType;

/** A package's enumeration: a column type wherever the package is installed. */
class OrderStatus extends EnumType
{
    public const OPEN = 'open';
    public const PAID = 'paid';

    public static function getStaticName(): string
    {
        return 'acme_order_status';
    }
}
