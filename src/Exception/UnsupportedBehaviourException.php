<?php

namespace Base\Exception;

/**
 * A behaviour (#[Vault], #[Slugify], #[Cache]...) needs a part of the
 * persistence that the installed one does not provide: refused aloud, where a
 * behaviour that found nothing would leave a row unsealed, a slug not unique or
 * a cache stale in silence (plan "omnibase agnostic", step F2b).
 */
final class UnsupportedBehaviourException extends \LogicException
{
    public static function for(string $behaviour, object $unitOfWork): self
    {
        return new self(sprintf(
            'No %s for the unit of work %s: the persistence installed does not implement this behaviour\'s part. '
            .'omnibridge/doctrine registers its own (Base\Bridge\Doctrine\Behaviour\DoctrineBehaviours until step F3); another persistence registers its implementation with Base\Database\Attribute\Backend\Backends::register().',
            $behaviour,
            get_debug_type($unitOfWork)
        ));
    }
}
