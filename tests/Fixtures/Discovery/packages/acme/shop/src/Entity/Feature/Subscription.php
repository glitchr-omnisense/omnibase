<?php

namespace Acme\Shop\Entity\Feature;

use Base\Mapping as ORM;

/** A feature's entity whose parent is another package's, which is not installed: never to be loaded. */
#[ORM\Entity]
class Subscription extends \Acme\Billing\Entity\Plan
{
}
