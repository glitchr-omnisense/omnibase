<?php

namespace Base\Model;

interface AutocompleteInterface
{
    public function __autocomplete(): ?string;
    public function __autocompleteData(): array;
}
