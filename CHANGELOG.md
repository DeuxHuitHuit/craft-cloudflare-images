# Change log

We try to maintain a complete change log, based on what is available in git.

## 2.3.0 - 2026-07-09

* 16dd3ebef4 Add download and reupload CLI for account migration

## 2.2.3 - 2025-06-03

* 86c17ba420 (chore) Compat with php 8.4 (#12)

## 2.2.2 - 2025-03-18

* addc4f9ed1 (chore) Add a note about cloudflareImagesUrl()
* ea38f9c0d9 (fix) Remove paths from filename
* 795d6a13c1 (fix) Duplicated ID label

## 2.2.1 - 2025-01-31

* 463311f168 (fix) Bug where getSize does not exists anymore?
* 33f75409a7 (fix) Add a allowed mime type list

## 2.2.0 - 2025-01-22

* f329d63daa (feat) Add directory operations: exists + delete (#9)
* a9c67c89b2 (feat) Allow images to be moved across volume (#7)
* 9f7f350c63 (fix) Add account id validation in filename passing (#8)
* a3ecaf2611 (feat) Add a filesize check

## 2.1.0 - 2024-11-02

This release make it impossible to rename files. It was allowed but was causing issues with the files and could lead to data loss.

* f87919cdeb (feat) Implement proper file index/listing
* 18b4ce28b7 (fix) Bypass Craft for filename overwrite
* 704352a69f (fix) Compare directories when a file is found
* 2e8a958797 (fix) Silence errors if the file is broken
* cc43fe164b (feat) Prevent file renaming
* 64cb0cb926 (feat) Make sure only images are accepted
* 5aeaa52132 (docs) Add docs about FS and Volumes
* 18373f4a00 (fix) Use cleaned path in recentFiles

## 2.0.1 - 2024-08-14

* 0b4e69d766 (fix) Convert Craft image position to CF transforms gravity (#5)

## 2.0.0 - 2024-08-13

This is a breaking change since v2 only support Craft 5.

* b0fb2e5a8d (feat) Craft 5 support (#4)

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
