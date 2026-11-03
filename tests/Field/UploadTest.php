<?php

namespace Tests\Base\Field;

use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\Upload;
use Base\Field\Kind;
use Base\Field\Upload\Dropzone;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The uploads on every host (plan "omnibase agnostic", step F5d, slice 4): Base\Field\Behaviour\Upload, what
 * FileType and its children decided and now ask - their options, what a form sends back read as FileType reads
 * it -, and Base\Field\Upload\Dropzone, which DropzoneController's routes now delegate to and omnibridge/laravel's
 * UploadController calls the same way: a file kept under a uuid with the visitor's token, shown again, removed,
 * refused with another token; the uuids a form sends back read as the files kept.
 */
final class UploadTest extends KernelTestCase
{
    public function testEachUploadKindHasItsTypesDefaults(): void
    {
        foreach ([Kind::FILE => 'file', Kind::IMAGE => 'image', Kind::AVATAR => 'avatar', Kind::AUDIO => 'file', Kind::VIDEO => 'file'] as $kind => $controller) {
            $behaviour = Behaviours::of($kind);
            self::assertInstanceOf(Upload::class, $behaviour, $kind);
            self::assertSame($controller, $behaviour->assets([])->controller, $kind.': the data attributes its script reads');
            self::assertSame('form.dropzone', $behaviour->assets($behaviour->options([]))->entry);
        }
        self::assertSame(['audio/*'], Behaviours::of(Kind::AUDIO)->options([])['mime_types']);
        self::assertTrue(Behaviours::of(Kind::AUDIO)->options([])['multiple'], 'a sound of several sources (AudioType\'s multisource)');
        self::assertTrue(Behaviours::of(Kind::IMAGE)->options([])['clipboard'], 'a single picture copied (ImageType)');
        self::assertFalse(Behaviours::of(Kind::AUDIO)->options([])['clipboard']);
        self::assertSame(['aspectRatio' => 1], Behaviours::of(Kind::AVATAR)->options(['cropper' => []])['cropper'], 'an avatar cropped square');
        self::assertSame('App\\Entity\\Thing', Behaviours::of(Kind::FILE)->options(['data_class' => 'App\\Entity\\Thing'])['class']);
    }

    public function testWhatTheFormSendsIsReadAsFileTypeReadsIt(): void
    {
        $upload = Behaviours::of(Kind::IMAGE);
        $options = $upload->options([]);
        $path = tempnam(sys_get_temp_dir(), 'upload');
        $sent = new UploadedFile($path, 'ringo.png', 'image/png', null, true);

        self::assertSame($sent, $upload->reverse(['file' => '5d469c63-ce06-4f45-b555-728878b371d6', 'raw' => $sent], $options), 'the file sent first');
        self::assertSame('5d469c63-ce06-4f45-b555-728878b371d6', $upload->reverse(['file' => '5d469c63-ce06-4f45-b555-728878b371d6', 'raw' => null], $options), 'else the stored file\'s name, kept');
        self::assertNull($upload->reverse(['file' => '', 'raw' => null], $options), 'nothing: the file removed');
        self::assertSame($sent, $upload->reverse($sent, $options), 'a plain file input, as it is');
        unlink($path);

        // The binder: a compound sent empty writes nothing (the file removed), a field not sent keeps the data's file.
        $form = FormDefinition::new('form')->add(FormDefinition::field('picture', Kind::IMAGE));
        self::assertNull((new FormBinder())->bind($form, ['picture' => ['file' => '', 'raw' => null]], ['picture' => 'kept'])->data['picture']);
        self::assertSame('kept', (new FormBinder())->bind($form, [], ['picture' => 'kept'])->data['picture']);
    }

    public function testAFileDroppedIsKeptShownAgainRemovedAndReadBack(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $dropzone = static::getContainer()->get(Dropzone::class);

        $data = $dropzone->data(['maxFilesize' => 1]);
        $path = tempnam(sys_get_temp_dir(), 'drop');
        file_put_contents($path, 'a sound');
        $answer = $dropzone->store($data, new UploadedFile($path, 'ringo.mp3', 'audio/mpeg', null, true), \UPLOAD_ERR_OK, static fn (string $from, string $to): bool => rename($from, $to));
        self::assertSame(200, $answer->status, json_encode($answer->body));
        self::assertSame(Dropzone::STATUS_OK, $answer->body['status']);
        $uuid = $answer->body['uuid'];
        self::assertSame($dropzone->directory().'/'.$uuid, $dropzone->preview($data, $uuid), 'shown again');

        $files = Dropzone::files($uuid.'|5d469c63-ce06-4f45-b555-728878b371d6', $dropzone->directory(), true);
        self::assertInstanceOf(\SplFileInfo::class, $files[0], 'a file kept: to store');
        self::assertSame('5d469c63-ce06-4f45-b555-728878b371d6', $files[1], 'a file the record holds: its name');

        self::assertSame(['status' => Dropzone::STATUS_OK, 'uuid' => $uuid], $dropzone->delete($data, $uuid)->body);
        self::assertNull($dropzone->preview($data, $uuid), 'removed');

        // Another visitor's token, a file too big: refused.
        $request->getSession()->clear();
        static::getContainer()->get('security.csrf.token_storage')->clear();
        self::assertSame(500, $dropzone->store($data, null)->status);
        $big = tempnam(sys_get_temp_dir(), 'drop');
        file_put_contents($big, str_repeat('x', 2000));
        self::assertSame(500, $dropzone->store($dropzone->data(['maxFilesize' => 0.001]), new UploadedFile($big, 'big.mp3', 'audio/mpeg', null, true))->status, 'too big');
        unlink($big);
    }
}
