<?php

namespace Base\Entity\Thread;

use App\Entity\User;
use App\Entity\Thread;

use App\Repository\Thread\LikeRepository;

use Doctrine\ORM\Mapping as ORM;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Service\Model\IconizeInterface;

/**
 * @ORM\Entity(repositoryClass=LikeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */
class Like implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-thumbs-up"]; }

    public function __construct(?User $user = null)
    {
        $this->user = $user;
        $this->icon = "fas fa-thumbs-up";
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="likes")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $user;
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=Thread::class, inversedBy="likes")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $thread;

    public function getThread(): ?Thread { return $this->thread; }
    public function setThread(?Thread $thread): self
    {
        $this->thread = $thread;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $icon;
    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }
}
