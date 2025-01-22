# Change log

We try to maintain a complete change log, based on what is available in git.

## 1.3.0 - 2025-01-22

This is a port of all changes in v2, until 2.2.0.

* f329d63daa (feat) Add directory operations: exists + delete (#9)
* a9c67c89b2 (feat) Allow images to be moved across volume (#7)
* 9f7f350c63 (fix) Add account id validation in filename parsing (#8)
* a3ecaf2611 (feat) Add a filesize check
* f87919cdeb (feat) Implement proper file index/listing
* 18b4ce28b7 (fix) Bypass Craft for filename overwrite
* 704352a69f (fix) Compare directories when a file is found
* 2e8a958797 (fix) Silence errors if the file is broken
* cc43fe164b (feat) Prevent file renaming
* 64cb0cb926 (feat) Make sure only images are accepted
* 18373f4a00 (fix) Use cleaned path in recentFiles
* 0b4e69d766 (fix) Convert Craft image position to CF transforms gravity (#5)

## 1.2.1 - 2024-11-18

* eb3a6d652c (fix) Port some fixes from v2

## 1.2.0 - 2024-06-06

* 257fa1c414 (feat) impl: CloudflareImagesFs::fileExists()

## 1.1.0 - 2024-06-03

* 0887b2fed8 (feat) Allow config in constructor
* 9b2dd0d833 (fix) Override static properties

## 1.0.2 - 2024-02-08

* 1d9dd16d0d (chore) Add php 8.3 support

## 1.0.1 - 2023-11-07

* 29f25ef2a2 Require at least craft cms 4.5.10

## 1.0.0 - 2023-10-30

- Initial version
