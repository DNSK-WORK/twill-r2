<?php

namespace DnskWork\TwillR2;

use A17\Twill\Repositories\FileRepository as TwillFileRepository;
use A17\Twill\Repositories\MediaRepository as TwillMediaRepository;
use A17\Twill\Services\Uploader\SignS3Upload as TwillSignS3Upload;
use DnskWork\TwillR2\Repositories\FileRepository;
use DnskWork\TwillR2\Repositories\MediaRepository;
use DnskWork\TwillR2\Services\FileService;
use DnskWork\TwillR2\Services\SignS3Upload;
use Illuminate\Support\ServiceProvider;

class TwillR2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fix safe deletion so R2 listing errors don't roll back the DB transaction.
        $this->app->bind(TwillMediaRepository::class, MediaRepository::class);
        $this->app->bind(TwillFileRepository::class, FileRepository::class);

        // Fix Fine Uploader policy validation (content-length-range condition breaks Twill's original check).
        $this->app->bind(TwillSignS3Upload::class, SignS3Upload::class);

        // Fix public URL generation from FILE_LIBRARY_PUBLIC_URL env var.
        // Only applied when the config key is set — without it the original Twill behaviour is preserved.
        if ($this->app['config']->get('filesystems.file_library_public_url')) {
            $this->app->singleton('fileService', fn ($app) => $app->make(FileService::class));
        }
    }
}
