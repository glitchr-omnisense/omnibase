<?php

namespace Tests\Base\Service\Analytics;

use Base\Entity\Analytics\PageView;
use Base\Service\Analytics\UserAgentClassifier;
use PHPUnit\Framework\TestCase;

class UserAgentClassifierTest extends TestCase
{
    private UserAgentClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new UserAgentClassifier();
    }

    public function testRealBrowserUserAgentsClassifyAsHuman(): void
    {
        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $safariMobile = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

        $this->assertSame(PageView::SOURCE_HUMAN, $this->classifier->classify($chrome));
        $this->assertSame(PageView::SOURCE_HUMAN, $this->classifier->classify($safariMobile));
    }

    /**
     * @dataProvider aiUserAgentProvider
     */
    public function testKnownAiCrawlersClassifyAsAi(string $userAgent): void
    {
        $this->assertSame(PageView::SOURCE_AI, $this->classifier->classify($userAgent));
    }

    public static function aiUserAgentProvider(): array
    {
        return [
            'GPTBot' => ['Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)'],
            'ChatGPT-User' => ['Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)'],
            'ClaudeBot' => ['Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            'anthropic-ai' => ['anthropic-ai/1.0'],
            'CCBot' => ['CCBot/2.0 (https://commoncrawl.org/faq/)'],
            'PerplexityBot' => ['Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/bot)'],
            'Google-Extended' => ['Mozilla/5.0 (compatible; Google-Extended)'],
            'Bytespider' => ['Mozilla/5.0 (compatible; Bytespider; spider-feedback@bytedance.com)'],
        ];
    }

    /**
     * A generic (non-AI) crawler still gets caught by jaybizzle/crawler-
     * detect's own signature list - it just lands in SOURCE_BOT instead of
     * SOURCE_AI, since it isn't one of the AI-specific patterns above.
     */
    public function testGenericCrawlerClassifiesAsBotNotAi(): void
    {
        $googlebot = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

        $this->assertSame(PageView::SOURCE_BOT, $this->classifier->classify($googlebot));
    }

    public function testMissingOrEmptyUserAgentClassifiesAsBot(): void
    {
        $this->assertSame(PageView::SOURCE_BOT, $this->classifier->classify(null));
        $this->assertSame(PageView::SOURCE_BOT, $this->classifier->classify(''));
        $this->assertSame(PageView::SOURCE_BOT, $this->classifier->classify('   '));
    }

    /**
     * GPTBot's user-agent string also happens to satisfy generic crawler
     * heuristics (it self-identifies as "compatible; ...Bot") - the AI
     * pattern check must win, or every AI crawler would silently fall into
     * the generic SOURCE_BOT bucket and defeat the whole point of this class.
     */
    public function testAiPatternTakesPriorityOverGenericBotDetection(): void
    {
        $gptBot = 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)';

        $this->assertSame(PageView::SOURCE_AI, $this->classifier->classify($gptBot));
    }
}
