<?php

namespace Tests\Base\Field;

use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\Editor;
use Base\Field\Behaviour\Wysiwyg;
use Base\Field\Editor\EditorJs;
use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The editors on every host (plan "omnibase agnostic", step F5d, slice 6): Base\Field\Behaviour\{Editor,
 * Wysiwyg}, what EditorType and WysiwygType decided and now ask - their options, the widget's id and settings,
 * an empty document read back as no text -, and Base\Field\Editor\EditorJs, which EditorController's routes now
 * delegate to and omnibridge/laravel's EditorController calls the same way: the mentions and the pictures with
 * the editor's token, the collaboration's ticket and autosave with the room's.
 */
final class EditorTest extends KernelTestCase
{
    public function testEachEditorKindHasItsTypesDefaults(): void
    {
        $editor = Behaviours::of(Kind::EDITOR);
        self::assertInstanceOf(Editor::class, $editor);
        self::assertSame('editor', $editor->assets([])->controller);
        $options = $editor->options([]);
        self::assertSame('form.editor', $editor->assets($options)->entry);
        self::assertNull($options['empty_data']);
        self::assertFalse($options['collab_autosave']);
        self::assertFalse($options['collab_live']);
        self::assertSame(array_keys(EditorJs::ADDRESSES), array_slice(array_keys($editor->endpoints($options)), 0, 5), 'the five routes the widget reads');

        $wysiwyg = Behaviours::of(Kind::WYSIWYG);
        self::assertInstanceOf(Wysiwyg::class, $wysiwyg);
        $options = $wysiwyg->options(['height' => '400px']);
        self::assertSame('form.wysiwyg', $wysiwyg->assets($options)->entry);
        $vars = $wysiwyg->vars($options, '<p>Help!</p>', 'crud_form_translations_fr-FR_content');
        self::assertSame('crud_form_translations_fr_FR_content', $vars['id'], 'Quill\'s element named without dashes');
        self::assertSame(['theme' => 'snow', 'modules' => Wysiwyg::MODULES, 'placeholder' => $options['placeholder'], 'height' => '400px'], json_decode($vars['wysiwyg'], true));
    }

    public function testAnEmptyDocumentIsNoText(): void
    {
        $editor = Behaviours::of(Kind::EDITOR);
        $document = '{"time":1,"blocks":[{"id":"a","type":"paragraph","data":{"text":"Help!"}}]}';
        self::assertNull($editor->reverse('{"time":2,"blocks":[]}', []));
        self::assertSame($document, $editor->reverse($document, []));
        self::assertNull($editor->reverse(null, []));

        $form = FormDefinition::new('form')->add(FormDefinition::field('content', Kind::EDITOR));
        self::assertNull((new FormBinder())->bind($form, ['content' => '{"time":2,"blocks":[]}'])->data['content']);
        self::assertSame($document, (new FormBinder())->bind($form, ['content' => $document])->data['content']);
    }

