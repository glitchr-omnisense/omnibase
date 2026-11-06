<?php

namespace Base\Console\Task;

/** cache:clear's options and what the host knows of itself (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class CacheClearInput
{
    /**
     * @param bool  $checks                omnibase's checks around the host's clear (not --no-extension)
     * @param bool  $optionalWarmers       the optional cache warmers run (not --no-optional-warmers nor --no-warmup)
     * @param bool  $sessions              the sessions killed after the clear (--sessions)
     * @param ?bool $phpInfo               public/phpinfo.php written (debug: true) or removed (false); null: the host has none
     * @param ?bool $hostFallback          whether the host's router has a host fallback (null: it has no such router)
     * @param ?bool $customMetadataFactory whether the persistence's metadata factory is omnibase's (null: not asked)
     */
    public function __construct(
        public string $projectDir,
        public string $cacheDir,
        public bool $checks = true,
        public bool $optionalWarmers = true,
        public bool $sessions = false,
        public ?bool $phpInfo = null,
        public ?bool $hostFallback = null,
        public ?bool $customMetadataFactory = null,
    ) {
    }

    /** The counter of clears beside the cache folder. */
    public function clears(): CacheClears
    {
        return new CacheClears(rtrim($this->cacheDir, '/').'.txt');
    }
}
