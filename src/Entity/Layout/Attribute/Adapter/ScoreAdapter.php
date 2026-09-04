<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractRuleAdapter;
use Base\Enum\Operation;
use Base\Field\Type\NumberType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\ScoreAdapterRepository;
use Base\Database\Attribute\Cache;

/**
 * "Is this account's score <operation> this number?"
 *
 * Modelled on the marketplace's TotalPriceAdapter rather than on a bespoke
 * shape: the comparison is a stored Operation (LESS, GREATER_EQUAL, EQUAL...)
 * rather than baked into the class, so one adapter covers every threshold a
 * rule might want instead of needing a MinScoreAdapter and a MaxScoreAdapter.
 * It also means a band is expressed the way the engine already expresses
 * everything else - as two rules on the same subject, AND-ed together by the
 * caller, which is exactly what MarketplaceManager does with $validRules &=.
 *
 * The subject is anything that can state a score - a User today, and a Group,
 * since both grew a getScore(). Deliberately duck-typed rather than hinted on
 * User: this class lives in the layout engine, and hinting it there would stop
 * the same rule judging anything else that learns to score itself later. A
 * subject that cannot state one returns false, the same way TotalPriceAdapter
 * returns false for anything that is not an Order.
 */
#[ORM\Entity(repositoryClass: ScoreAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "rule_score")]
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
        return $value;
    }

    public function supports(mixed $value): bool
    {
        return is_numeric($value);
    }

    public function compliesWith(mixed $value, mixed $subject): bool
    {
        $score = $this->scoreOf($subject);
        if ($score === null || !is_numeric($value)) {
            return false;
        }

        $value = (int) $value;

        return match ($this->operation) {
            Operation::LT  => $score <   $value,
            Operation::LTE => $score <=  $value,
            Operation::EQ  => $score === $value,
            Operation::NEQ => $score !== $value,
            Operation::GT  => $score >   $value,
            Operation::GTE => $score >=  $value,
            // An adapter saved without an operation should not silently
            // behave like one - see the class comment on why false.
            default => false,
        };
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

    #[ORM\Column(type: "operation")]
    protected $operation;

    public function getOperation()
    {
        return $this->operation;
    }

    public function setOperation($operation): self
    {
        $this->operation = $operation;

        return $this;
    }
}
