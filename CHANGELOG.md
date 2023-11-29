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