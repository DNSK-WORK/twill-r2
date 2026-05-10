<?php

namespace Dnsk\TwillR2\Services;

use A17\Twill\Services\FileLibrary\FileServiceInterface;
use Illuminate\Config\Repository as Config;

/**
 * Generates public file URLs from the FILE_LIBRARY_PUBLIC_URL env var instead of
 * the disk's url config, which Twill overwrites to a local path when
 * file_library.endpoint_type is "local". Also decouples URL generation from the
 * R2 endpoint misconfiguration where the bucket name is included in AWS_ENDPOINT.
 *
 * Requires filesystems.file_library_public_url to be set (via FILE_LIBRARY_PUBLIC_URL).
 */
class FileService implements FileServiceInterface
{
    public function __construct(protected Config $config) {}

    public function getUrl($id): string
    {
        $base = rtrim($this->config->get('filesystems.file_library_public_url', ''), '/');

        return $base . '/' . ltrim($id, '/');
    }
}
