<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    private FilesystemService $api;

    public function __construct(FilesystemService $api)
    {
        $this->api = $api;
    }

    public function getName(): string
    {
        return 'blob-connector-filesystem';
    }

    private function checkMethod(string $description, callable $func): CheckResult
    {
        $result = new CheckResult($description);
        try {
            $func();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        return [
            $this->checkMethod('Check if the filesystem path is usable', [$this->api, 'checkPath']),
        ];
    }
}
