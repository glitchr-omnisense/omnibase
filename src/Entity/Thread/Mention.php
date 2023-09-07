<?php

namespace Base\Entity\Thread;

use App\Entity\User;
use App\Entity\Thread;

use Base\Repository\Thread\MentionRepository;
use Doctrine\ORM\Mapping as ORM;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Service\Model\IconizeInterface;
use Base\Database\Annotation\Cache;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass=MentionRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 * @DiscriminatorEntry( value = "abstract" )
 */
class Mention implements IconizeInterface
{
    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-quote-right"];
    }

    public function __construct(?User $mentionee = null, ?Thread $thread = null)
    {
        $this->mentioners = new ArrayCollection();
        $this->mentionee = $mentionee;

        $this->thread = $thread;
    }

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
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="mentions")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $mentionee;

    public function getMentionee(): ?User
    {
        return $this->mentionee;
    }

    public function setMentionee(?User $mentionee): self
    {
        $this->mentionee = $mentionee;

        return $this;
    }
    
    /**
     * @ORM\ManyToMany(targetEntity=User::class)
     */
    protected $mentioners;

    public function getMentioners(): Collection
    {
        return $this->mentioners;
    }

    public function addMentioner(User $mentioner): self
    {
        if (!$this->mentioners->contains($mentioner)) {
            $this->mentioners[] = $mentioner;
        }

        return $this;
    }

    public function removeMentioner(User $mentioner): self
    {
        $this->mentioners->removeElement($mentioner);

        return $this;
    }
    /**
     * @ORM\ManyToOne(targetEntity=Thread::class, inversedBy="mentions")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $thread;

    public function getThread(): ?Thread
    {
        return $this->thread;
    }

    public function setThread(?Thread $thread): self
    {
        $this->thread = $thread;

        return $this;
    }
}
