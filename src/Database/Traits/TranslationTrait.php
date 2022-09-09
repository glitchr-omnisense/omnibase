<?php

namespace Base\Database\Traits;

use Base\Database\Mapping\NamingStrategy;
use Base\Database\TranslatableInterface;
use Base\Service\LocaleProvider;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait TranslationTrait
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int { return $this->id;}

    /**
     * Translatable related methods
     */
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

    public function getTranslatable(): ?TranslatableInterface { return $this->translatable; }
    public function setTranslatable(?TranslatableInterface $translatable)
    {
        $this->translatable = $translatable;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=5)
     * @Assert\Locale(canonicalize = true)
     */
    protected $locale;

    public function getLocale(): ?string
    {
        if($this->locale) return LocaleProvider::normalize($this->locale);

        if($this->getTranslatable() === null)
            return null;

        return $this->getTranslatable()->getTranslations()->indexOf($this);
    }

    public function setLocale(string $locale)
    {
        $this->locale = LocaleProvider::normalize($locale);
        return $this;
    }

    public function isEmpty(array $addIgnoredVars = [], callable $addConditions = null): bool
    {
        $ignoredVars = array_unique(array_merge(['id', 'translatable', 'locale'], $addIgnoredVars));
        $ignoredVars = array_intersect(array_keys(get_object_vars($this)), $ignoredVars);

        foreach (get_object_vars($this) as $var => $value) {

            if (in_array($var, $ignoredVars, true))
                continue;
            if ($value === null)
                continue;

            if($addConditions !== null && call_user_func_array($addConditions, [$var, $value]))
                return false;
            if (is_string($value) && trim($value) !== "")
                return false;
            if (is_array($value) && $value !== [])
                return false;
        }

        return true;
    }
}