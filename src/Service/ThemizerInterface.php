<?php

namespace Base\Service;

interface ThemizerInterface
{
    public function filter(): ?array;
    public function mode(): ?array;
    public function audience(): ?array;
    public function events(): ?array;
}
