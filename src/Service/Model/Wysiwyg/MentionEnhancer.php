<?php

namespace Base\Service\Model\Wysiwyg;

use App\Entity\User;
use Base\Entity\Thread;
use Base\Repository\Thread\MentionRepository;
use Base\Repository\UserRepository;
use Base\Service\Model\LinkableInterface;
use Base\Service\ObfuscatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;

/**
 *
 */
class MentionEnhancer implements MentionEnhancerInterface
{
    /**
     * @var MentionRepository
     */
    protected MentionRepository $mentionRepository;

    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;

    public function __construct(MentionRepository $mentionRepository, UserRepository $userRepository, EntityManagerInterface $entityManager, ObfuscatorInterface $obfuscator)
    {
        $this->mentionRepository = $mentionRepository;
        $this->userRepository = $userRepository;
        
        $this->entityManager = $entityManager; 
        $this->obfuscator = $obfuscator;
    }

    public function getRepository(): MentionRepository
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

            $encoding = mb_detect_encoding($entry);
            $dom = new DOMDocument('1.0', $encoding);
            $dom->loadHTML(mb_convert_encoding($entry, 'HTML-ENTITIES', $encoding), LIBXML_NOERROR);

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

        return $mentionees ? $this->userRepository->cacheById($mentionees)->getResult() : [];        
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
            $dom->loadHTML(mb_convert_encoding($entry, 'HTML-ENTITIES', $encoding), LIBXML_NOERROR);

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

                        $entityRepository = $this->entityManager->getRepository($className);
                        if($entityRepository && is_instanceof($className, LinkableInterface::class)) {

                            $entity = $entityRepository->cacheOneById($id);
                            if($entity) {
                                $tagLink->setAttribute("href", $entity->__toLink($parameters) ?? "");
                                $tagLink->nodeValue = $entity?->__toString() ?? "";
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
