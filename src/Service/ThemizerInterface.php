<?php

namespace Base\Service;

interface ThemizerInterface
{
    public function getAvailableFilters(): array;
    public function getFilter(): ?array;
    
    public function getAvailableEvents(): array;
    public function getEvent(): ?array;
    
    public function getAvailableModes(): array;
    public function getMode(): ?array;
    public function color_scheme(): string;

    public function getAvailableWidths(): array;
    public function getWidth(): ?array;
    public function width(): ?array;

    public function getAudienceCategories(): array;
    public function getAudience(): ?array;


}
