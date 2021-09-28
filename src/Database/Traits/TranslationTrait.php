<?php

namespace Base\Database\Traits;

use Base\Database\TranslatableInterface;

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

    /**
     * Translatable related methods
     */
    public static function getTranslatableEntityClass(): string
    {
        // By default, the translatable class has the same name but without the "Translation" suffix
        return substr(static::class, 0, -11);
    }
    
    /**
     * Will be mapped to translatable entity by TranslatableSubscriber
     *
     * @var TranslatableInterface
     */
    protected $translatable;

    public function getTranslatable(): TranslatableInterface { return $this->translatable; }
    public function setTranslatable(TranslatableInterface $translatable)
    {
        $this->translatable = $translatable;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=16)
     * @Assert\Locale(canonicalize = true)
     */
    protected $locale;

    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale)
    {
        $this->locale = $locale;
        return $this;
    }
    
    public function isEmpty(): bool
    {
        foreach (get_object_vars($this) as $var => $value) {

            if (in_array($var, ['id', 'translatable', 'locale'], true))
                continue;

            if (!empty($value)) return false;
        }

        return true;
    }

}