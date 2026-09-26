<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CreateRoomData;
use App\Data\ItemListItemData;
use App\Data\ReorderRoomsData;
use App\Data\RoomListItemData;
use App\Data\SpendGroupData;
use App\Data\UpdateRoomData;
use App\Models\Item;
use App\Models\Room;
use App\Navigation\NavItem;
use App\Services\RoomService;
use App\Services\SpendingService;
use App\Utils\AllowedFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

final class RoomController extends Controller
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly SpendingService $spendingService,
    ) {}

    #[Authorize('viewAny', Room::class)]
    #[NavItem(label: 'app.rooms', route: 'rooms.index', icon: 'HomeModernIcon', permission: 'view rooms', order: 40)]
    public function index(Request $request): Response
    {
        $summaries = collect($this->spendingService->byRoom())->keyBy(fn (SpendGroupData $group) => $group->id ?? '');

        $rooms = QueryBuilder::for(Room::query())
            ->allowedFilters(
                AllowedFilter::search(['name']),
                AllowedFilter::dynamic('name'),
                AllowedFilter::dynamic('sort_order')->integer(),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts(
                'name',
                'sort_order',
                'created_at',
            )
            ->defaultSort('sort_order', 'name')
            ->paginate($request->integer('per_page', 100))
            ->withQueryString()
            ->through(fn (Room $room) => RoomListItemData::fromModel($room, $summaries->get($room->id)?->summary));

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
            'filters' => $request->query(),
            'wholeHouse' => $summaries->get('')?->summary,
        ]);
    }

    #[Authorize('view', 'room')]
    public function show(Room $room): Response
    {
        $items = $room->items()->getQuery();

        return Inertia::render('Rooms/Show', [
            'room' => RoomListItemData::fromModel($room, $this->spendingService->summaryFor($items)),
            'items' => (clone $items)
                ->with(['room', 'assignedUser', 'media'])
                ->shoppingOrder()
                ->get()
                ->map(fn (Item $item) => ItemListItemData::fromModel($item))
                ->all(),
        ]);
    }

    #[Authorize('reorder', Room::class)]
    public function reorder(ReorderRoomsData $data): RedirectResponse
    {
        $this->roomService->reorder($data->ids);

        return back()->with('success', __('app.rooms_reordered'));
    }

    #[Authorize('create', Room::class)]
    public function create(): Response
    {
        return Inertia::render('Rooms/Form');
    }

    #[Authorize('create', Room::class)]
    public function store(CreateRoomData $data): RedirectResponse
    {
        $this->roomService->create($data);

        return redirect()->route('rooms.index')->with('success', __('app.room_created'));
    }

    #[Authorize('update', 'room')]
    public function edit(Room $room): Response
    {
        return Inertia::render('Rooms/Form', [
            'room' => RoomListItemData::fromModel($room),
        ]);
    }

    #[Authorize('update', 'room')]
    public function update(UpdateRoomData $data, Room $room): RedirectResponse
    {
        $this->roomService->update($room, $data);

        return redirect()->route('rooms.index')->with('success', __('app.room_updated'));
    }

    #[Authorize('delete', 'room')]
    public function destroy(Room $room): RedirectResponse
    {
        $this->roomService->delete($room);

        return redirect()->route('rooms.index')->with('success', __('app.room_deleted'));
    }
}
