<?php

namespace Tests\Base\Field;

use Base\Database\Entity\EntityHydratorInterface;
use Base\Field\Behaviour\AssociationFile;
use Base\Field\Behaviour\Behaviours;
use Base\Field\Behaviour\Upload;
use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\FormView;
use Base\Validator\Constraints\File;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Records made from uploads and the file sent refused when the form is validated, on every host (plan
 * "omnibase agnostic", step F5d, slice 5): Base\Field\Behaviour\AssociationFile, what AssociationFileType
 * decided and now asks - its options, its upload field, the related records' files and their ids, what is
 * sent made into the related records -, and Upload::refusal(), FileType's File constraint on the file sent,
 * which the neutral FormBinder now applies: a file of another type, or over the ceiling, refused with the
 * constraint's message, the data untouched. The constraint reads an integer as bytes (FileType's ceiling,
 * read as bits until then: 32 MB refused a file over 4 MB).
 */
final class AssociationFileTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel(); // the record's mapping, which Uploader reads (its #[Uploader], #[File])
    }

    public function testItsOptionsAreTheTypes(): void
    {
        $options = AssociationFile::options(['class' => 'App\\Entity\\Gallery\\Photo', 'entity_file' => 'image.source', 'form_type' => 'Omnibridge\\Symfony\\Omnibase\\Field\\Type\\ImageType']);
        self::assertSame(Upload::IMAGE, AssociationFile::variant($options), 'the upload its form type names, by its former name');
        self::assertSame(Kind::IMAGE, AssociationFile::kind($options));
        self::assertSame('image', AssociationFile::child($options), 'the upload field: the first part of the path');
        self::assertSame('image.source', AssociationFile::path($options));
        self::assertFalse($options['allow_delete']);
        self::assertSame(Upload::AVATAR, AssociationFile::variant(['form_type' => 'Omnibridge\\Symfony\\Omnibase\\Field\\Type\\AvatarType']));
        self::assertSame(Upload::FILE, AssociationFile::variant([]), 'a file by default (FileType)');
        self::assertSame(['class' => 'X', 'allow_delete' => true, 'required' => false, 'multiple' => true, 'href' => null, 'max_size' => 10, 'max_files' => null, 'mime_types' => ['image/*'], 'sortable' => true],
            AssociationFile::childOptions(['class' => 'X', 'allow_delete' => true, 'multiple' => true, 'max_size' => 10, 'max_files' => null, 'mime_types' => ['image/*'], 'sortable' => true], false));

        foreach ([['entity_file' => 'source'], ['class' => 'X'], ['class' => 'X', 'entity_file' => 'source', 'form_type' => 'Omnibridge\\Symfony\\Omnibase\\Field\\Type\\SelectType']] as $refused) {
            try {
                AssociationFile::options($refused);
                self::fail('refused: '.json_encode($refused));
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheRelatedRecordsFilesAndTheirIds(): void
    {
        $records = new ArrayCollection([self::record(12, '/uploads/a/5d469c63.jpg'), self::record(13, null), self::record(14, '/uploads/b/4ef6f20f.jpg')]);
        self::assertSame('{"5d469c63.jpg":12,"4ef6f20f.jpg":14}', AssociationFile::entries($records, ['entity_file' => 'source']), 'each stored file\'s name by its record\'s id');
        self::assertSame('[]', AssociationFile::entries(self::record(12, '/uploads/a/5d469c63.jpg'), ['entity_file' => 'source']), 'none for one record (the type\'s finishView())');
        self::assertCount(3, AssociationFile::list($records));
        self::assertSame([], AssociationFile::list(null));
    }

    public function testWhatIsSentIsMadeIntoTheRelatedRecords(): void
    {
        $kept = self::record(12, '5d469c63');
        $left = self::record(13, '4ef6f20f');
        $sent = new \SplFileInfo(__FILE__);
        $owners = [];
        $options = ['class' => \stdClass::class, 'entity_file' => 'source', 'multiple' => true, 'entity_data' => function ($record, $owner, $file) use (&$owners) {
            $owners[] = $owner;
            $record->setSource($file);
        }];
        $owner = new \stdClass();
        $records = AssociationFile::records(['5d469c63', $sent], [$kept, $left], $options, $owner, self::hydrator());
        self::assertSame($kept, $records[0], 'a stored file\'s name: its record kept');
        self::assertSame($sent, $records[1]->getSource(), 'a file sent: a new record, given entity_data');
        self::assertCount(2, $records, 'a name left out: its record left');
        self::assertSame([$owner, $owner], $owners, 'entity_data told the owner');

        $one = AssociationFile::records($sent, $left, ['class' => \stdClass::class, 'entity_file' => 'source', 'entity_data' => []], $owner, self::hydrator());
        self::assertSame($left, $one, 'one record: the one held, given the file');
        self::assertSame($sent, $left->getSource());
        self::assertNull(AssociationFile::records(null, $left, ['class' => \stdClass::class, 'entity_file' => 'source'], $owner, self::hydrator()), 'nothing sent for one');

        $gallery = new class {
            public ArrayCollection $photos;

            public function __construct()
            {
                $this->photos = new ArrayCollection();
            }

            public function getPhotos(): ArrayCollection
            {
                return $this->photos;
            }
        };
        $gallery->photos->add($left);
        AssociationFile::bind($gallery, 'photos', [$kept], ['multiple' => true]);
        self::assertSame([$kept], $gallery->photos->toArray(), 'the collection\'s records replaced');
    }

    public function testTheUploadFieldIsDrawnInsideIt(): void
    {
        $form = FormDefinition::new('crud_form')->setCsrf(false)->add(FormDefinition::field('image', Kind::ASSOCIATION_FILE)->setFormTypeOptions(['class' => \Base\Entity\Layout\Image::class, 'entity_file' => 'source', 'form_type' => 'Omnibridge\\Symfony\\Omnibase\\Field\\Type\\ImageType', 'mime_types' => ['image/*']]));
        $view = FormView::of($form);
        self::assertSame('associationfile', $view['image']->vars['widget']);
        self::assertSame('[]', $view['image']->vars['entityId']);
        $upload = $view['image']['source'];
        self::assertSame(Kind::IMAGE, $upload->vars['kind']);
        self::assertSame('image', $upload->vars['widget'], 'drawn as an image\'s upload');
        self::assertSame('crud_form[image][source]', $upload->vars['full_name']);
        self::assertSame('crud_form_image_source', $upload->vars['id']);
        self::assertTrue($view->vars['multipart']);
    }

    public function testAFileTheFieldRefusesIsRefusedWhenTheFormIsValidated(): void
    {
        $text = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($text, 'Not a picture.');
        $picture = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($picture, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $image = Behaviours::of(Kind::IMAGE);
        $options = $image->options([]);

        self::assertSame(['file.mime_type', ['{0}' => 1, '{1}' => '*']], $image->refusal(['raw' => new UploadedFile($text, 'notes.txt', 'text/plain', null, true)], $options, 'picture'), 'a file that is no picture');
        self::assertSame(['file.max_size', ['{1}' => byte2str(filesize($picture)), '{0}' => byte2str(10)]], $image->refusal(['raw' => new UploadedFile($picture, 'ringo.png', 'image/png', null, true)], ['max_size' => 10] + $options, 'picture'), 'a picture over the ceiling');
        self::assertNull($image->refusal(['raw' => new UploadedFile($picture, 'ringo.png', 'image/png', null, true)], $options, 'picture'), 'a picture within it');
        self::assertNull($image->refusal(['raw' => [new UploadedFile($picture, 'ringo.png', 'image/png', null, true)]], $options, 'picture'), 'several: the constraint reads one file');
        self::assertNull($image->refusal(['file' => '5d469c63'], $options, 'picture'), 'none sent');

        $form = FormDefinition::new('form')->add(FormDefinition::field('picture', Kind::IMAGE));
        $binding = (new FormBinder())->bind($form, ['picture' => ['raw' => new UploadedFile($text, 'notes.txt', 'text/plain', null, true)]], ['picture' => 'kept']);
        self::assertSame('kept', $binding->data['picture'], 'the data untouched');
        self::assertCount(1, $binding->violations);
        self::assertSame(['picture', 'file.mime_type', ['{0}' => 1, '{1}' => '*']], [$binding->violations[0]->path, $binding->violations[0]->message, $binding->violations[0]->parameters]);

        unlink($text);
        unlink($picture);
    }

    public function testTheConstraintReadsAnIntegerAsBytes(): void
    {
        self::assertSame(32000000, (new File(32000000, ['image/*']))->getMaxSize(), 'FileType\'s ceiling, in bytes');
        self::assertSame(32000000, (new File('32MB'))->getMaxSize(), 'a size written out, as #[File(max_size: "32MB")]');
        self::assertSame(2000, (new File('2KB'))->getMaxSize());
    }

    public static function fresh(): object
    {
        return self::record(null, null);
    }

    private static function record(?int $id, ?string $source): object
    {
        return new class($id, $source) {
            public function __construct(private ?int $id, private mixed $source)
            {
            }

            public function getId(): ?int
            {
                return $this->id;
            }

            public function getSource(): mixed
            {
                return $this->source;
            }

            public function setSource(mixed $source): void
            {
                $this->source = $source;
            }
        };
    }

    /** A hydrator that makes a new record (id null) and writes the values given. */
    private static function hydrator(): EntityHydratorInterface
    {
        return new class implements EntityHydratorInterface {
            public function hydrate(mixed $entity, null|array|object $data = [], array $fieldExceptions = [], int $aggregateModel = 0, ...$constructArguments): mixed
            {
                // A class: a new record, not stored yet (no id).
                $entity = \is_string($entity) ? AssociationFileTest::fresh() : $entity;
                foreach ((array) $data as $name => $value) {
                    $entity->{'set'.ucfirst($name)}($value);
                }

                return $entity;
            }

            public function dehydrate(mixed $entity, array $fieldExceptions = []): ?array
            {
                return null;
            }

            public function getOriginalEntity($eventOrEntity)
            {
                return null;
            }
        };
    }
}
