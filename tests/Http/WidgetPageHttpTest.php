<?php

namespace Tests\Base\Http;

use Base\Entity\Layout\Widget\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * /page/{slug} (widget_page) and /attachment/{slug} (widget_attachment) for
 * an address that names nothing: not found, 404, the site's error page. They
 * threw php-http's Discovery NotFoundException, no HTTP exception: every
 * unknown page answered 500 (a site's link to /page/gdpr, a page it never
 * made, did). A page that exists still opens.
 */
class WidgetPageHttpTest extends KernelTestCase
{
    use HttpTestTrait;

    protected function setUp(): void
    {
        $this->bootHost();
    }

    public function testAPageThatDoesNotExistIsNotFound(): void
    {
        $response = $this->request('/page/no-such-page-'.bin2hex(random_bytes(3)));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testAnAttachmentThatDoesNotExistIsNotFound(): void
    {
        $response = $this->request('/attachment/no-such-attachment-'.bin2hex(random_bytes(3)));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testAPageThatExistsOpens(): void
    {
        $slug = 'widget-page-'.bin2hex(random_bytes(3));
        $em = $this->entityManager();
        $em->persist(new Page('A page of the site', $slug));
        $em->flush();

        try {
            $response = $this->request('/page/'.$slug);
            $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 600));
            $this->assertStringContainsString('widget-page', (string) $response->getContent());
        } finally {
            $em->clear();
            foreach ($em->getRepository(Page::class)->findBy(['slug' => $slug]) as $page) {
                $em->remove($page);
            }
            $em->flush();
        }
    }
}
