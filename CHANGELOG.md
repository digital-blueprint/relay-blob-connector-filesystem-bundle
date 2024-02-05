# v0.1.11
* Remove connector endpoint since its not necessary anymore

# v0.1.10
* Add additional checks on data retrieval
* Introduce new error cases on filedata retrieval

# v0.1.9
* Fix bug that made older files retrievable (bug occurred since v0.1.7)

# v0.1.8
* Update to blob v0.1.18
* Remove `setExtension` and fix test issues caused by new blob version

# v0.1.7
* Remove `getExtension` since it is no longer supported by blob

# v0.1.6
* Rename `getBinaryData` to `getBase64Data` in FilesystemService
* Implement `getBinaryResponse` in FilesystemService that responds with binary data

# v0.1.4
* Removal of `path` and rename of `public_key` to `key` in blob config

# v0.1.3
* Remove last traces of the legacy database
* Handle blob/filesystem/{id} route cleaner
* Code cleanup

# v0.1.2
* Remove database from connector
* Change signature to signed checksum, using sha2

# v0.1.1

* Update to api-platform 2.7