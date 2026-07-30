<?php

namespace Base\Service\Analytics;

use Base\Entity\Analytics\PageView;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Buckets a request's User-Agent into one of three Base\Entity\Analytics\
 * PageView::SOURCE_* values. AI checks run first and take priority over the
 * generic crawler-detect pass: several AI crawlers (GPTBot, ClaudeBot, ...)
 * also match generic bot heuristics, but they deserve their own bucket
 * (SOURCE_AI) rather than falling into the catch-all SOURCE_BOT one - that
 * split is the whole point of this class over just jaybizzle/crawler-detect's
 * plain isCrawler() boolean.
 *
 * A missing/empty User-Agent is classified as SOURCE_BOT rather than
 * SOURCE_HUMAN: real browsers always send one, so its absence is itself a
 * signal, and defaulting to "not human" keeps the human bucket honest
 * instead of silently inflating it with unidentified traffic.
 */
final class UserAgentClassifier
{
    /**
     * AI/LLM crawlers and assistant fetchers - not exhaustive (this space
     * moves fast), but covers the major labs' documented bot user-agent
     * tokens as of 2026. Matched as a case-insensitive substring, same as
     * jaybizzle/crawler-detect's own approach.
     */
    private const AI_PATTERNS = [
        'GPTBot', 'ChatGPT-User', 'OAI-SearchBot',
        'ClaudeBot', 'Claude-Web', 'Claude-SearchBot', 'anthropic-ai',
        'CCBot',
        'PerplexityBot', 'Perplexity-User',
        'Google-Extended',
        'Bytespider',
        'Amazonbot',
        'Applebot-Extended',
        'meta-externalagent', 'meta-webindexer', 'FacebookBot',
        'cohere-ai', 'cohere-training-data-crawler',
        'Diffbot',
        'YouBot',
        'Timpibot',
        'ImagesiftBot',
        'Omgilibot', 'Omgili', 'webzio-extended',
        'FriendlyCrawler',
        'DuckAssistBot',
        'MistralAI-User',
    ];

    public function __construct(private readonly CrawlerDetect $crawlerDetect = new CrawlerDetect())
    {
    }

    public function classify(?string $userAgent): string
    {
        $userAgent = trim((string) $userAgent);
        if ($userAgent === '') {
            return PageView::SOURCE_BOT;
        }

        foreach (self::AI_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return PageView::SOURCE_AI;
            }
        }

        return $this->crawlerDetect->isCrawler($userAgent) ? PageView::SOURCE_BOT : PageView::SOURCE_HUMAN;
    }
}
