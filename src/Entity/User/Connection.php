<?php

namespace Base\Entity\User;

use Base\Annotations\Annotation\Timestamp;
use Base\Entity\User;
use Base\Enum\ConnectionState;
use Base\Repository\User\ConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConnectionRepository::class)
 */
class Connection
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="connections")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $ip;
    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $attempts;
    public function getAttempts(): ?string
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * @ORM\Column(type="connection_state")
     */
    protected $state;
    public function getState(): ?ConnectionState
    {
        return $this->state;
    }

    public function markAsSucceeded(): self
    {
        $this->state = ConnectionState::SUCCEEDED;
        return $this;
    }

    public function markAsFailed(): self
    {
        $this->state = ConnectionState::FAILED;
        return $this;
    }

    public function markAsLogout(): self
    {
        $this->state = ConnectionState::LOGOUT;
        return $this;
    }


    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $agent;
    public function getAgent(): ?string
    {
        return $this->agent;
    }

    public function setAgent(string $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $timezone;
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on={"create", "update"})
     */
    protected $updatedAt;
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
