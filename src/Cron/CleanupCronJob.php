<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Cron;

use Dbp\Relay\BlobConnectorFilesystemBundle\Service\ShareLinkPersistenceService;
use Dbp\Relay\CoreBundle\Cron\CronJobInterface;
use Dbp\Relay\CoreBundle\Cron\CronOptions;

class CleanupCronJob implements CronJobInterface
{
    /**
     * @var ShareLinkPersistenceService
     */
    private $shareLinkPersistenceService;

    public function __construct(ShareLinkPersistenceService $shareLinkPersistenceService)
    {
        $this->shareLinkPersistenceService = $shareLinkPersistenceService;
    }

    public function getName(): string
    {
        return 'Blob Connector Filesystem Database cleanup';
    }

    public function getInterval(): string
    {
        return '0 * * * *'; // Every hour
    }

    public function run(CronOptions $options): void
    {
        $this->shareLinkPersistenceService->cleanUp();
    }
}
