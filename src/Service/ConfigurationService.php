<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

class ConfigurationService
{
    /**
     * @var array
     */
    private $config = [];

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getPath(): string
    {
        return $this->config['path'];
    }

    public function getLinkUrl(): string
    {
        return $this->config['link_url'];
    }

    public function getLinkExpireTime(): string
    {
        return $this->config['link_expire_time'];
    }
}
