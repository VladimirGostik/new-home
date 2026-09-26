<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Data\ItemListItemData;
use App\Models\Item;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaUrlGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_on_s3_bucket_get_signed_temporary_urls(): void
    {
        config(['media-library.disk_name' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration): string => "https://bucket.test/{$path}?expires={$expiration->getTimestamp()}",
        );

        $item = Item::factory()->create();
        $item->addMedia(UploadedFile::fake()->image('sofa.jpg', 800, 600))->toMediaCollection('photo');

        $data = ItemListItemData::fromModel($item->fresh() ?? $item);

        $this->assertStringStartsWith('https://bucket.test/', (string) $data->photo_url);
        $this->assertStringStartsWith('https://bucket.test/', (string) $data->photo_thumb_url);
        $this->assertStringContainsString('expires=', (string) $data->photo_thumb_url);
        $this->assertStringEndsWith('.webp', parse_url((string) $data->photo_thumb_url, PHP_URL_PATH) ?: '');
    }

    public function test_photos_on_local_public_disk_keep_plain_urls(): void
    {
        Storage::fake('public');

        $item = Item::factory()->create();
        $item->addMedia(UploadedFile::fake()->image('lamp.jpg'))->toMediaCollection('photo');

        $data = ItemListItemData::fromModel($item->fresh() ?? $item);

        $this->assertStringNotContainsString('expires=', (string) $data->photo_url);
        $this->assertStringContainsString('lamp', (string) $data->photo_url);
    }
}
