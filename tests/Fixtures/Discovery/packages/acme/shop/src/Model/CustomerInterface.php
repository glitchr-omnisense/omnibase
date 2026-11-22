<?php

namespace Acme\Shop\Model;

use Base\Database\Attribute\ResolveTargetEntity;

/** Whom an order is for: the application's user by default. */
#[ResolveTargetEntity('App\Entity\User')]
interface CustomerInterface
{
}
