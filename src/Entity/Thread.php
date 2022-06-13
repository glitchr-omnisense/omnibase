<?php

namespace Base\Entity;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;
use App\Entity\Thread\Taxon;

use Base\Database\Annotation\OrderColumn;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Base\Validator\Constraints as AssertBase;
use Symfony\Component\Validator\Constraints as Assert;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Timestamp;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Hierarchify;
use Base\Database\Annotation\Trasheable;
use Base\Enum\ThreadState;

use Base\Traits\BaseTrait;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Database\Traits\TrasheableTrait;
use Base\Model\IconizeInterface;
use Base\Model\GraphInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\ThreadRepository;

/**
 * @ORM\Entity(repositoryClass=ThreadRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "common" )
 *
 * @AssertBase\UniqueEntity(fields={"slug"}, groups={"new", "edit"})
 * @Hierarchify(null, separator = "/" )
 * @Trasheable
 */
class Thread implements TranslatableInterface, IconizeInterface, GraphInterface
{
    use BaseTrait;
    use TrasheableTrait;
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->getPrimaryTag() && $this->getPrimaryTag()->getIcon() ? [$this->getPrimaryTag()->getIcon()] : null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-box"]; }

    public function __toString() { return $this->getTitle() ?? $this->getSlug() ?? $this->getTranslator()->entity(self::class); }
    public function __construct(?User $owner = null, ?Thread $parent = null, ?string $title = null, ?string $slug = null)
    {
        $this->tags      = new ArrayCollection();
        $this->children  = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->mentions  = new ArrayCollection();
        $this->likes     = new ArrayCollection();
        $this->owners    = new ArrayCollection();
        $this->connexes  = new ArrayCollection();
        $this->taxa      = new ArrayCollection();

        $this->setParent($parent);
        $this->addOwner($owner);
        $this->setTitle($title);

        $this->setState(ThreadState::DRAFT);

        $this->slug = $slug;

    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     *
     * @ORM\Column(type="string", unique=true)
     * @GenerateUuid(version=4)
     */
    protected $uuid;
    public function getUuid() { return $this->uuid; }

    /**
     * @ORM\ManyToOne(targetEntity=Thread::class, inversedBy="children")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $parent;
    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        if ($this->parent === null) return $this;

        $this->parent->addChild($this);
        return $this;
    }

    /**
     * @ORM\OneToMany(targetEntity=Thread::class, mappedBy="parent", cascade={"persist"}))
     */
    protected $children;
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
     * @ORM\ManyToMany(targetEntity=Thread::class)
     */
    protected $connexes;
    public function getConnexes(): Collection { return $this->connexes; }
    public function addConnex($connex): self
    {
        if(!$this->connexes->contains($connex))
            $this->connexes[] = $connex;

        return $this;
    }

    public function removeConnex($connex): self
    {
        $this->connexes->removeElement($connex);
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="title")
     */
    protected $slug;
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @ORM\Column(type="thread_state")
     * @AssertBase\NotBlank(groups={"new", "edit"})
     */
    protected $state;
    public function getState() { return $this->state; }
    public function setState($state): self
    {
        $this->state = $state;
        if ($this->isPublished() && !$this->getPublishedAt())
            $this->setPublishedAt(new \DateTime("now"));

        return $this;
    }

    public function setIsPublished (bool $newState): self
    {
        $this->state = $newState ? ThreadState::PUBLISH : ThreadState::DRAFT;
        return $this;
    }

    public function isScheduled(): bool { return $this->publishedAt && !$this->isPublished(); }
    public function isPublished(): bool { return str_starts_with($this->state, ThreadState::PUBLISH); }
    public function isPublishable(): bool
    {
        if($this->state == ThreadState::FUTURE && !$this->publishedAt) return false;
        return time() - $this->publishedAt->getTimestamp() >= 0;
    }

    /**
     * @ORM\ManyToMany(targetEntity=User::class, inversedBy="threads")
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @OrderColumn
     */
    protected $owners;
    public function getOwner(): ?User { return $this->owners[0] ?? null; }
    public function getOwners(): Collection { return $this->owners; }
    public function addOwner(?User $owner): self
    {
        if(!$owner) return $this;

        if (!$this->owners->contains($owner)) {
            $this->owners[] = $owner;
        }

        return $this;
    }

    public function removeOwner(?User $owner): self
    {
        $this->owners->removeElement($owner);

        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=User::class, inversedBy="followedThreads")
     */
    protected $followers;
    public function getFollowers(): Collection { return $this->followers; }
    public function addFollower(User $follower): self
    {
        if(!$this->followers->contains($follower)) {
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
     * @ORM\ManyToMany(targetEntity=Tag::class, inversedBy="threads", cascade={"persist"})
     * @OrderColumn
     */
    protected $tags;
    public function getPrimaryTag() { $first = $this->tags->first(); return ($first ? $first : null); }
    public function getSecondaryTags(): array { return $this->tags->slice(1); }
    public function getTags(): Collection { return $this->tags; }
    public function addTag(Tag $tag): self
    {
        if(!$this->tags->contains($tag))
            $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(?Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Taxon::class, inversedBy="threads", cascade={"persist"})
     * @OrderColumn
     */
    protected $taxa;
    public function getTaxa(): Collection { return $this->taxa; }
    public function getTaxonomy(): Collection { return $this->getTaxa(); }
    public function addTaxon($taxon): self
    {
        if(!$this->taxa->contains($taxon))
            $this->taxa[] = $taxon;

        return $this;
    }

    public function removeTaxon($taxon): self
    {
        $this->taxa->removeElement($taxon);
        return $this;
    }

    /**
     * @ORM\OneToMany(targetEntity=Mention::class, mappedBy="thread", orphanRemoval=true, cascade={"persist"})
     */
    protected $mentions;
    public function getMentions(): Collection { return $this->mentions; }
    public function addMention(Mention $mention): self
    {
        if(!$this->mentions->contains($mention)) {
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
     * @ORM\OneToMany(targetEntity=Like::class, mappedBy="thread", orphanRemoval=true, cascade={"persist"})
     */
    protected $likes;
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
        if(!$this->likes->contains($like)) {
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

    public function isLiked(?User $user, ?string $icon = null /* Icon type: thumbs up, thumbs down,.. */): bool
    {
        if($user == null) return false;

        foreach($this->likes as $like) {

            if ($like->getUser() != $user) continue;
            if ($icon && $like->getIcon() != $icon) continue;
            return true;
        }

        return false;
    }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on={"update", "create"})
     */
    protected $updatedAt;
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $publishedAt;
    public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getKeywords(?string $locale = null, int $depth = 0): array
    {
        $keywords = $this->translate($locale)->getKeywords();
        if($depth > 0) $keywords ??= ($this->getParent() ? $this->getParent()->getKeywords($locale, --$depth) : null);

        return $keywords;
    }

    public function setKeywords(?string $keywords, ?string $locale = null, int $depth = 0)
    {
        if($depth > 0)
            return $this->translate($locale)->setKeywords(empty($keywords) || $keywords === $this->getParent()->getKeywords($locale, --$depth) ? null : $keywords);

        return $this->translate($locale)->setKeywords($keywords);
    }

    public function getHeadline(?string $locale = null, int $depth = 0): ?string
    {
        $headline = $this->translate($locale)->getHeadline();
        if($depth > 0) $headline ??= ($this->getParent() ? $this->getParent()->getHeadline($locale, --$depth) : null);

        return $headline;
    }

    public function setHeadline(?string $headline, ?string $locale = null, int $depth = 0)
    {
        if($depth > 0)
            return $this->translate($locale)->setHeadline(empty($headline) || $headline === $this->getParent()->getHeadline($locale, --$depth) ? null : $headline);

        return $this->translate($locale)->setHeadline($headline);
    }

    public function getTitle(?string $locale = null, int $depth = 0): ?string
    {
        $title = $this->translate($locale)->getTitle();
        if($depth > 0) $title ??= ($this->getParent() ? $this->getParent()->getTitle($locale, --$depth) : null);

        return $title;
    }

    public function setTitle(?string $title, ?string $locale = null, int $depth = 0)
    {
        if($depth > 0)
            return $this->translate($locale)->setTitle(empty($title) || $title === $this->getParent()->getTitle($locale, --$depth) ? null : $title);

        return $this->translate($locale)->setTitle($title);
    }

    public function getExcerpt(?string $locale = null, int $depth = 0): ?string
    {
        $excerpt = $this->translate($locale)->getExcerpt();
        if($depth > 0) $excerpt ??= ($this->getParent() ? $this->getParent()->getExcerpt($locale, --$depth) : null);

        return $excerpt;
    }

    public function setExcerpt(?string $excerpt, ?string $locale = null, int $depth = 0)
    {
        if($depth > 0)
            return $this->translate($locale)->setExcerpt(empty($excerpt) || $excerpt === $this->getParent()->getExcerpt($locale, --$depth) ? null : $excerpt);

        return $this->translate($locale)->setExcerpt($excerpt);
    }

    public function getContent(?string $locale = null, int $depth = 0): ?string
    {
        $content = $this->translate($locale)->getContent();
        if($depth > 0) $content ??= ($this->getParent() ? $this->getParent()->getContent($locale, --$depth) : null);

        return $content;
    }

    public function setContent(?string $content, ?string $locale = null, int $depth = 0)
    {
        if($depth > 0)
            return $this->translate($locale)->setContent(empty($content) || $content === $this->getParent()->getContent($locale, --$depth) ? null : $content);

        return $this->translate($locale)->setContent($content);
    }

    /**
     * Add article content with reshaped titles
     */
    public const MAX_ANCHOR = 6;
    public function getContentWithAnchors(array $options = [], $suffix = "", $max = self::MAX_ANCHOR): ?string
    {
        $max = min($max, self::MAX_ANCHOR);
        return preg_replace_callback("/\<(h[1-".$max."])\>([^\<\>]*)\<\/h[1-".$max."]\>/", function ($match) use ($suffix, $options) {

            $tag = $match[1];
            $content = $match[2];
            $slug = strtolower($this->getSlugger()->slug($content));

            $options["attr"]["class"] = $options["attr"]["class"] ?? "";
            $options["attr"]["class"] = trim($options["attr"]["class"] . " anchor");

            return "<".$tag." ".html_attributes($options["row_attr"] ?? [], ["id" => $slug])."><a ".html_attributes($options["attr"] ?? [])." href='#" . $slug . "'>".$content."</a><a href='#" . $slug . "'>".$suffix."</a></".$tag.">";

        }, $this->content);
    }

    /**
     * Compute table of content
     */
    public function getTableOfContent($max = 6): array
    {
        $headlines = [];
        $max = min($max, 6);

        preg_replace_callback("/\<(h[1-".$max."])\>([^\<\>]*)\<\/h[1-".$max."]\>/", function ($match) use (&$headlines) {

            $headlines[] = [
                "tag" => $match[1],
                "slug"  => $this->getSlugger()->slug($match[2]),
                "title" => $match[2]
            ];

        }, $this->getContent());

        return $headlines;
    }

}