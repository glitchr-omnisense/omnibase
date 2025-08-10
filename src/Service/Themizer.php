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
    
    public function modes(): ?array
    {
        return $this->parameterBag->get('base.twig.modes') ?? null;
    }

    public function mode(): ?array
    {  
        // Get the list of valid modes from parameters
        $modesByName = $this->parameterBag->get('base.twig.modes') ?? [];
        foreach ($modesByName as $key => $mode) {
            $modesByName[$key]["class"] = "data-mode-{$key}";
        }

        $firstModeName = first(array_keys($modesByName));
        $defaultModeName = $this->parameterBag->get('base.twig.mode') ?? $firstModeName;
        $selectedModeName = $defaultModeName;
        
        if ($defaultModeName === $firstModeName && isset($_COOKIE['USER/THEME/MODE'])) {
            $cookieMode = strtolower($_COOKIE['USER/THEME/MODE']);
            if (isset($modesByName[$cookieMode])) {
                $selectedModeName = $cookieMode;
            }
        }

        return \array_key_exists($selectedModeName, $modesByName) 
            ? [$selectedModeName => $modesByName[$selectedModeName]] : null;
    }

    public function filters(): ?array
    {
        return $this->parameterBag->get('base.twig.filters') ?? null;
    }

    public function filter(): ?array
    {
        // Get the list of valid modes from parameters
        $filtersByName = $this->parameterBag->get('base.twig.filters') ?? [];
        foreach ($filtersByName as $name => &$filter) {
            $filter["class"] = "data-filter-{$name}";
        }

        $selectedFilterName = null;
        if (isset($_COOKIE['USER/THEME/FILTER'])) {
            $cookieFilter = strtolower($_COOKIE['USER/THEME/FILTER']);
            if (isset($filtersByName[$cookieFilter])) {
                $selectedFilterName = $cookieFilter;
            }
        }

        return $selectedFilterName !== null && \array_key_exists($selectedFilterName, $filtersByName) 
            ? [$selectedFilterName => $filtersByName[$selectedFilterName]] : null;
    }

    public function audience(): ?array 
    {
        $audiencesByName = $this->parameterBag->get('base.twig.audiences') ?? [];
        foreach ($audiencesByName as $key => $audience) {
            $audiencesByName[$key]["class"] = "data-audience-{$key}";
        }

        $user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;
        $userAge = $user !== null && method_exists($user, 'getAge') ? $user->getAge() : null;

        $defaultAudience = $this->parameterBag->get('base.twig.default_audience') ?? null;
        if ($userAge === null) {

            if($defaultAudience === null) return null;
            return \array_key_exists($defaultAudience, $audiencesByName) 
                    ? [$defaultAudience => $audiencesByName[$defaultAudience]] : null;
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

        return $selectedAudience !== null && \array_key_exists($selectedAudience, $audiencesByName) 
            ? [$selectedAudience => $audiencesByName[$selectedAudience]] : null;
    }

    /**
     * Returns the list of candidate themes (events) that are currently active.
     *
     * @return array|null
     */
    public function events(): ?array { return $this->getEvents(); }
    public function getEvents(): ?array
    {
        $events = $this->parameterBag->get('base.twig.events') ?? [];
        foreach ($events as $key => $event) {
            $events[$key]["class"] = "data-event-{$key}";
        }

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

            $activeEvents[$name] = $event;
        }

        // Sort by priority (descending), then by begin (ascending)
        uasort($activeEvents, function ($a, $b) {

            $priorityA = $a['priority'] ?? 0;
            $priorityB = $b['priority'] ?? 0;
            if ($priorityA !== $priorityB) {
            return $priorityB <=> $priorityA;
            }

            $beginA = isset($a['begin']) ? strtotime($a['begin']) : 0;
            $beginB = isset($b['begin']) ? strtotime($b['begin']) : 0;
            return $beginA <=> $beginB;
        });

        // Remove 'priority' from each event after sorting
        foreach ($activeEvents as &$event) {
            unset($event['priority']);
        }

        return !empty($activeEvents) ? $activeEvents : null;
    }

}
