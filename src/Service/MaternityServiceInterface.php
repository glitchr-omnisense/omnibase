<?php

namespace Base\Service;

use DateTime;
use Symfony\Component\HttpKernel\Event\RequestEvent;

interface MaternityServiceInterface
{
    public function getBirthdate(?string $locale = null) : ?DateTime;
    public function isBorn(?string $locale = null) : ?bool;
    public function getAge(?string $locale = null) : string;

    public function redirectOnDeny(?RequestEvent $event = null): bool;
}
