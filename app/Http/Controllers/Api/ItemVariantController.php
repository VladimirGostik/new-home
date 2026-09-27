<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\CreateItemVariantData;
use App\Data\ItemVariantListItemData;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\ItemVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Item variants', 'Variants of a shopping-list item')]
#[Authenticated]
final class ItemVariantController extends Controller
{
    public function __construct(
        private readonly ItemVariantService $itemVariantService,
    ) {}

    #[Authorize('update', 'item')]
    #[Endpoint('Create item variant', 'Creates a new variant for an item.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function store(CreateItemVariantData $data, Item $item, Request $request): JsonResponse
    {
        $variant = $this->itemVariantService->create($item, $data);

        return response()->json(
            ItemVariantListItemData::fromModel($variant, $item->selected_variant_id, $request->user()?->id),
            201,
        );
    }
}
