<?php

namespace Base\Database;

interface TranslatableInterface
{
    public function translate(?string $locale);
}
