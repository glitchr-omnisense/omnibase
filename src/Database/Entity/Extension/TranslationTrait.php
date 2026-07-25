<?php

namespace Base\Database\Entity\Extension;

use Base\Database\Mapping\NamingStrategy;
use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Service\Localizer;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait TranslationTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Translatable related methods
     */
    public static function getEntityFqcn(): string
    {
        return self::getTranslatableEntityClass()::getTranslationEntityClass();
    }

    public static function getTranslatableEntityClass(): string
    {
        // By default, the translatable class has the same name but without the suffix
        return substr(static::class, 0, -strlen(NamingStrategy::TABLE_I18N_SUFFIX));
    }

    /**
     * Will be mapped to translatable entity by TranslatableSubscriber
     *
     * @var TranslatableInterface
     */
    protected $translatable;

    public function getTranslatable(): ?TranslatableInterface
    {
        return $this->translatable;
    }

    /**
     * @param TranslatableInterface|null $translatable
     * @return $this
     */
    public function setTranslatable(?TranslatableInterface $translatable)
    {        
        $this->translatable = $translatable;
        return $this;
    }

    #[ORM\Column(type:"string", length:5)]
    #[Assert\Locale(canonicalize: true)]
    protected $locale;

    public function getLocale(): ?string
    {
        if ($this->locale) {
            return Localizer::normalizeLocale($this->locale);
        }

        return $this->getTranslatable()?->getTranslations()->indexOf($this);

    }

    /**
     * @param string $locale
     * @return $this
     */
    public function setLocale(string $locale)
    {
        $this->locale = Localizer::normalizeLocale($locale);
        return $this;
    }

    public function isEmpty(array $addIgnoredVars = [], ?callable $addConditions = null): bool
    {
        $ignoredVars = array_unique(array_merge(['id', 'translatable', 'locale'], $addIgnoredVars));
        $ignoredVars = array_intersect(array_keys(get_object_vars($this)), $ignoredVars);

        foreach (get_object_vars($this) as $var => $value) {
            if (in_array($var, $ignoredVars, true)) {
                continue;
            }
            if ($value === null) {
                continue;
            }

            // A multi-value choice widget (e.g. a "keywords" tag picker)
            // left genuinely empty by the user can still submit an array
            // containing only blank strings ([""], not the literal [] the
            // widget conceptually means) - treated the same way a single
            // blank string already is below (trimmed to nothing = no real
            // content), instead of falling through to the generic
            // !empty($value) check further down, which considers ANY
            // array with at least one element "not empty" regardless of
            // what that element actually contains.
            if (is_array($value)) {
                if (array_filter($value, fn($v) => is_string($v) ? trim($v) !== "" : (bool) $v) !== []) {
                    return false;
                }
                continue;
            }

            // Same fallthrough problem as the array case above: a
            // whitespace-only string ("   ") is blank content-wise, but
            // PHP's empty() only considers "" (zero-length) empty, so the
            // generic !empty($value) catch-all further down would still
            // flag it "not empty" if this didn't return/continue on its
            // own first.
            if (is_string($value)) {
                if (trim($value) !== "") {
                    return false;
                }
                continue;
            }

            if ($addConditions !== null && !call_user_func_array($addConditions, [$var, $value])) {
                return false;
            } elseif ($value === true) {
                return false;
            } elseif (!empty($value)) {
                return false;
            }
        }

        return true;
    }
}
