# twill-r2

Fixes three Twill bugs that surface when using Cloudflare R2 (or any S3-compatible storage with a custom endpoint).

## What it fixes

### 1. Public URL generation

Twill overwrites the disk's `url` config to a local path when `file_library.endpoint_type` is `"local"`, breaking public file URLs. This package reads `FILE_LIBRARY_PUBLIC_URL` instead, decoupling URL generation from the disk config entirely.

### 2. Fine Uploader policy signing

Twill's `SignS3Upload::isValid()` compares `parsedMaxSize` against `(string)null`, which always fails when Fine Uploader includes a `content-length-range` condition in the upload policy — blocking all file uploads. This package validates by checking the `bucket` condition instead.

### 3. Safe media/file deletion

Twill's `afterDelete()` calls `Storage::disk()->files()` to clean up empty parent directories. On R2, a malformed `AWS_ENDPOINT` (bucket name included) causes `ListObjectsV2` to return `NoSuchKey`, which throws `UnableToListContents` and rolls back the DB delete transaction — leaving orphaned records. This package wraps both operations in `try/catch` so the DB record is always deleted.

## Installation

```bash
composer require dnsk-work/twill-r2
```

The service provider is auto-discovered. No other steps are needed for fixes 2 and 3.

For fix 1, add to `config/filesystems.php`:

```php
'file_library_public_url' => env('FILE_LIBRARY_PUBLIC_URL'),
```

Then set `FILE_LIBRARY_PUBLIC_URL` in your `.env` to the public base URL of your R2 bucket (e.g. `https://files.example.com`). The override only activates when this key is present.

## Requirements

- PHP 8.1+
- Twill 3.x

## License

MIT

---

Made by [DNSK](https://dnsk.work), a [UI/UX agency](https://dnsk.work).
