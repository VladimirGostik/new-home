<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class DownloadedImage
{
    public function __construct(
        public string $path,
        public string $fileName,
        public string $mimeType,
    ) {}

    public function attachTo(HasMedia $model, string $collection): Media
    {
        return $model->addMedia($this->path)
            ->usingName(pathinfo($this->fileName, PATHINFO_FILENAME))
            ->usingFileName($this->fileName)
            ->toMediaCollection($collection);
    }

    public function discard(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
