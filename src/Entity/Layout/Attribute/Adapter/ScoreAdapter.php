<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractRuleAdapter;
use Base\Field\Type\NumberType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\ScoreAdapterRepository;
use Base\Database\Attribute\Cache;

/**
 * "Is this account's score inside this band?"
 *
 * The rule's value is a band, either end of which may be omitted:
 *
 *     {"min": 100}              at least 100
 *     {"max": -50}              at most -50, i.e. in trouble
 *     {"min": 100, "max": 499}  a single level's worth
 *
 * min is inclusive and max is exclusive, so consecutive bands can be written
 * as {0,100}, {100,250}, {250,500} without a gap or an overlap at the seam.
 *
 * The subject is anything that can state a score - a User today, and a Group
 * as well, since both grew a getScore(). Deliberately duck-typed rather than
 * type-hinted on User: this class lives in the layout engine, and making the
 * condition engine depend on the user model would stop the same rule being
 * pointed at anything else that learns to score itself later.
 *
 * A subject that cannot state a score does not "fail" the rule, it is simply
 * not something this rule can judge - see supports() and compliesWith().
 */
#[ORM\Entity(repositoryClass: ScoreAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "score")]
class ScoreAdapter extends AbstractRuleAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-chart-simple"];
    }

    public static function getType(): string
    {
        return NumberType::class;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function resolve(mixed $value): mixed
    {
        return $this->band($value);
    }

    public function supports(mixed $value): bool
    {
        $band = $this->band($value);

        return $band["min"] !== null || $band["max"] !== null;
    }

    public function compliesWith(mixed $value, mixed $subject): bool
    {
        $score = $this->scoreOf($subject);
        if ($score === null) {
            return false;
        }

        $band = $this->band($value);

        if ($band["min"] !== null && $score < $band["min"]) {
            return false;
        }

        // Exclusive, so adjacent bands meet without overlapping.
        if ($band["max"] !== null && $score >= $band["max"]) {
            return false;
        }

        return true;
    }

    /** @return array{min: int|null, max: int|null} */
    private function band(mixed $value): array
    {
        // A bare number is the common case ("at least N"), and writing it out
        // as {"min": N} every time would be noise in the admin form.
        if (is_int($value) || (is_string($value) && is_numeric($value))) {
            return ["min" => (int) $value, "max" => null];
        }

        if (!is_array($value)) {
            return ["min" => null, "max" => null];
        }

        $min = $value["min"] ?? null;
        $max = $value["max"] ?? null;

        return [
            "min" => is_numeric($min) ? (int) $min : null,
            "max" => is_numeric($max) ? (int) $max : null,
        ];
    }

    private function scoreOf(mixed $subject): ?int
    {
        if (is_int($subject)) {
            return $subject;
        }

        if (is_object($subject) && method_exists($subject, "getScore")) {
            $score = $subject->getScore();

            return is_numeric($score) ? (int) $score : null;
        }

        return null;
    }
}
