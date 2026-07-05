<?php

namespace App\Controller;

use Base\Service\ObfuscatorInterface;
use Base\Service\SettingBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A deliberately flat, style-free tour of a few base-bundle features.
 * Every section runs in isolation so a failing feature reports its error
 * instead of breaking the whole page.
 */
class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_index')]
    public function index(
        SettingBagInterface $settings,
        ObfuscatorInterface $obfuscator,
        TranslatorInterface $translator,
        EntityManagerInterface $em,
    ): Response {
        $sections = [];
        $demo = function (string $title, string $about, callable $fn) use (&$sections) {
            try {
                $sections[] = ['title' => $title, 'about' => $about, 'ok' => true, 'output' => $fn()];
            } catch (\Throwable $e) {
                $sections[] = ['title' => $title, 'about' => $about, 'ok' => false, 'output' => $e::class . ': ' . $e->getMessage()];
            }
        };

        $demo(
            'App\ ↔ Base\ mirroring',
            'The bundle mirrors the App\ namespace: any App\ class you do not define yourself is aliased to its Base\ twin at boot. This demo app defines none of these — they all resolve to the bundle.',
            function () {
                $out = [];
                foreach (['App\Entity\User', 'App\Entity\Thread', 'App\Repository\UserRepository', 'App\Notifier\Notifier'] as $class) {
                    $out[] = sprintf('%s → %s', $class, (new \ReflectionClass($class))->getName());
                }
                return implode("\n", $out);
            }
        );

        $demo(
            'Doctrine entity set',
            'The bundle ships a full entity layer (users, threads, layout, settings, …) mapped through its own naming strategy and metadata factory — this demo created the whole schema in SQLite.',
            function () use ($em) {
                $all = $em->getMetadataFactory()->getAllMetadata();
                $names = array_map(fn ($m) => $m->getName(), $all);
                sort($names);
                return count($names) . " mapped entities, e.g.:\n" . implode("\n", array_slice($names, 0, 10)) . "\n…";
            }
        );

        $demo(
            'Setting bag',
            'Database-backed named settings, readable/writable like a parameter bag (persisted in the demo SQLite database).',
            function () use ($settings) {
                $count = 1 + (int) ($settings->getScalar('demo.counter') ?? 0);
                $settings->set('demo.counter', (string) $count);
                return "demo.counter = " . var_export($settings->getScalar('demo.counter'), true) . " (increments on each page load)";
            }
        );

        $demo(
            'Obfuscator (hashids)',
            'Reversible compression/obfuscation of structured data into URL-safe strings, e.g. for stateless links.',
            function () use ($obfuscator) {
                $payload = ['page' => 42, 'filter' => 'recent'];
                $hash = $obfuscator->encode($payload);
                $back = $obfuscator->decode($hash);
                return sprintf("encode(%s)\n  → %s\ndecode(...)\n  → %s", json_encode($payload), $hash, json_encode($back));
            }
        );

        $demo(
            'Translator',
            'A recursive, quiet-capable translator built on top of Symfony\'s — unknown keys can resolve to null instead of throwing.',
            function () use ($translator) {
                return sprintf(
                    "transQuiet('demo.missing_key') → %s\ntrans('demo.missing_key')      → %s",
                    var_export($translator->transQuiet('demo.missing_key'), true),
                    $translator->trans('demo.missing_key')
                );
            }
        );

        return $this->render('demo/index.html.twig', ['sections' => $sections]);
    }
}
