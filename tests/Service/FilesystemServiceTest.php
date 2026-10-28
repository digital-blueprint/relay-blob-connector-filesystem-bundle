<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests\Service;

use Dbp\Relay\BlobConnectorFilesystemBundle\Command\CleanupEmptyDirectoriesCommand;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ConfigurationService;
use Dbp\Relay\BlobConnectorFilesystemBundle\Service\FilesystemService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

class FilesystemServiceTest extends WebTestCase
{
    private ConfigurationService $configurationService;

    private FilesystemService $fileSystemService;

    private Filesystem $filesystem;
    private string $tempDir;
    private string $bucketDir;
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/'.uniqid('test_', true);
        $this->bucketDir = $this->tempDir.'/bucket';
        $this->uploadDir = $this->tempDir.'/upload';
        $this->filesystem->mkdir($this->bucketDir);
        $this->filesystem->mkdir($this->uploadDir);

        $config = ['path' => $this->bucketDir, 'create_path' => false];
        $this->configurationService = new ConfigurationService();
        $this->configurationService->setConfig($config);
        $this->fileSystemService = new FilesystemService($this->configurationService);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    private function getExampleFile(): File
    {
        $filesystem = new Filesystem();
        $tempFile = $filesystem->tempnam($this->uploadDir, 'blob_fs_');
        $file = dirname(__FILE__).DIRECTORY_SEPARATOR.'test.pdf';
        $this->assertTrue(copy($file, $tempFile), 'Copy testfile went wrong');

        return new File($tempFile, true);
    }

    private function getAllPaths(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->bucketDir, \RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_SELF),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $paths = [];
        foreach ($iterator as $item) {
            $paths[] = $item->getSubPathname();
        }
        sort($paths);

