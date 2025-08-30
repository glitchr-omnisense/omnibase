<?php

namespace Base\Service;

use Base\Cache\Abstract\AbstractLocalCache;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 *
 */
class Themizer extends AbstractLocalCache implements ThemizerInterface
{
    protected $parameterBag;
    protected $tokenStorage;

    protected string $projectDir;

    public function __construct(ParameterBagInterface $parameterBag, TokenStorageInterface $tokenStorage, string $cacheDir, ?string $buildDir = null)
    {
        $this->parameterBag = $parameterBag;
        $this->tokenStorage = $tokenStorage;
        $this->projectDir = $parameterBag->get('kernel.project_dir');

        parent::__construct($cacheDir, $buildDir);
    }

    public function getLayout($id = 0): string
    {
        if(array_key_exists($id, self::$layouts)) {
            if(self::$layouts[$id] === null) {
                throw new \RuntimeException("No layout `layout$id.html.twig` found in ".$this->projectDir . '/templates/layout/');
            }
            return self::$layouts[$id];
        }

        $layout = first(self::$layouts);
        if($layout === null) {
            throw new \RuntimeException("No default layout found in ".$this->projectDir . '/templates/layout/, Themizer::$layouts must be empty.');
        }

        return $layout;
    }
    
    protected static array $layouts = [];
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        self::$layouts = $this->getCache("/Theme/Layouts", self::detectLayouts());
        return [];
    }

    public function detectLayouts()
    {
        $layouts = [];
        $layoutDir = $this->projectDir . '/templates/layout/';
        if (is_dir($layoutDir)) {
            $files = scandir($layoutDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $filePath = $layoutDir . $file;
                // Only match files like layoutX.html.twig
                if (is_file($filePath) && preg_match('/^layout(\d+)\.html\.twig$/', $file, $matches)) {
                    $index = (int)$matches[1];
                    $layouts[$index] = basename($filePath);
                }
            }
        }

        return $layouts;
    }
    
    public function getAvailableModes(): array
    {
        $modes = $this->parameterBag->get('base.twig.themes.modes') ?? [];
        foreach ($modes as $key => $mode) {
            $modes[$key]["id"] = $key;
            $modes[$key]["class"] = "theme-mode-{$key}";
        }

        return $modes;
    }

    public function getMode(): ?array
    {
        // Get the list of valid modes from parameters
        $modesByName = $this->getAvailableModes();
        $firstModeName = first(array_keys($modesByName));
        $defaultModeName = $this->parameterBag->get('base.twig.themes.mode') ?? $firstModeName;
        $selectedModeName = $defaultModeName;
        
        if ($defaultModeName === $firstModeName && isset($_COOKIE['USER/THEME/MODE'])) {
            $cookieMode = strtolower($_COOKIE['USER/THEME/MODE']);
            if (isset($modesByName[$cookieMode])) {
                $selectedModeName = $cookieMode;
            }
        }

        if($selectedModeName === null) return null;
        if(!array_key_exists($selectedModeName, $modesByName) ) return null;

        $mode = $modesByName[$selectedModeName];
        $mode["id"] = $selectedModeName;
        return $mode;
    }

    public function getAvailableFilters(): array
    {
        $filtersByName = $this->parameterBag->get('base.twig.themes.filters') ?? [];
        foreach ($filtersByName as $name => &$filter) {
            $filter["id"] = $name;
            $filter["class"] = "theme-filter-{$name}";
        }

        return $filtersByName;
    }

    public function getFilter(): ?array
    {
        // Get the list of valid modes from parameters
        $filtersByName = $this->getAvailableFilters();
        $selectedFilterName = null;
        if (isset($_COOKIE['USER/THEME/FILTER'])) {
            $cookieFilter = strtolower($_COOKIE['USER/THEME/FILTER']);
            if (isset($filtersByName[$cookieFilter])) {
                $selectedFilterName = $cookieFilter;
            }
        }

        if($selectedFilterName === null) $selectedFilterName = first(array_keys($filtersByName));
        if(!array_key_exists($selectedFilterName, $filtersByName) ) return null;
        
        $filter = $filtersByName[$selectedFilterName];
        $filter["id"] = $selectedFilterName;
        return $filter;
    }

    public function getAudienceCategories(): array
    {
        $audiences = $this->parameterBag->get('base.twig.themes.audiences') ?? [];
        foreach ($audiences as $key => $audience) {
            $audiences[$key]["id"] = $key;
            $audiences[$key]["class"] = "theme-audience-{$key}";
        }

        return $audiences;
    }

    public function getAudience(): ?array
    {
        $audiencesByName = $this->getAudienceCategories();
        if (empty($audiencesByName)) {
            return null;
        }

        $user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $userAge = $user !== null && method_exists($user, 'getAge') ? $user->getAge() : null;

        $defaultAudience = $this->parameterBag->get('base.twig.themes.default_audience') ?? null;
        if ($userAge === null) {

            if($defaultAudience === null) return null;
            if(!\array_key_exists($defaultAudience, $audiencesByName)) return null;

            $audience = $audiencesByName[$defaultAudience];
            $audience['id'] = $defaultAudience;
            return $audience;
        }

        uasort($audiencesByName, function ($a, $b) {
            $ageA = $a['age'] ?? 0;
            $ageB = $b['age'] ?? 0;
            return $ageA <=> $ageB;
        });

        $selectedAudience = null;
        foreach ($audiencesByName as $audience) {
            if (isset($audience['age']) && $userAge <= $audience['age']) {
                $selectedAudience = $audience;
            }
        }

        if($selectedAudience === null) return null;
        if(!\array_key_exists($selectedAudience, $audiencesByName)) return null;

        $audience = $audiencesByName[$selectedAudience];
        $audience['id'] = $selectedAudience;
        return $audience;
    }

    /**
     * Returns the list of candidate themes (events) that are currently active.
     *
     * @return array|null
     */
    public function getAvailableEvents(): array
    {
        $events = $this->parameterBag->get('base.twig.themes.events') ?? [];
        foreach ($events as $key => $event) {
            $events[$key]["id"] = $key;
            $events[$key]["class"] = "theme-event-{$key}";
        }

        return $events;
    }

    public function getEvent(): ?array
    {
        $events = $this->getAvailableEvents();
        
        $activeEvents = [];
        foreach ($events as $name => $event) {

            // Check for begin and end if present (inclusive)
            if (isset($event['begin']) && isset($event['end'])) {
                $now = new \DateTimeImmutable();
                $begin = new \DateTimeImmutable($event['begin']);
                $end = new \DateTimeImmutable($event['end']);
                // If 'begin' and 'end' are on the same day but 'end' is before 'begin', assume it spans midnight
                if ($end < $begin && $begin->format('Y-m-d') === $end->format('Y-m-d')) {
                    $end = $end->modify('+1 day');
                }

                if ($now < $begin || $now > $end) {
                    continue;
                }
            }

            $activeEvents[$name]["id"] = $name;
            $activeEvents[$name] = $event;
        }

        // Sort by priority (descending), then by begin (ascending)
        uasort($activeEvents, function ($a, $b) {
            $priorityA = $a['priority'] ?? 0;
            $priorityB = $b['priority'] ?? 0;
            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA; // Higher priority first
            }

            $beginA = isset($a['begin']) ? strtotime($a['begin']) : 0;
            $beginB = isset($b['begin']) ? strtotime($b['begin']) : 0;
            return $beginB <=> $beginA; // Later begin first (descending)
        });

        // Remove 'priority' from each event after sorting
        foreach ($activeEvents as &$event) {
            unset($event['priority']);
        }

        if(empty($activeEvents)) return null;
        return first($activeEvents);
    }

}
