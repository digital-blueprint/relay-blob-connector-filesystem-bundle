<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Service;

use Symfony\Component\HttpFoundation\UrlHelper;

class ConfigurationService
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var UrlHelper
     */
    private $urlHelper;

    public function __construct(
        UrlHelper $urlHelper
    ) {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return void
     */
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
        return $this->config['linkUrl'];
    }

    public function getLinkExpireTime(): string
    {
        return $this->config['linkExpireTime'];
    }
}
