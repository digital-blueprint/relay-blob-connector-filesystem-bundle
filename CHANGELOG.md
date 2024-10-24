# Changelog

## v0.1.24

* Fail in case a file is not found when deleting it
* Stop throwing ApiErrors in the connector and leave that to the blob bundle
* Adjust for blob API changes
* Remove fallback code for old filename format (pre v0.1.7)
* Clean up docs

## v0.1.23
* Remove unused 'link_url' config option
* Update for blob API changes

## v0.1.22
* Allow passing a File instead of an UploadedFile

## v0.1.21
* Fix version lock on `symfony/http-foundation`

## v0.1.20
* Drop support for Symfony 5
* Drop support for api-platform 2
* Adapt to changes in blob v0.1.52

## v0.1.19
* Remove unused dependencies
* Some test cleanups

## v0.1.17
* Add support for api-platform 3.2

## v0.1.16
* File directory names were changed from 3 characters to 2 characters
* `PoliciesStruct` was removed from Blob, the connector was adjusted accordingly

## v0.1.15
* composer: fix dependency on dbp/relay-blob-bundle

## v0.1.14
* **BREAKING**: Add a 2nd layer to the directory hierarchy of blob files. Now, the files get stored in a folder named after the `internal_bucket_id`. Inside there is a folder using 3 characters of the random UUID part, which is stored in another folder with 3 characters of the random UUID part.
* Add function that enables upload of base64 encoded strings as files
* Add support for Symfony 6

## v0.1.13
* Drop support for PHP 7.4/8.0

## v0.1.12
* Drop support for PHP 7.3

## v0.1.11
* Remove connector endpoint since its not necessary anymore

## v0.1.10
* Add additional checks on data retrieval
* Introduce new error cases on filedata retrieval

## v0.1.9
* Fix bug that made older files retrievable (bug occurred since v0.1.7)

## v0.1.8
* Update to blob v0.1.18
* Remove `setExtension` and fix test issues caused by new blob version

## v0.1.7
* Remove `getExtension` since it is no longer supported by blob

## v0.1.6
* Rename `getBinaryData` to `getBase64Data` in FilesystemService
* Implement `getBinaryResponse` in FilesystemService that responds with binary data

## v0.1.4
* Removal of `path` and rename of `public_key` to `key` in blob config

## v0.1.3
* Remove last traces of the legacy database
* Handle blob/filesystem/{id} route cleaner
* Code cleanup

## v0.1.2
* Remove database from connector
* Change signature to signed checksum, using sha2

## v0.1.1

* Update to api-platform 2.7