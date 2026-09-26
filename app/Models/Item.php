<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property ItemStatus $status
 * @property ItemPriority $priority
 */
#[Fillable(['name', 'note', 'room_id', 'unit_price', 'quantity', 'url', 'assigned_user_id', 'status', 'priority'])]
final class Item extends Model implements HasMedia
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'status' => ItemStatus::class,
            'priority' => ItemPriority::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photo')
            ->nonQueued()
            ->width(400)
            ->format('webp');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'note', 'room_id', 'unit_price', 'quantity', 'url', 'assigned_user_id', 'status', 'priority'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Shopping-list order: planned before bought, then high → low priority, then name.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function shoppingOrder(Builder $query): void
    {
        $query
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ItemStatus::Planned->value])
            ->orderByRaw('CASE priority WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [ItemPriority::High->value, ItemPriority::Medium->value])
            ->orderBy('name');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
