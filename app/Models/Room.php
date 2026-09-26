<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'description', 'sort_order'])]
final class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
