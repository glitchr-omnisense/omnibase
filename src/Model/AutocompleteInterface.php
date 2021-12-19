<?php

namespace Base\Model;

interface AutocompleteInterface
{
    public function __autocomplete(): ?string;
}
