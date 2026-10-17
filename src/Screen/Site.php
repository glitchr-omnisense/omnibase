<?php

namespace Base\Screen;

/** The site around every page: its name, its slogan, its logo's address, the year, its description (lesringostars' frame `site`). */
final readonly class Site implements Screen
{
    public function __construct(
        public ?string $title = null,
        public ?string $slogan = null,
        public ?string $logo = null,
        public string $age = '',
        public ?string $description = null,
    ) {
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'slogan' => $this->slogan, 'logo' => $this->logo, 'age' => $this->age, 'description' => $this->description];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
