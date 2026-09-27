<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\CreateItemData;
use App\Data\ItemDetailData;
use App\Data\ItemListItemData;
use App\Data\ItemVariantComparisonData;
use App\Data\PatchItemData;
use App\Data\SaveItemVariantComparisonData;
use App\Data\SyncItemAllocationsData;
use App\Data\UpdatePhotoFromUrlData;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\ItemService;
use App\Utils\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Items', 'Shopping-list items')]
#[Authenticated]
final class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService,
    ) {}

    #[Authorize('viewAny', Item::class)]
    #[Endpoint('List items', 'Returns a paginated list of items with optional filtering and sorting.')]
    #[QueryParam('filter[search]', 'string', 'Search by name.', required: false, example: 'milk')]
    #[QueryParam('filter[room]', 'string', 'Filter by items having an allocation in the room UUID, or "house" for a Celý dom allocation.', required: false, example: 'house')]
    #[QueryParam('filter[status]', 'string', 'Filter by exact status.', required: false, example: 'planned')]
    #[QueryParam('sort', 'string', 'Sort field. Prefix with `-` for descending. Allowed: name, created_at, priority, status.', required: false, example: '-created_at')]
    #[QueryParam('per_page', 'integer', 'Number of results per page (default 25, clamped 1-100).', required: false, example: 25)]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    public function index(Request $request): JsonResponse
    {
        $baseQuery = Item::query()
            ->with(['allocations.room:id,name,sort_order', 'assignedUser', 'media', 'selectedVariant:id,name'])
            ->withCount('variants')
            ->when(! $request->filled('sort'), fn (Builder $query) => $query->shoppingOrder());

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        $items = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::search(['name']),
                AllowedFilter::callbackClean('room', function (Builder $query, mixed $value): void {
                    /** @var Builder<Item> $query */
                    $query->inRoomFilter(is_string($value) ? $value : '');
                }),
                AllowedFilter::dynamic('status'),
            )
            ->allowedSorts('name', 'created_at', 'priority', 'status')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Item $item) => ItemListItemData::fromModel($item));

        return response()->json($items);
    }

    #[Authorize('view', 'item')]
    #[Endpoint('Get item', 'Returns a single item with its variants and AI variant comparison.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function show(Item $item, Request $request): JsonResponse
    {
        return response()->json(ItemDetailData::fromModel($item, $request->user()?->id));
    }

    #[Authorize('create', Item::class)]
    #[Endpoint('Create item', 'Creates a new shopping-list item. Rooms via allocations[] (list of {room_id, quantity}), or the legacy room_id + quantity for a single room.')]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    public function store(CreateItemData $data): JsonResponse
    {
        $item = $this->itemService->create($data);

        return response()->json(ItemListItemData::fromModel($item), 201);
    }

    #[Authorize('update', 'item')]
    #[Endpoint('Update item', 'Partial update — only the sent fields change. Rooms/quantities are not part of this endpoint; use "Set item allocations". Fails if unit_price or url is sent while the item has a selected variant.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 422, 'Validation error, empty body, or price/url sent while a variant is selected')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function update(PatchItemData $data, Item $item): JsonResponse
    {
        $item = $this->itemService->patch($item, $data);

        return response()->json(ItemListItemData::fromModel($item));
    }

    #[Authorize('delete', 'item')]
    #[Endpoint('Delete item', 'Deletes an item together with its variants, votes and room allocations.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function destroy(Item $item): HttpResponse
    {
        $this->itemService->delete($item);

        return response()->noContent();
    }

    #[Authorize('update', 'item')]
    #[Endpoint('Set item allocations', 'Full replacement of an item\'s room allocations (list of {room_id, quantity}, room_id null = Celý dom). Idempotent.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function updateAllocations(SyncItemAllocationsData $data, Item $item): JsonResponse
    {
        $item = $this->itemService->syncAllocations($item, $data->allocations);

        return response()->json(ItemListItemData::fromModel($item));
    }

    #[Authorize('update', 'item')]
    #[Endpoint('Save variant comparison', 'Saves an AI-written variant comparison text for an item. Requires at least 2 variants.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 422, 'Validation error, or fewer than 2 variants')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function updateVariantComparison(SaveItemVariantComparisonData $data, Item $item): JsonResponse
    {
        $item = $this->itemService->saveVariantComparison($item, $data);

        return response()->json(ItemVariantComparisonData::fromItem($item));
    }

    #[Authorize('update', 'item')]
    #[Endpoint('Set item photo from URL', 'Downloads an image from a URL and sets it as the item photo. Fails if the item has a selected variant, since its photo is mirrored from the variant.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 422, 'Validation error, download failed, or item has a selected variant')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function updatePhoto(UpdatePhotoFromUrlData $data, Item $item): JsonResponse
    {
        $item = $this->itemService->replacePhotoFromUrl($item, $data->photo_url);

        return response()->json(ItemListItemData::fromModel($item));
    }
}
