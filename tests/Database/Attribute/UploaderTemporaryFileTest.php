<?php

namespace Tests\Base\Database\Attribute;

use Base\Database\Attribute\Uploader;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the remote-storage copy in Uploader::get().
 *
 * Every call used to fwrite() the object into the same cached tmpfile() handle
 * without rewinding, so reading the same upload twice in one request produced a
 * file holding the content twice - a corrupt image - and downloaded it twice.
 * In a long-running process the handles were never closed either.
 */
class UploaderTemporaryFileTest extends TestCase
{
    protected function tearDown(): void
    {
        Uploader::forgetTemporaryFiles();
    }

    public function testTheSameObjectIsDownloadedOnceAndNeverAppended(): void
    {
        $reads = 0;
        $read = function () use (&$reads) {
            $reads++;

            return 'PNG-BYTES';
        };

        $first = Uploader::materializeTemporaryFile('adapter:uuid-1', $read);
        $second = Uploader::materializeTemporaryFile('adapter:uuid-1', $read);

        $this->assertSame(1, $reads, 'a second get() of the same upload must not download it again');
        $this->assertSame($first->getPathname(), $second->getPathname());
        $this->assertSame('PNG-BYTES', file_get_contents($second->getPathname()), 'content must not be appended twice');
    }

    public function testDistinctObjectsGetDistinctCopies(): void
    {
        $a = Uploader::materializeTemporaryFile('adapter:uuid-a', fn () => 'AAA');
        $b = Uploader::materializeTemporaryFile('adapter:uuid-b', fn () => 'BBB');

        $this->assertNotSame($a->getPathname(), $b->getPathname());
        $this->assertSame('AAA', file_get_contents($a->getPathname()));
        $this->assertSame('BBB', file_get_contents($b->getPathname()));
    }

    public function testForgettingDeletesTheCopiesAndTheNextCallFetchesAgain(): void
    {
        $reads = 0;
        $read = function () use (&$reads) {
            $reads++;

            return 'v' . $reads;
        };

        $before = Uploader::materializeTemporaryFile('adapter:uuid-1', $read)->getPathname();
        $this->assertFileExists($before);

        Uploader::forgetTemporaryFiles();

        $this->assertFileDoesNotExist($before, 'closing a tmpfile() handle must delete the copy');

        $after = Uploader::materializeTemporaryFile('adapter:uuid-1', $read);
        $this->assertSame(2, $reads);
        $this->assertSame('v2', file_get_contents($after->getPathname()));
    }

    public function testAFailingReadLeavesNothingCached(): void
    {
        try {
            Uploader::materializeTemporaryFile('adapter:uuid-broken', function () {
                throw new \RuntimeException('storage unavailable');
            });
            $this->fail('the read exception must propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('storage unavailable', $e->getMessage());
        }

        $file = Uploader::materializeTemporaryFile('adapter:uuid-broken', fn () => 'recovered');
        $this->assertSame('recovered', file_get_contents($file->getPathname()));
    }
}
