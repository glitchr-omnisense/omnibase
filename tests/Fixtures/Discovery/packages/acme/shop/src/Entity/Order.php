<?php

namespace Acme\Shop\Entity;

use Acme\Shop\Model\CustomerInterface;
use Base\Mapping as ORM;

/** A package's entity: its column of the package's enumeration, its association toward the package's interface. */
#[ORM\Entity]
#[ORM\Table(name: 'acme_order')]
class Order
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType (the database gives it)

    #[ORM\Column(type: 'acme_order_status')]
    private string $status = 'open';

    #[ORM\ManyToOne(targetEntity: CustomerInterface::class)]
    private ?CustomerInterface $customer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): static
    {
        $this->customer = $customer;

        return $this;
    }
}
