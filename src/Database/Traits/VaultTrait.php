<?php

namespace Base\Database\Traits;

use Base\Entity\Layout\SettingIntl;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 *
 */
trait VaultTrait
{
    use BaseTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $vault = null;

    public function getVault(): ?string
    {
        return $this->vault;
    }

    public function setVault(?string $vault): self
    {
        $this->vault = $vault;
        return $this;
    }

    protected array $vaultBag = [];

    public function getPlainVaultBag(mixed $key): mixed
    {
        return $this->vaultBag[$key][1] ?? null;
    }

    public function getSealedVaultBag(string $key): ?string
    {
        return $this->vaultBag[$key][0] ?? null;
    }

    public function setVaultBag(?string $key, string $sealedValue, mixed $plainValue): self
    {
        $this->vaultBag[$key] = [$sealedValue, $plainValue];

        return $this;
    }

    public function isSecured(): bool
    {
        return null !== $this->vault;
    }

    public function getSecure(): bool
    {
        return $this->isSecured();
    }

    /**
     * @param bool $secure
     * @return SettingIntl
     */
    public function setSecure(bool $secure)
    {
        return $this->setVault($secure === true ? $this->getEnvironment() : null);
    }
}
