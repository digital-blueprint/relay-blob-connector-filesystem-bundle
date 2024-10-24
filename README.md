Relay-Blob-Connector-Filesystem Bundle
================================

[GitHub](https://github.com/digital-blueprint/relay-blob-connector-filesystem-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-blob-connector-filesystem-bundle) |
[Changelog](https://github.com/digital-blueprint/relay-blob-connector-filesystem-bundle/blob/main/CHANGELOG.md)

This bundle is a connector bundle for the [relay-blob-bundle](https://github.com/digital-blueprint/relay-blob-bundle) to store blob data on the filesystem.
It implements the [`DatasystemProviderServiceInterface`](https://github.com/digital-blueprint/relay-blob-bundle/blob/main/src/Service/DatasystemProviderServiceInterface.php) of the blob bundle.
It can save files to a specific path, rename those files, remove those files, or return short-lived share-links.

## Requirements

You need the DbpRelayBlob bundle installed to make this bundle working, see [DbpRelayBlobBundle](https://github.com/digital-blueprint/relay-blob-bundle).

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-blob-connector-filesystem-bundle).

```bash
composer require dbp/relay-blob-connector-filesystem-bundle
```

## Integration into the Relay API Server

* Add the bundle to your `config/bundles.php` in front of `DbpRelayCoreBundle`:

```php
...
Dbp\Relay\BlobBundle\DbpRelayBlobConnectorFilesystemBundle::class => ['all' => true],
Dbp\Relay\BlobBundle\DbpRelayBlobBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

If you were using the [DBP API Server Template](https://github.com/digital-blueprint/relay-server-template)
as template for your Symfony application, then this should have already been generated for you.

* Run `composer install` to clear caches

## Configuration

The bundle has multiple configuration values that you can specify in your
app, either by hard-coding it, or by referencing an environment variable.

For this create `config/packages/dbp_relay_blob_connector_filesystem.yaml` in the app with the following
content:

```yaml
dbp_relay_blob_connector_filesystem:
  path: '%kernel.project_dir%/var/blobFiles' # path where files should be placed
```

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`
