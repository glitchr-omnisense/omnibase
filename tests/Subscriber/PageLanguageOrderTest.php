<?php

namespace Tests\Base\Subscriber;

use Base\Subscriber\LocalizerSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * The order a page's language is decided in (LocalizerSubscriber::choose()):
 * a signed-in member's own language comes before the one their last page
 * was shown in. A member whose language is French, who arrived on the
 * sign-in page with an English browser, was kept in English everywhere
 * after signing in: the first page set LANG to English, and LANG was read
 * before the member's language.
 */
class PageLanguageOrderTest extends TestCase
{
    public function testTheAddressDecidesFirst(): void
    {
        self::assertSame('de-DE', LocalizerSubscriber::choose('de-DE', 'en-GB', 'fr-FR', false, 'en-GB', 'fr-FR'));
    }

    public function testASignedInMembersLanguageBeforeTheRememberedOne(): void
    {
        self::assertSame('fr-FR', LocalizerSubscriber::choose(null, 'en-GB', 'fr-FR', false, 'en-GB', 'en-GB'), 'LANG set by the first visit, in the browser\'s language, no longer wins');
        self::assertSame('en-GB', LocalizerSubscriber::choose(null, 'en-GB', null, false, 'fr-FR', 'fr-FR'), 'a visitor: their last page\'s');
    }

    public function testWhileImpersonatingTheImpersonatorsChoice(): void
    {
        self::assertSame('en-GB', LocalizerSubscriber::choose(null, 'en-GB', 'fr-FR', true, null, 'fr-FR'));
        self::assertSame('fr-FR', LocalizerSubscriber::choose(null, null, 'fr-FR', true, null, 'en-GB'), 'no choice made: the member\'s');
    }

    public function testThenTheBrowserThenTheSite(): void
    {
        self::assertSame('en-GB', LocalizerSubscriber::choose(null, null, null, false, 'en-GB', 'fr-FR'));
        self::assertSame('fr-FR', LocalizerSubscriber::choose(null, null, null, false, null, 'fr-FR'));
    }
}