        return $paths;
    }

    public function testSaveGetRemoveFile()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';

        $this->assertSame([], $this->getAllPaths());

        $this->fileSystemService->saveFile($bucketId, $fileId, $this->getExampleFile());
        $this->assertSame([
            '154cc850-ede8-4c10-bff5-4e24f2ef6087',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9/11',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9/11/0192b970-cd6d-726d-a258-a911c5aac1b7',
        ], $this->getAllPaths());

        $this->fileSystemService->removeFile($bucketId, $fileId);
        $this->assertSame([
            '154cc850-ede8-4c10-bff5-4e24f2ef6087',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9',
            '154cc850-ede8-4c10-bff5-4e24f2ef6087/a9/11',
        ], $this->getAllPaths());
    }

    public function testRemoveEmptyDirectories(): void
    {
        $this->filesystem->mkdir([
            $this->bucketDir.'/empty/aa/bb',
            $this->bucketDir.'/occupied/cc/dd',
            $this->bucketDir.'/occupied/empty',
            $this->bucketDir.'/hidden',
        ]);
        $this->filesystem->dumpFile($this->bucketDir.'/occupied/cc/dd/file', 'data');
        $this->filesystem->dumpFile($this->bucketDir.'/hidden/.keep', '');

        $this->assertSame(4, $this->fileSystemService->removeEmptyDirectories());
        $this->assertSame([
            'hidden',
            'hidden/.keep',
            'occupied',
            'occupied/cc',
            'occupied/cc/dd',
            'occupied/cc/dd/file',
        ], $this->getAllPaths());
        $this->assertSame('data', file_get_contents($this->bucketDir.'/occupied/cc/dd/file'));
        $this->assertSame(0, $this->fileSystemService->removeEmptyDirectories());
        $this->assertDirectoryExists($this->bucketDir);
    }

    public function testRemoveEmptyDirectoriesDryRun(): void
    {
        $this->filesystem->mkdir([
            $this->bucketDir.'/empty/aa/bb',
            $this->bucketDir.'/occupied/empty',
        ]);
        $this->filesystem->dumpFile($this->bucketDir.'/occupied/.keep', '');
        $paths = $this->getAllPaths();

        $this->assertSame(4, $this->fileSystemService->removeEmptyDirectories(true));
        $this->assertSame($paths, $this->getAllPaths());
        $this->assertSame(4, $this->fileSystemService->removeEmptyDirectories());
    }

    public function testRemoveEmptyDirectoriesPreservesSymlinks(): void
    {
        $this->filesystem->mkdir($this->uploadDir.'/empty');
        $this->filesystem->mkdir($this->bucketDir.'/links');
        $this->assertTrue(symlink($this->uploadDir, $this->bucketDir.'/links/external'));
        $this->assertTrue(symlink($this->tempDir.'/missing', $this->bucketDir.'/links/broken'));

        $this->assertSame(0, $this->fileSystemService->removeEmptyDirectories(true));
        $this->assertSame(0, $this->fileSystemService->removeEmptyDirectories());
        $this->assertDirectoryExists($this->uploadDir.'/empty');
        $this->assertTrue(is_link($this->bucketDir.'/links/external'));
        $this->assertTrue(is_link($this->bucketDir.'/links/broken'));
    }

    public function testCleanupEmptyDirectoriesCommand(): void
    {
        $this->filesystem->mkdir($this->bucketDir.'/empty/child');
        $tester = new CommandTester(new CleanupEmptyDirectoriesCommand($this->fileSystemService));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Removed 2 empty directories.', $tester->getDisplay());
        $this->assertSame([], $this->getAllPaths());
        $this->assertDirectoryExists($this->bucketDir);
    }

    public function testCleanupEmptyDirectoriesCommandDryRun(): void
    {
        $this->filesystem->mkdir($this->bucketDir.'/empty/child');
        $paths = $this->getAllPaths();
        $tester = new CommandTester(new CleanupEmptyDirectoriesCommand($this->fileSystemService));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Would remove 2 empty directories.', $tester->getDisplay());
        $this->assertSame($paths, $this->getAllPaths());
    }

    public function testCleanupEmptyDirectoriesCommandWithMissingRoot(): void
    {
        $this->filesystem->remove($this->bucketDir);
        $this->configurationService->setConfig(['path' => $this->bucketDir, 'create_path' => true]);
        $tester = new CommandTester(new CleanupEmptyDirectoriesCommand($this->fileSystemService));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('is not a directory', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->bucketDir);
    }

    public function testAddFileAndGetFileStream()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';

        $file = $this->getExampleFile();
        $fileContents = $file->getContent(); // get it before saveFile

        $this->fileSystemService->saveFile($bucketId, $fileId, $file);

        $fileStream = $this->fileSystemService->getFileStream($bucketId, $fileId);
        $this->assertSame($fileContents, $fileStream->getContents());
    }

    public function testGetFileStreamNoExist()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';

        $this->expectException(\Exception::class);
        $this->fileSystemService->getFileStream($bucketId, $fileId);
    }

    public function testHasFile()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';

        $this->assertFalse($this->fileSystemService->hasFile($bucketId, $fileId));
        $file = $this->getExampleFile();
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
        $this->assertTrue($this->fileSystemService->hasFile($bucketId, $fileId));
        $this->fileSystemService->removeFile($bucketId, $fileId);
        $this->assertFalse($this->fileSystemService->hasFile($bucketId, $fileId));
    }

    public function testGetSumOfFilesizesAndNumberOfFilesOfBucket()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $numFiles = count([...$this->fileSystemService->listFiles($bucketId)]);
        $this->assertSame(0, $numFiles);

        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $this->fileSystemService->saveFile($bucketId, $fileId, $this->getExampleFile());

        $sumSize = 0;
        foreach ($this->fileSystemService->listFiles($bucketId) as $fileId) {
            $sumSize += $this->fileSystemService->getFileSize($bucketId, $fileId);
        }
        $numFiles = count([...$this->fileSystemService->listFiles($bucketId)]);
        $this->assertSame(9243, $sumSize);
        $this->assertSame(1, $numFiles);

        $this->fileSystemService->removeFile($bucketId, $fileId);

        $numFiles = count([...$this->fileSystemService->listFiles($bucketId)]);
        $this->assertSame(0, $numFiles);
    }

    public function testRemoveLostFile()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $this->fileSystemService->saveFile($bucketId, $fileId, $this->getExampleFile());

        $path = $this->fileSystemService->getFilePath($bucketId, $fileId);
        unlink($path);

        $this->expectException(\Exception::class);
        $this->fileSystemService->removeFile($bucketId, $fileId);
    }

    public function testFailIfPathDoesntExist()
    {
        // In case the configured path doesn't exist we should fail and not silently create it
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $file = $this->getExampleFile();
        $this->filesystem->remove($this->bucketDir);

        $this->expectException(\Exception::class);
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
    }

    public function testCreateIfConfigured()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $file = $this->getExampleFile();
        $this->filesystem->remove($this->bucketDir);
        $this->configurationService->setConfig(['path' => $this->bucketDir, 'create_path' => true]);
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
    }

    public function testFailWithInvalidId()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-..11c5aac1b7';
        $file = $this->getExampleFile();
        $this->expectException(\Exception::class);
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
    }

    public function testFailWithInvalidId2()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '';
        $file = $this->getExampleFile();
        $this->expectException(\Exception::class);
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
    }

    public function testListFiles()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $file = $this->getExampleFile();
        $this->assertSame([], [...$this->fileSystemService->listFiles($bucketId)]);
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
        $this->assertSame([$fileId], [...$this->fileSystemService->listFiles($bucketId)]);
        $this->fileSystemService->removeFile($bucketId, $fileId);
        $this->assertSame([], [...$this->fileSystemService->listFiles($bucketId)]);
    }

    public function testGetFileHash()
    {
        $bucketId = '154cc850-ede8-4c10-bff5-4e24f2ef6087';
        $fileId = '0192b970-cd6d-726d-a258-a911c5aac1b7';
        $file = $this->getExampleFile();
        $this->fileSystemService->saveFile($bucketId, $fileId, $file);
        $this->assertSame('1a3f15c65474074505208b4dc4e13bd652f6a70692debd592ff6a8e9ded15ff3', $this->fileSystemService->getFileHash($bucketId, $fileId));
    }
}
