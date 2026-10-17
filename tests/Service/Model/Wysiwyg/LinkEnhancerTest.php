<?php

namespace Tests\Base\Service\Model\Wysiwyg;

use Base\Service\Model\Wysiwyg\LinkEnhancer;
use PHPUnit\Framework\TestCase;

class LinkEnhancerTest extends TestCase
{
    /**
     * An older article kept by Quill as HTML, its photos inline in base64 (2.6MB
     * on lesringostars): mb_detect_encoding() said ASCII for such a text, and the
     * conversion from ASCII printed every accent and curly quote as "?".
     */
    public function testAnArticleOpeningOnALongInlinePhotoKeepsItsAccents(): void
    {
        $html = '<p><img src="data:image/jpeg;base64,'.str_repeat('QUJD', 50000).'"></p>'
            .'<p>Si vous mettez votre main à couper d’avoir lu « 13 juillet 2022 », lisez <a href="https://example.org/article">la suite</a>.</p>';
        self::assertSame('ASCII', mb_detect_encoding($html), 'the case: a loose detection is wrong here');

        $enhanced = (new LinkEnhancer())->enhance($html);

        self::assertStringContainsString('main à couper d’avoir lu « 13 juillet 2022 »', $enhanced);
        self::assertStringNotContainsString('??', $enhanced);
        self::assertStringContainsString('<a href="https://example.org/article">la suite</a>', $enhanced, 'another site\'s link stays whole');
    }

    public function testAShortTextKeepsItsAccents(): void
    {
        self::assertSame('<p>Été à <a href="https://example.org/">Kyōto</a></p>', (new LinkEnhancer())->enhance('<p>Été à <a href="https://example.org/">Kyōto</a></p>'));
    }
}
