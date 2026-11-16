<?php

namespace Tests\Base\Field;

use Base\Field\Type\SelectType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * What select2 is told to select (`selected` in data-select2-options), for a select of records: their ids,
 * the values of their entries, which form-type-select2.js hands $(field).val(). SelectType printed the
 * records themselves, each JSON-encoded as an empty object ([{}]), and a record's picker started with
 * nothing selected in the browser (plan "omnibase agnostic", step F5d).
 */
final class SelectTypeSelectedTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    /** @return list<object> */
    private function users(int $count): array
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $users = [];
        for ($i = 0; $i < $count; ++$i) {
            $user = new \App\Entity\User();
            $name = 'selected'.$i.bin2hex(random_bytes(4));
            if (PropertyAccess::createPropertyAccessor()->isWritable($user, 'username')) {
                PropertyAccess::createPropertyAccessor()->setValue($user, 'username', $name);
            }
            $user->setEmail($name.'@example.org');
            $user->setPlainPassword('test-'.$name);
            $em->persist($user);
            $users[] = $user;
        }
        $em->flush();

        return $users;
    }

    /** @return array<string, mixed> select2's settings as the page prints them */
    private function select2(mixed $data, bool $multiple): array
    {
        $form = static::getContainer()->get('form.factory')->createNamed('pick', SelectType::class, $data, ['class' => \App\Entity\User::class, 'multiple' => $multiple]);

        return json_decode((string) $form->createView()->vars['select2'], true);
    }

    public function testTheRecordsChosenAreSelectedByTheirIds(): void
    {
        [$first, $second] = $this->users(2);

        $select2 = $this->select2([$first, $second], true);
        self::assertSame([(string) $first->getId(), (string) $second->getId()], $select2['selected']);

        $select2 = $this->select2($first, false);
        self::assertSame([(string) $first->getId()], $select2['selected']);
        $marked = array_values(array_map(static fn (array $entry): string => (string) $entry['id'], array_filter($select2['data'], static fn (array $entry): bool => !empty($entry['selected']))));
        self::assertSame($marked, $select2['selected'], 'the ids of the entries it marks selected');
    }

    public function testNothingChosenIsNothingSelected(): void
    {
        self::assertSame([], $this->select2(null, true)['selected']);
    }
}