    public function testTheEditorsRoutesCheckTheirTokens(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $editorJs = static::getContainer()->get(EditorJs::class);

        $data = $editorJs->data();
        $addresses = $editorJs->addresses();
        self::assertSame(array_keys(EditorJs::ADDRESSES), array_keys($addresses));
        // The token is masked anew each time it is asked (Symfony's CSRF manager): the addresses by their form.
        self::assertMatchesRegularExpression('#^/ux/editorjs/[0-9a-f]+$#', $addresses['uploadByFile']);
        self::assertMatchesRegularExpression('#^/ux/editorjs/user/[0-9a-f]+$#', $addresses['endpointByUser']);
        self::assertMatchesRegularExpression('#^/ux/editorjs/[0-9a-f]+/fetch$#', $addresses['uploadByUrl']);
        self::assertSame(200, $editorJs->keywords(substr($addresses['endpointByKeyword'], strlen('/ux/editorjs/keyword/')), 'POST', ['query' => 'ringo'])->status, 'an address\'s own token holds');

        // The mentions: with the editor's token, a page of five (none in a fresh database); without it, refused.
        $answer = $editorJs->users($data, 'POST', ['query' => 'ringo']);
        self::assertSame(200, $answer->status, json_encode($answer->body));
        self::assertSame(EditorJs::STATUS_OK, $answer->body['success']);
        self::assertSame(['page' => 1, 'more' => false], $answer->body['pagination']);
        self::assertSame(500, $editorJs->keywords('another', 'POST', ['query' => 'ringo'])->status);
        self::assertSame(500, $editorJs->threads($data, 'POST', [])->status, 'no query');

        // A picture: refused without the token or a file.
        self::assertSame(500, $editorJs->uploadByFile('another', null)->status);
        self::assertSame(500, $editorJs->uploadByFile($data, null)->status);
        $path = tempnam(sys_get_temp_dir(), 'editor');
        file_put_contents($path, 'not a picture');
        self::assertSame(500, $editorJs->uploadByFile($data, new UploadedFile($path, 'ringo.png', 'image/png', \UPLOAD_ERR_PARTIAL, true), \UPLOAD_ERR_PARTIAL)->status);
        @unlink($path);

        // The collaboration: the room's own token, an account signed in, a target the persistence maps.
        self::assertSame(400, $editorJs->ticket([])->status);
        self::assertSame(403, $editorJs->ticket(['room' => 'App\\Entity\\Thing:1:content:_', 'token' => 'nope'])->status);
        $room = $editorJs->rooms()->buildRoom('Base\\Entity\\Thread', 1, 'content');
        $token = static::getContainer()->get('security.csrf.token_manager')->getToken($editorJs->rooms()->tokenId($room))->getValue();
        self::assertSame(403, $editorJs->ticket(['room' => $room, 'token' => $token])->status, 'no one signed in');
        self::assertSame(400, $editorJs->autosave(['fqcn' => 'Base\\Entity\\Thread'])->status, 'no id, no field');
        self::assertSame(403, $editorJs->autosave(['fqcn' => 'Base\\Entity\\Thread', 'id' => 1, 'field' => 'content', 'token' => 'nope'])->status);
        self::assertSame(404, $editorJs->autosave(['fqcn' => 'Base\\Entity\\Thread', 'id' => 1, 'field' => 'content', 'token' => $token, 'value' => '{}'])->status, 'no such thread in a fresh database');
        $room = $editorJs->rooms()->buildRoom('stdClass', 1, 'content');
        $token = static::getContainer()->get('security.csrf.token_manager')->getToken($editorJs->rooms()->tokenId($room))->getValue();
        self::assertSame(400, $editorJs->autosave(['fqcn' => 'stdClass', 'id' => 1, 'field' => 'content', 'token' => $token])->status, 'not a record');
    }

    public function testEditorTypeDrawsWhatTheBehaviourGives(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $document = '{"time":1,"blocks":[{"id":"a","type":"paragraph","data":{"text":"Help!"}}]}';
        $view = static::getContainer()->get('form.factory')->createNamedBuilder('crud_form', \Symfony\Component\Form\Extension\Core\Type\FormType::class, ['content' => $document, 'html' => '<p>Help!</p>'], ['csrf_protection' => false])
            ->add('content', 'Base\\Field\\Type\\EditorType')
            ->add('html', 'Base\\Field\\Type\\WysiwygType')
            ->getForm()->createView();

        $editorJs = static::getContainer()->get(EditorJs::class);
        self::assertSame($document, $view['content']->vars['value']);
        self::assertMatchesRegularExpression('#^/ux/editorjs/thread/[0-9a-f]+$#', $view['content']->vars['endpointByThread']);
        self::assertSame(['placeholder' => $view['content']->vars['editor'] ? json_decode($view['content']->vars['editor'], true)['placeholder'] : null], json_decode($view['content']->vars['editor'], true), 'no collaboration asked');
        self::assertSame('snow', json_decode($view['html']->vars['wysiwyg'], true)['theme']);
    }
}
