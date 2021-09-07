<?php

namespace Base\Entity;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;

use Base\Repository\ThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\GenerateUuid;
use Base\Database\Annotation\Timestamp;
use Base\Database\Annotation\Slugify;
use Base\Database\Annotation\EntityHierarchy;
use Base\Enum\ThreadState;
use Base\Traits\EntityHierarchyTrait;

/**
 * @ORM\Entity(repositoryClass=ThreadRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 * 
 * @EntityHierarchy(null, separator = "/" );
 */

class Thread
{
    use EntityHierarchyTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     *
     * @ORM\Column(type="string", unique=true)
     * @GenerateUuid(version=4)
     */
    protected $uuid;

    /**
     * @ORM\ManyToOne(targetEntity=Thread::class, inversedBy="children")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity=Thread::class, mappedBy="parent", cascade={"persist"}))
     * @ORM\OrderBy({"createdAt" = "ASC"})
     */
    protected $children;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="title")
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $excerpt;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $content;

    /**
     * @ORM\Column(type="thread_state", length=1)
     *
     * @AssertBase\NotBlank(groups={"new", "edit"})
     */
    protected $state;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, inversedBy="threads")
     *
     * @AssertBase\NotBlank(groups={"new", "edit"})
     */
    protected $authors;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, inversedBy="followedThreads")
     */
    protected $followers;

    /**
     * @ORM\ManyToMany(targetEntity=Tag::class, inversedBy="threads", cascade={"persist", "remove"})
     */
    protected $tags;

    /**
     * @ORM\OneToMany(targetEntity=Mention::class, mappedBy="thread", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $mentions;

    /**
     * @ORM\OneToMany(targetEntity=Like::class, mappedBy="thread", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $likes;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $publishedAt;

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on={"update", "create"})
     */
    protected $updatedAt;

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;

    public function __construct(?User $author = null, ?Thread $parent = null, ?string $title = null, ?string $slug = null)
    {
        $this->tags = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->mentions = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->authors = new ArrayCollection();

        $this->setParent($parent);
        $this->addAuthor($author);

        $this->title  = $title;
        $this->slug   = $slug;
        $this->state = ThreadState::DRAFT;
    }

    public function __toString()
    {
        return $this->getTitle() ?? get_class($this);
    }

    public static function whoAmI(): string
    {
        $array = explode('\\', get_called_class());
        return lcfirst(end($array));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state): self
    {
        $this->state = $state;
        if(in_array($this->state, [ThreadState::PUBLISHED, ThreadState::APPROVED]) && !$this->getPublishedAt())
            $this->setPublishedAt(new \DateTime("now"));

        return $this;
    }


    public function isDeleted(): bool { return $this->state == ThreadState::DELETED;   }
    public function isDraft()  : bool { return $this->state == ThreadState::DRAFT;     }
    public function isPublish(): bool { return $this->state == ThreadState::PUBLISHED; }
    public function isFuture() : bool { return $this->state == ThreadState::FUTURE;    }
    public function isSecret() : bool { return $this->state == ThreadState::SECRET;    }
    public function isPublishable(): bool
    {
        if(!$this->publishedAt) return false;
        return time() - $this->publishedAt->getTimestamp() >= 0;
    }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getPrimaryTag() { return $this->tags->first(); }

    /**
     * @return Collection|Tag[]
     */
    public function getSecondaryTags(): array { return $this->tags->slice(1); }

    /**
     * @return Collection|Tag[]
     */
    public function getTags(): Collection { return $this->tags; }
    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag))
            $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(?Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        if ($this->parent === null) return $this;

        $this->parent->addChild($this);
        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getChildren(): Collection { return $this->children; }
    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getParticipants(): Collection { return $this->participants; }
    public function addParticipant(User $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants[] = $participant;
        }

        return $this;
    }

    public function removeParticipant(User $participant): self
    {
        $this->participants->removeElement($participant);

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getFollowers(): Collection { return $this->followers; }
    public function addFollower(User $follower): self
    {
        if (!$this->followers->contains($follower)) {
            $this->followers[] = $follower;
        }

        return $this;
    }

    public function removeFollower(User $follower): self
    {
        $this->followers->removeElement($follower);

        return $this;
    }

    /**
     * @return Collection|Mention[]
     */
    public function getMentions(): Collection
    {
        return $this->mentions;
    }

    public function addMention(Mention $mention): self
    {
        if (!$this->mentions->contains($mention)) {
            $this->mentions[] = $mention;
            $mention->setThread($this);
        }

        return $this;
    }

    public function removeMention(Mention $mention): self
    {
        if ($this->mentions->removeElement($mention)) {
            // set the owning side to null (unless already changed)
            if ($mention->getThread() === $this) {
                $mention->setThread(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Like[]
     */
    public function isLiked(?User $user, ?string $icon = null): bool
    {
        if($user == null) return false;

        foreach($this->likes as $like) {

            if ($like->getUser() != $user) continue;
            if ($icon && $like->getIcon() != $icon) continue;
            return true;
        }

        return false;
    }

    public function getLikes(): Collection { return $this->likes; }
    public function addLike(Like $like): self
    {
        // Check if user already likes
        $user = $like->getUser();
        foreach($user->getLikes() as $_like) {

            if ($_like->getThread() != $this) continue;
            return $this;
        }

        // Attach new like !
        if (!$this->likes->contains($like)) {
            $this->likes[] = $like;
            $like->setThread($this);
        }
        return $this;
    }

    public function removeLike(?Like $like): self
    {
        $this->likes->removeElement($like);
        return $this;
    }

    /**
     * @return |User
     */
    public function getAuthor(): ?User { return $this->authors[0] ?? null; }
    /**
     * @return Collection|User[]
     */
    public function getAuthors(): Collection { return $this->authors; }
    public function addAuthor(?User $author): self
    {
        if(!$author) return $this;

        if (!$this->authors->contains($author)) {
            $this->authors[] = $author;
        }

        return $this;
    }

    public function removeAuthor(?User $author): self
    {
        $this->authors->removeElement($author);

        return $this;
    }

}