<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\RoomOptionData;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Rooms', 'Room lookups')]
#[Authenticated]
final class RoomController extends Controller
{
    #[Authorize('viewAny', Room::class)]
    #[Endpoint('List rooms', 'Returns all rooms ordered for display, for use as a filter/select option list.')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    public function index(): JsonResponse
    {
        $rooms = Room::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => RoomOptionData::fromModel($room))
            ->all();

        return response()->json($rooms);
    }
}
