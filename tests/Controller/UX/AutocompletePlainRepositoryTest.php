<?php

namespace Tests\Base\Controller\UX;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread\Taxon;
use Base\Entity\Thread\TaxonIntl;
use Base\Field\Type\SelectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A picker on an entity whose repository is a plain Doctrine one - a bundle's
 * DoctrineBundle ServiceEntityRepository, or the EntityRepository an entity
 * inheriting another's mapping gets when it names none (omnibase/classroom's
 * levels and subjects, Taxon's) - searches like any other: the autocompletion
 * asked that repository for cacheByInstanceOf(), which only omnibase's
 * answer, and every search answered 500. TaxonIntl stands for such an entity
 * here: core's, mapped, with no repository of its own.
 */
class AutocompletePlainRepositoryTest extends KernelTestCase
{
    private Session $session;

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $this->session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setLocale('en');
        $request->setSession($this->session);
        static::getContainer()->get('request_stack')->push($request);
    }

    public function testTheEntityStandsForAPlainRepository(): void
    {
        self::assertNotInstanceOf(ServiceEntityRepository::class, $this->em()->getRepository(TaxonIntl::class));
    }

    public function testItsPickerSearches(): void
    {
        $taxon = new Taxon('Searched '.bin2hex(random_bytes(3)));
        $this->em()->persist($taxon);
        $this->em()->flush();
        $translation = $taxon->getTranslations()->first() ?: null;

        try {
            $answer = $this->search(TaxonIntl::class, '');
            self::assertSame(200, $answer['status'], $answer['body']);
            $json = json_decode($answer['body'], true);
            self::assertIsArray($json['results'] ?? null, $answer['body']);
            if ($translation && $translation->getId()) {
                self::assertContains((string) $translation->getId(), array_map(fn ($r) => (string) ($r['id'] ?? ''), $json['results']), 'the row is among the answers');
            }
        } finally {
            $this->em()->remove($this->em()->find(Taxon::class, $taxon->getId()));
            $this->em()->flush();
        }
    }

    /** A repository of omnibase's own is used as it is. */
    public function testAnOmnibaseRepositoryIsKept(): void
    {
        $answer = $this->search(Taxon::class, '');
        self::assertSame(200, $answer['status'], $answer['body']);
    }

    /**
     * The address the select prints (its token in this session), asked as
     * select2 asks it.
     *
     * @return array{status: int, body: string}
     */
    private function search(string $class, string $term): array
    {
        $form = static::getContainer()->get('form.factory')->createNamedBuilder('record', FormType::class, null, ['csrf_protection' => false])
            ->add('picked', SelectType::class, ['class' => $class, 'multiple' => true, 'mapped' => false, 'required' => false])
            ->getForm();
        $select2 = json_decode($form->createView()['picked']->vars['select2'], true);
        $url = $select2['ajax']['url'] ?? null;
        self::assertNotNull($url, 'the picker searches through the autocompletion: '.json_encode($select2));

        $request = Request::create($url, 'POST', ['term' => $term, 'page' => '1']);
        $request->setSession($this->session);
        $response = static::$kernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);

        return ['status' => $response->getStatusCode(), 'body' => substr((string) $response->getContent(), 0, 2000)];
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }
}
