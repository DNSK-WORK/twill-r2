<?php

namespace Dnsk\TwillR2\Repositories;

use A17\Twill\Repositories\FileRepository as BaseFileRepository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Safe file deletion: wraps Twill's afterDelete in try/catch so that R2 listing
 * errors (e.g. ListObjectsV2 → NoSuchKey from a double-bucket endpoint) don't throw
 * and roll back the DB delete transaction.
 */
class FileRepository extends BaseFileRepository
{
    public function afterDelete($object): void
    {
        if (! Config::get('twill.file_library.cascade_delete')) {
            return;
        }

        $disk = Config::get('twill.file_library.disk');
        $storageId = $object->uuid;

        try {
            Storage::disk($disk)->delete($storageId);
        } catch (\Throwable $e) {
            Log::warning('twill-r2: file delete from disk failed', ['uuid' => $storageId, 'error' => $e->getMessage()]);
        }

        try {
            $folder = Str::finish(Str::beforeLast($storageId, '/'), '/');
            if (empty(Storage::disk($disk)->files($folder))) {
                Storage::disk($disk)->deleteDirectory($folder);
            }
        } catch (\Throwable) {
            // Directory cleanup is best-effort; don't fail the delete transaction.
        }
    }
}
