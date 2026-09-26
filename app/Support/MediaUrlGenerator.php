<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * On S3-compatible disks (Laravel Cloud object storage / Cloudflare R2) every media URL is a
 * short-lived signed URL, so photos work from a private bucket and a public one alike.
 * Local disks keep plain public URLs.
 */
final class MediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if (config("filesystems.disks.{$this->getDiskName()}.driver") !== 's3') {
            return parent::getUrl();
        }

        $minutes = (int) config('media-library.temporary_url_default_lifetime', 5);

        return $this->getTemporaryUrl(now()->addMinutes(max($minutes, 1)));
    }
}
