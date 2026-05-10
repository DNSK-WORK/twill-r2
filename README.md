# Twill R2 — Cloudflare R2 & S3 fixes for Laravel Twill

Fixes three bugs in [Laravel Twill](https://twillcms.com) that surface when using Cloudflare R2 or any S3-compatible storage with a custom endpoint. Covers the media library, file library, and Fine Uploader policy signing.

## What it fixes

### 1. Public URL generation (media library & file library)

Laravel Twill overwrites the disk's `url` config to a local path when `file_library.endpoint_type` is `"local"`, breaking all public file URLs on Cloudflare R2. This package reads `FILE_LIBRARY_PUBLIC_URL` instead, decoupling URL generation from the disk config entirely.

### 2. Fine Uploader S3 policy signing

Twill's `SignS3Upload::isValid()` compares `parsedMaxSize` against `(string)null`, which always fails when Fine Uploader includes a `content-length-range` condition in the upload policy — blocking all file and media uploads. This package validates by checking the `bucket` condition instead, keeping the full HMAC-SHA256 v4 signing logic intact.

### 3. Safe media & file deletion

Twill's `afterDelete()` calls `Storage::disk()->files()` to clean up empty parent directories. On Cloudflare R2, a malformed `AWS_ENDPOINT` (bucket name included) causes `ListObjectsV2` to return `NoSuchKey`, which throws `UnableToListContents` and rolls back the DB delete transaction — leaving orphaned records in `twill_medias` and `twill_files`. This package wraps both operations in `try/catch` so the database record is always deleted.

## Installation

```bash
composer require dnsk-work/twill-r2
```

The service provider is auto-discovered by Laravel. No other steps are needed for fixes 2 and 3.

For fix 1 (public URL generation), add to `config/filesystems.php`:

```php
'file_library_public_url' => env('FILE_LIBRARY_PUBLIC_URL'),
```

Then set `FILE_LIBRARY_PUBLIC_URL` in your `.env` to the public base URL of your R2 bucket (e.g. `https://files.example.com`). The override only activates when this key is present — if it's not set, Twill's default behaviour is preserved.

## Requirements

- PHP 8.1+
- Laravel Twill 3.x

## License

MIT

---

Made by [DNSK.WORK](https://dnsk.work), a [UI/UX agency](https://dnsk.work).
