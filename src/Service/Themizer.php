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
    
    public function getMode(): ?array
    {
        // Get the list of valid modes from parameters
        $validModes = $this->parameterBag->get('base.twig.modes') ?? [];

        // Normalize modes: ensure each has 'name', 'class', and 'icon'
        $modesByName = [];
        foreach ($validModes as $mode) {
            $name = $mode['name'] ?? null;
            if (!$name) {
                continue;
            }
            $modesByName[$name] = [
                'name' => $name,
                'class' => $mode['class'] ?? $name,
                'icon' => $mode['icon'] ?? null,
            ];
        }

        $defaultModeName = $this->parameterBag->get('base.twig.mode') ?? 'auto';
        $selectedModeName = $defaultModeName;

        if ($defaultModeName === 'auto' && isset($_COOKIE['USER/THEME/MODE'])) {
            $cookieMode = strtolower($_COOKIE['USER/THEME/MODE']);
            if (isset($modesByName[$cookieMode])) {
                $selectedModeName = $cookieMode;
            }
        }

        if (!isset($modesByName[$selectedModeName])) {
            $selectedModeName = array_key_first($modesByName);
        }

        return $modesByName[$selectedModeName] ?? null;
    }

    public function getAudience(?string $name = null): ?array
    {
        $audiences = $this->parameterBag->get('base.twig.audiences') ?? [];
        $user = $this->tokenStorage->getToken() ? $this->tokenStorage->getToken()->getUser() : null;

        if ($name === null) {
            $audience = $audiences[0] ?? null;
        } else {
            $audience = null;
            foreach ($audiences as $a) {
                if (isset($a['name']) && $a['name'] === $name) {
                    $audience = $a;
                    break;
                }
            }
        }

        if ($audience && isset($audience['age'])) {

            $userAge = null;
            if (is_object($user) && method_exists($user, 'getAge')) {
                $userAge = $user->getAge();
            } elseif (is_array($user) && isset($user['age'])) {
                $userAge = $user['age'];
            }

            if ($userAge === null || $userAge < $audience['age']) {
                return $audiences[$this->parameterBag->get('base.twig.default_audience')] ?? null;
            }
        }

        return $audience;
    }

    /**
     * Returns the list of candidate themes (events) that are currently active.
     *
     * @return array|null
     */
    public function getEvents(): ?array
    {
        $events = $this->parameterBag->get('base.twig.events') ?? [];
        $activeEvents = [];

        foreach ($events as $name => $event) {
            // Check for begin and end if present (inclusive)
            if (isset($event['begin']) && isset($event['end'])) {
                $now = new \DateTimeImmutable();
                $begin = new \DateTimeImmutable($event['begin']);
                $end = new \DateTimeImmutable($event['end']);

                if ($now < $begin || $now > $end) {
                    // Event is not active
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

        return !empty($activeEvents) ? $activeEvents : null;
    }
}
