<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\CreateItemVariantData;
use App\Data\ItemDetailData;
use App\Data\ItemVariantListItemData;
use App\Data\PatchItemVariantData;
use App\Data\UpdatePhotoFromUrlData;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Services\ItemVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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

    #[Authorize('update', 'variant')]
    #[Endpoint('Update item variant', 'Partial update — only the sent fields change (name, unit_price, url). The photo is untouched. If this is the selected variant, changes mirror onto the item.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[UrlParam('variant', 'string', 'The UUID of the variant.', example: '0195d123-0000-7000-0000-000000000002')]
    #[Response(null, 422, 'Validation error or empty body')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item or variant not found')]
    public function update(PatchItemVariantData $data, Item $item, ItemVariant $variant, Request $request): JsonResponse
    {
        $variant = $this->itemVariantService->patch($variant, $data);

        return response()->json(
            ItemVariantListItemData::fromModel($variant, $item->selected_variant_id, $request->user()?->id),
        );
    }

    #[Authorize('delete', 'variant')]
    #[Endpoint('Delete item variant', 'Deletes a variant and its votes.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[UrlParam('variant', 'string', 'The UUID of the variant.', example: '0195d123-0000-7000-0000-000000000002')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item or variant not found')]
    public function destroy(Item $item, ItemVariant $variant): HttpResponse
    {
        $this->itemVariantService->delete($variant);

        return response()->noContent();
    }

    #[Authorize('select', 'variant')]
    #[Endpoint('Select item variant', 'Marks a variant as the winner — its price, link and photo mirror onto the item.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[UrlParam('variant', 'string', 'The UUID of the variant.', example: '0195d123-0000-7000-0000-000000000002')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item or variant not found')]
    public function select(Item $item, ItemVariant $variant, Request $request): JsonResponse
    {
        $item = $this->itemVariantService->select($item, $variant);

        return response()->json(ItemDetailData::fromModel($item, $request->user()?->id));
    }

    #[Authorize('update', 'item')]
    #[Endpoint('Unselect item variant', 'Clears the selected variant — the item price, link and photo are cleared.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item not found')]
    public function unselect(Item $item, Request $request): JsonResponse
    {
        $item = $this->itemVariantService->unselect($item);

        return response()->json(ItemDetailData::fromModel($item, $request->user()?->id));
    }

    #[Authorize('update', 'variant')]
    #[Endpoint('Set item variant photo from URL', 'Downloads an image from a URL and sets it as the variant photo. Mirrors onto the item photo when the variant is selected.')]
    #[UrlParam('item', 'string', 'The UUID of the item.', example: '0195d123-0000-7000-0000-000000000001')]
    #[UrlParam('variant', 'string', 'The UUID of the variant.', example: '0195d123-0000-7000-0000-000000000002')]
    #[Response(null, 422, 'Validation error or download failed')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'Item or variant not found')]
    public function updatePhoto(UpdatePhotoFromUrlData $data, Item $item, ItemVariant $variant, Request $request): JsonResponse
    {
        $variant = $this->itemVariantService->replacePhotoFromUrl($variant, $data->photo_url);

        return response()->json(
            ItemVariantListItemData::fromModel($variant, $item->selected_variant_id, $request->user()?->id),
        );
    }
}
