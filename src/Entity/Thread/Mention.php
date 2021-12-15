<?php

namespace Base\Entity\Thread;

use App\Entity\User;
use App\Entity\Thread;

use Base\Repository\MentionRepository;
use Doctrine\ORM\Mapping as ORM;
use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;

/**
 * @ORM\Entity(repositoryClass=MentionRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */
class Mention implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-quote-right"]; }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="mentions")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $target;
    public function getTarget(): ?User { return $this->target; }
    public function setTarget(?User $target): self
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    protected $author;
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }


    /**
     * @ORM\ManyToOne(targetEntity=Thread::class, inversedBy="mentions")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $thread;
    public function getThread(): ?Thread { return $this->thread; }
    public function setThread(?Thread $thread): self
    {
        $this->thread = $thread;

        return $this;
    }
}
