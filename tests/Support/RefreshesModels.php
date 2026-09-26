<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait RefreshesModels
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    protected function refreshed(Model $model): Model
    {
        return $model->fresh() ?? throw new RuntimeException('Model no longer exists.');
    }

    protected function firstMedia(HasMedia $model, string $collection): Media
    {
        return $model->getFirstMedia($collection) ?? throw new RuntimeException('Media not found in collection.');
    }
}
