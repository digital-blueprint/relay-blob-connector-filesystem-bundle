Relay-Blob-Connector-Filesystem Bundle README
================================

# DbpRelayBlobConnectorFilesystemBundle

[GitHub](https://github.com/digital-blueprint/relay-blob-connector-filesystem-bundle)

This bundle is a connector bundle for the dbp-relay-blob-bundle. It implements the [`DatasystemProviderServiceInterface`](https://github.com/digital-blueprint/relay-blob-bundle/blob/main/src/Service/DatasystemProviderServiceInterface.php) of the blob bundle.
It can save files to a specific path, rename those files, remove those files, or return short-lived sharelinks.

## Requirements
You need a DbpRelayBlobConnector bundle installed to make this bundle working. E.g. [DbpRelayBlobBundle](https://github.com/digital-blueprint/relay-blob-bundle)

<!--
## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/{{package-name}}).

```bash
composer require {{package-name}}
```
-->
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
  database_url: '%env(resolve:DATABASE_URL)%'
  path: '%kernel.project_dir%/var/blobFiles' # path where files should be placed
  link_url: 'http://localhost:8000/' # base link_url of the api
  link_expire_time: 'P7D' # default max expire time of sharelinks in ISO 8601 can be overwritten by bucket config of blob bundle
```

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies of dbp/relay-blob-bundle and dbp/relay-blob-connector-filesystem-bundle
composer update dbp/relay-blob-bundle
composer update dbp/relay-blob-connector-filesystem-bundle
```

## Scripts

### Database migration

Run this script to migrate the database. Run this script after installation of the bundle and
after every update to adapt the database to the new source code.

```bash
php bin/console doctrine:migrations:migrate --em=dbp_relay_blob_connector_filesystem_bundle
```

## Functionality

### `/blob/filesystem/{identifier}`

#### GET
Returns a binary file response of a sharelink id if the sharelink is valid and it exists

## Error codes

| relay:errorId                                  | Status code | Description                      | relay:errorDetails | Example                          |
|------------------------------------------------|-------------|----------------------------------| ------------------ |----------------------------------|
| `blobConnectorFilesystem:no-identifier-set`    | 400         | No identifier set                | `message`          | |
| `blobConnectorFilesystem:download-file`        | 400         | No file with this share id found | `message`          | |
| `blob-connector-filesystem:save-file-error`    | 400         | File could not be uploaded       | `message`          | |
| `blob-connector-filesystem:generate-sharelink-error`    | 400         | Sharelink could not generated    | `message`          | |
| `blob-connector-filesystem:fileshare-not-found`    | 403         | Fileshare was not found!                                 | `message`          | |
| `blob-connector-filesystem:path-not-generated` | 500         | Path could not be generated      | `message`          | |
| `blob-connector-filesystem:sharelink-not-saved` | 500         | ShareLink could not be saved!    | `message`          | |



## CronJobs

### Cleanup Cronjob
`Blob Connector Filesystem Database cleanup`: This cronjob is for cleanup purposes. It deletes all invalid sharelinks and starts every hour.

