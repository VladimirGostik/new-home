<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CreateItemData;
use App\Data\ItemListItemData;
use App\Data\ItemVariantListItemData;
use App\Data\UpdateItemData;
use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Room;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ItemService;
use App\Services\SpendingService;
use App\Utils\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

final class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService,
        private readonly SpendingService $spendingService,
    ) {}

    #[NavItem(label: 'app.items', route: 'items.index', icon: 'ListBulletIcon', permission: 'view items', order: 20)]
    #[Authorize('viewAny', Item::class)]
    public function index(Request $request): Response
    {
        $currentUserId = $request->user()?->id;

        $baseQuery = Item::query()
            ->with(['room', 'assignedUser', 'media', 'selectedVariant:id,name'])
            ->withCount('variants')
            ->when(! $request->filled('sort'), fn (Builder $query) => $query->shoppingOrder());

        $items = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::search(['name']),
                AllowedFilter::callbackClean('room', function (Builder $query, mixed $value): void {
                    if ($value === 'house') {
                        $query->whereNull('room_id');

                        return;
                    }

                    $query->where('room_id', $value);
                }),
                AllowedFilter::callbackClean('assignee', function (Builder $query, mixed $value) use ($currentUserId): void {
                    if ($value === 'me') {
                        $query->where('assigned_user_id', $currentUserId);

                        return;
                    }

                    if ($value === 'none') {
                        $query->whereNull('assigned_user_id');

                        return;
                    }

                    $query->where('assigned_user_id', $value);
                }),
                AllowedFilter::callbackClean('price', function (Builder $query, mixed $value): void {
                    if ($value === 'none') {
                        $query->whereNull('unit_price');
                    }
                }),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('priority'),
            )
            ->allowedSorts('name', 'unit_price', 'priority', 'status', 'created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Item $item) => ItemListItemData::fromModel($item));

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => $request->query(),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    #[NavItem(label: 'app.my_items', route: 'items.mine', icon: 'UserIcon', permission: 'view items', order: 30)]
    #[Authorize('viewAny', Item::class)]
    public function mine(Request $request): Response
    {
        $query = Item::query()->where('assigned_user_id', $request->user()?->id);

        return Inertia::render('Items/Mine', [
            'items' => (clone $query)
                ->with(['room', 'assignedUser', 'media', 'selectedVariant:id,name'])
                ->withCount('variants')
                ->shoppingOrder()
                ->get()
                ->map(fn (Item $item) => ItemListItemData::fromModel($item))
                ->all(),
            'summary' => $this->spendingService->summaryFor($query),
        ]);
    }

    #[Authorize('create', Item::class)]
    public function create(): Response
    {
        return Inertia::render('Items/Form', [
            'roomOptions' => $this->roomOptions(),
            'userOptions' => $this->userOptions(),
            'statusOptions' => ItemStatus::options(),
            'priorityOptions' => ItemPriority::options(),
        ]);
    }

    #[Authorize('create', Item::class)]
    public function store(CreateItemData $data, Request $request): RedirectResponse
    {
        $this->itemService->create($data);

        return redirect()->to($this->returnPath($request))->with('success', __('app.item_created'));
    }

    #[Authorize('update', 'item')]
    public function edit(Item $item): Response
    {
        return Inertia::render('Items/Form', [
            'item' => ItemListItemData::fromModel($item),
            'roomOptions' => $this->roomOptions(),
            'userOptions' => $this->userOptions(),
            'statusOptions' => ItemStatus::options(),
            'priorityOptions' => ItemPriority::options(),
        ]);
    }

    #[Authorize('view', 'item')]
    public function show(Item $item, Request $request): Response
    {
        return Inertia::render('Items/Show', [
            'item' => ItemListItemData::fromModel($item),
            'variants' => $item->variants()
                ->with(['media', 'votes.user'])
                ->get()
                ->map(fn (ItemVariant $variant) => ItemVariantListItemData::fromModel($variant, $item->selected_variant_id, $request->user()?->id))
                ->all(),
        ]);
    }

    #[Authorize('update', 'item')]
    public function update(UpdateItemData $data, Item $item, Request $request): RedirectResponse
    {
        $this->itemService->update($item, $data);

        return redirect()->to($this->returnPath($request))->with('success', __('app.item_updated'));
    }

    #[Authorize('update', 'item')]
    public function toggleStatus(Item $item): RedirectResponse
    {
        $this->itemService->toggleStatus($item);

        return back();
    }

    #[Authorize('delete', 'item')]
    public function destroy(Item $item): RedirectResponse
    {
        $this->itemService->delete($item);

        return redirect()->route('items.index')->with('success', __('app.item_deleted'));
    }

    /**
     * Local path the form came from (e.g. a room detail), never an external URL.
     */
    private function returnPath(Request $request): string
    {
        $path = $request->input('return_to');

        if (is_string($path) && preg_match('#^/(?![/\\\\])[^\s]*$#', $path) === 1) {
            return $path;
        }

        return route('items.index');
    }

    /** @return array<int, array{id: string, name: string}> */
    private function roomOptions(): array
    {
        return Room::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Room $room) => ['id' => $room->id, 'name' => $room->name])
            ->all();
    }

    /** @return array<int, array{id: string, name: string}> */
    private function userOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * @return array{
     *     rooms: array<int, array{id: string, name: string}>,
     *     users: array<int, array{id: string, name: string}>,
     *     statuses: array<int, array{id: string, name: string}>,
     *     priorities: array<int, array{id: string, name: string}>,
     * }
     */
    private function filterOptions(): array
    {
        return [
            'rooms' => $this->roomOptions(),
            'users' => $this->userOptions(),
            'statuses' => ItemStatus::options(),
            'priorities' => ItemPriority::options(),
        ];
    }
}
