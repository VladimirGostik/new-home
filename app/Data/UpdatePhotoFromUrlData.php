<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;

final class UpdatePhotoFromUrlData extends Data
{
    public function __construct(
        #[Required, Url(['http', 'https']), Max(2048)]
        public readonly string $photo_url,
    ) {}
}
