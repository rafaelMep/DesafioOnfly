<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Notifications\TravelOrderDecision;
use App\Notifications\TravelOrderRequested;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'unread_only' => ['nullable', 'boolean']]);
        $query = $this->inbox($request);
        $unreadCount = (clone $query)->whereNull('read_at')->count();
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection($query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString())
            ->additional(['unread_count' => $unreadCount]);
    }

    public function unreadCount(Request $request): JsonResponse
    {

        return response()->json(['unread_count' => $this->unread($request)->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        // Scope by recipient, never resolve notification IDs globally.
        $item = $this->inbox($request)->findOrFail($notification);
        $this->inbox($request)->whereKey($notification)->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
        $item->refresh();

        return response()->json(['data' => new NotificationResource($item), 'unread_count' => $this->unread($request)->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->unread($request)->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['unread_count' => $this->unread($request)->count()]);
    }

    private function unread(Request $request): MorphMany
    {
        return $this->inbox($request)->whereNull('read_at');
    }

    private function inbox(Request $request): MorphMany
    {
        $types = [TravelOrderDecision::class];
        if ($request->user()->is_admin) {
            $types[] = TravelOrderRequested::class;
        }

        return $request->user()->notifications()->whereIn('type', $types);
    }
}
