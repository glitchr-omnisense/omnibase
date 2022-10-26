<?php

namespace Base\Database\Traits;

use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;

trait VaultTrait
{
    use BaseTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $vault = null;
    public function isSecured():bool   { return null !== $this->vault; }
    public function getSecure(): bool { return $this->isSecured(); }
    public function setSecure(bool $secure) { return $this->setVault($secure === true ? $this->getEnvironment() : null); }

    public function getVault(): ?string { return $this->vault; }
    public function setVault(?string $vault): self
    {
        $this->vault = $vault;
        return $this;
    }
}
