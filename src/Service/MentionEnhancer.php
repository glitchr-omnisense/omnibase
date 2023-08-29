<?php

namespace Base\Service;

use App\Entity\User;
use Base\Entity\Thread\Mention;
use Base\Repository\Thread\MentionRepository as MentionRepository;
use Base\Service\Model\LinkableInterface;
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
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;

    public function __construct(MentionRepository $mentionRepository, EntityManagerInterface $entityManager, ObfuscatorInterface $obfuscator)
    {
        $this->mentionRepository = $mentionRepository;
        $this->entityManager = $entityManager; 
        $this->obfuscator = $obfuscator;
    }

    public function highlight(string|array|null $strOrArray, array $attributes = []): string|array|null
    {
        if ($strOrArray === null) {
            return null;
        }

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        foreach($array as &$entry) {
    
            $encoding = mb_detect_encoding($entry);

            $dom = new DOMDocument('1.0', $encoding);
            $dom->loadHTML(mb_convert_encoding($entry, 'HTML-ENTITIES', $encoding), LIBXML_NOERROR);

            $tags = $dom->getElementsByTagName("mention");
            foreach (iterator_to_array($tags) as $tag) {

                $tagLink = $dom->createDocumentFragment();
                $tagLink = $dom->createElement("a", $tag->nodeValue);
                
                $value = first((array) json_decode($tag->getAttribute("data-json"))) ?? null;
                if($value) {
                    
                    $value = $this->obfuscator->decode($value);
                    $id = $value["id"] ?? NULL;
                    $parameters = $value["parameters"] ?? [];
                    $className = $value["className"] ?? NULL;

                    if(class_exists($className) && $id !== NULL) {

                        $entityRepository = $this->entityManager->getRepository($className);
                        if($entityRepository && is_instanceof($className, LinkableInterface::class)) {

                            $entity = $entityRepository->cacheOneById($id);
                            $tagLink->setAttribute("href", $entity?->__toLink($parameters) ?? "");
                            // dump($entity, $threadId);
                            // if ($threadId != null && $entity instanceof User) {
                                
                            //     dump($threadId);
                            //     $mention = $this->mentionRepository->cacheOneByUserAndThread($entity, $threadId);
                            // }
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
