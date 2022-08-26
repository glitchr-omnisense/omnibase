<?php

namespace Base\Service\Model;

interface AutocompleteInterface
{
    public function __autocomplete(): ?string;
    public function __autocompleteData(): array;
}
