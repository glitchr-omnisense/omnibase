<?php

namespace Base\Entity\Layout\Attribute;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Entity\Layout\Attribute\Common\AbstractRule;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\RuleRepository;
use Base\Database\Attribute\Cache;

/**
 * A condition, stored: "the score is at least 100", "we are in December".
 *
 * AbstractRule has been abstract and childless since 2022, so nothing could
 * ever be persisted against it. This is the concrete class that makes the
 * engine usable, and it stays deliberately empty: a rule is nothing but a
 * value plus the adapter that knows how to read it, both of which the parent
 * already holds. What varies between "a score threshold" and "a date window"
 * is the ADAPTER, not the rule - see ScoreAdapter and DateRangeAdapter.
 *
 * That is what makes one table able to express a coupon's validity period
 * and a group's entry requirement without either knowing about the other.
 */
#[ORM\Entity(repositoryClass: RuleRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "rule")]
class Rule extends AbstractRule implements IconizeInterface
{
    public function __construct(AbstractAdapter $adapter, mixed $value = null)
    {
        parent::__construct($adapter);
        $this->setValue($value);
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-scale-balanced"];
    }

    /**
     * A rule's value is its condition, not a translated piece of content, so
     * the $locale these three carry for TranslatableInterface's sake is
     * meaningless here and deliberately ignored: "score >= 100" does not read
     * differently in English.
     */
    public function get(?string $locale = null): mixed
    {
        return $this->getValue();
    }

    public function set(...$args): self
    {
        return array_key_exists("value", $args) ? $this->setValue($args["value"]) : $this;
    }

    public function resolve(?string $locale = null): mixed
    {
        return $this->adapter ? $this->adapter->resolve($this->getValue()) : null;
    }
}
