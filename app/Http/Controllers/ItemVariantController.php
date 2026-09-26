<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CreateItemVariantData;
use App\Data\UpdateItemVariantData;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\User;
use App\Services\ItemVariantService;
use App\Services\ItemVariantVoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class ItemVariantController extends Controller
{
    public function __construct(
        private readonly ItemVariantService $itemVariantService,
        private readonly ItemVariantVoteService $voteService,
    ) {}

    #[Authorize('update', 'item')]
    public function store(CreateItemVariantData $data, Item $item): RedirectResponse
    {
        $this->itemVariantService->create($item, $data);

        return back()->with('success', __('app.variant_created'));
    }

    #[Authorize('update', 'variant')]
    public function update(UpdateItemVariantData $data, Item $item, ItemVariant $variant): RedirectResponse
    {
        $this->itemVariantService->update($variant, $data);

        return back()->with('success', __('app.variant_updated'));
    }

    #[Authorize('delete', 'variant')]
    public function destroy(Item $item, ItemVariant $variant): RedirectResponse
    {
        $this->itemVariantService->delete($variant);

        return back()->with('success', __('app.variant_deleted'));
    }

    #[Authorize('select', 'variant')]
    public function select(Item $item, ItemVariant $variant): RedirectResponse
    {
        $this->itemVariantService->select($item, $variant);

        return back()->with('success', __('app.variant_selected'));
    }

    #[Authorize('update', 'item')]
    public function unselect(Item $item): RedirectResponse
    {
        $this->itemVariantService->unselect($item);

        return back()->with('success', __('app.variant_unselected'));
    }

    #[Authorize('vote', 'variant')]
    public function vote(Item $item, ItemVariant $variant, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->voteService->cast($variant, $user);

        return back();
    }

    #[Authorize('view', 'item')]
    public function retractVote(Item $item, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->voteService->retract($item, $user);

        return back();
    }
}
