<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelTest extends KernelTestCase
{
    public function testBasics()
    {
        $this->assertNotNull($this->getContainer());
    }

    public function testCleanupCommandIsRegistered(): void
    {
        $application = new Application(self::bootKernel());

        $name = 'dbp:relay:blob-connector-filesystem:cleanup-empty-directories';
        $this->assertSame($name, $application->find($name)->getName());
    }
}
