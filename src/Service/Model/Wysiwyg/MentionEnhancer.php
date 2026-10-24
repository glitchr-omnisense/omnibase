<?php

namespace Base\Service\Model\Wysiwyg;

use App\Entity\User;
use Base\Entity\Thread;
use Base\Database\Repository\Repositories;
use Base\Repository\Thread\Mentions;
use Base\Repository\Users;
use Base\Service\Model\LinkableInterface;
use Base\Service\ObfuscatorInterface;
use DOMDocument;

class MentionEnhancer implements MentionEnhancerInterface
{
    /** The mentions, the accounts and any linkable entity by their interfaces (step R28), where it asked the entity manager. */
    protected Mentions $mentionRepository;

    protected Users $userRepository;

    protected Repositories $repositories;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;

    public function __construct(Mentions $mentionRepository, Users $userRepository, Repositories $repositories, ObfuscatorInterface $obfuscator)
    {
        $this->mentionRepository = $mentionRepository;
        $this->userRepository = $userRepository;
        $this->repositories = $repositories;
        $this->obfuscator = $obfuscator;
    }

    public function mentions(): Mentions
    {
        return $this->mentionRepository;
    }

    /** @deprecated since omnibase 4.0 (step R28), the mentions by their interface: mentions() */
    public function getRepository(): Mentions
    {
        return $this->mentionRepository;
    }

    public function extractMentionees(string|array|null $strOrArray, array $attributes = []): array
    {
        if ($strOrArray === null) {
            return [];
        }

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        $mentionees = [];
        foreach($array as &$entry) {

            if(!$entry) continue;

            // mb_utf8(), not mb_detect_encoding($entry): unsure, the latter says ASCII for a text
            // that opens on enough of it (an older article's photos kept inline in base64, 2.6MB),
            // and the conversion from ASCII turned every other character into "?" on the page.
            $dom = new DOMDocument('1.0', 'UTF-8');
            // Charset hint is load-bearing: without it libxml decodes this UTF-8
            // fragment as ISO-8859-1 and saveHTML() re-encodes it, doubling every
            // accent ("e-acute" -> "A-tilde + copyright"). The DOMDocument encoding
            // argument only labels the output; it does not affect loadHTML() input.
            $dom->loadHTML('<?xml encoding="UTF-8" ?>' . mb_utf8($entry), LIBXML_NOERROR);

            $tags = $dom->getElementsByTagName("mention");
            if(count($tags) < 1) continue;

            foreach (iterator_to_array($tags) as $tag) {
        
                $value = first((array) json_decode($tag->getAttribute("data-json"))) ?? null;
                if($value) {
                    
                    $value = $this->obfuscator->decode($value, ObfuscatorInterface::NO_SHORT);
                    $id = $value["id"] ?? NULL;
                    $className = $value["className"] ?? Thread::class;
                    
                    if(is_instanceof($className, User::class) && !in_array($id, $mentionees)) {
                        $mentionees[] = $id;
                    }
                }
            }
        }

        return $mentionees ? $this->userRepository->byIds($mentionees) : [];        
    }

    public function enhance(string|array|null $strOrArray, array $attributes = []): string|array|null
    {
        if (!$strOrArray) {
            return $strOrArray;
        }

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        foreach($array as &$entry) {
    
            if(!$entry) continue;
            
            $encoding = mb_detect_encoding($entry);

            $dom = new DOMDocument('1.0', $encoding);
            // Charset hint is load-bearing: without it libxml decodes this UTF-8
            // fragment as ISO-8859-1 and saveHTML() re-encodes it, doubling every
            // accent ("e-acute" -> "A-tilde + copyright"). The DOMDocument encoding
            // argument only labels the output; it does not affect loadHTML() input.
            $dom->loadHTML('<?xml encoding="UTF-8" ?>' . mb_utf8($entry), LIBXML_NOERROR);

            $tags = $dom->getElementsByTagName("mention");
            if(count($tags) < 1) continue;

            foreach (iterator_to_array($tags) as $tag) {

                if(!str_contains($tag->getAttribute("class"), "ce-mention")) continue;

                $tagLink = $dom->createDocumentFragment();
                $tagLink = $dom->createElement("a", $tag->nodeValue);
                
                $value = first((array) json_decode($tag->getAttribute("data-json"))) ?? null;
                if($value) {
                    
                    $value = $this->obfuscator->decode($value, ObfuscatorInterface::NO_SHORT);

                    $id = $value["id"] ?? NULL;
                    $parameters = $value["parameters"] ?? [];
                    $className = $value["className"] ?? Thread::class;
                    
                    if(class_exists($className) && $id !== NULL) {

                        if(is_instanceof($className, LinkableInterface::class)) {

                            $entity = $this->repositories->oneById($className, $id);
                            if($entity) {
                                $tagLink->setAttribute("href", $entity->__toLink($parameters) ?? "");
                                $tagLink->nodeValue = $entity->__toString() ?? "";
                            }
                        }
                    }
                }
                
                $tagLink->setAttribute("class", $tag->getAttribute("class"));
                $tagLink->setAttribute("style", $tag->getAttribute("style"));
                $tagLink->setAttribute("target", "_blank");

                $tagLink->setAttribute("data-marker", $tag->getAttribute("data-marker"));
                $tagLink->setAttribute("data-before", $tag->getAttribute("data-before"));
                
                $tag->parentNode->replaceChild($tagLink, $tag);
            }

            $node = $dom->getElementsByTagName('body')->item(0);
            $entry = trim(implode(array_map([$node->ownerDocument, "saveHTML"], iterator_to_array($node->childNodes))));
        }

        return is_array($strOrArray) ? $array : first($array);
    }
}
