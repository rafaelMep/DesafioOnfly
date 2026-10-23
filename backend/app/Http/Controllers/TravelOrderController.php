<?php

namespace App\Http\Controllers;

use App\Actions\CreateTravelOrder;
use App\Actions\UpdateTravelOrderStatus;
use App\Enums\TravelStatus;
use App\Http\Requests\ListTravelOrdersRequest;
use App\Http\Requests\StoreTravelOrderRequest;
use App\Http\Requests\UpdateTravelStatusRequest;
use App\Http\Resources\TravelOrderResource;
use App\Models\TravelOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TravelOrderController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $query = TravelOrder::query();
        if (! $request->user()->is_admin) {
            $query->where('user_id', $request->user()->id);
        }
        $counts = $query->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json(['data' => [
            'total' => (int) $counts->sum(),
            'solicitado' => (int) ($counts['solicitado'] ?? 0),
            'aprovado' => (int) ($counts['aprovado'] ?? 0),
            'cancelado' => (int) ($counts['cancelado'] ?? 0),
        ]]);
    }

    public function index(ListTravelOrdersRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = TravelOrder::query()->with('user');

        if (! $request->user()->is_admin) {
            $query->where('user_id', $request->user()->id);
        }

        $query->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $query->when($filters['destination'] ?? null, fn ($q, $destination) => $q->whereRaw('LOWER(destination) LIKE ?', ['%'.mb_strtolower($destination).'%']));

        if (($filters['date_type'] ?? 'created') === 'travel') {
            // Intersection: include trips that overlap any part of the requested interval.
            $query->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('return_date', '>=', $date));
            $query->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('departure_date', '<=', $date));
        } else {
            $query->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date));
            $query->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
        }

        return TravelOrderResource::collection($query->orderByDesc('id')->paginate($filters['per_page'] ?? 10)->withQueryString());
    }

    public function store(StoreTravelOrderRequest $request, CreateTravelOrder $action): TravelOrderResource
    {
        $order = $action->execute($request->user(), $request->validated());

        return new TravelOrderResource($order);
    }

    public function show(TravelOrder $travelOrder): TravelOrderResource
    {
        Gate::authorize('view', $travelOrder);

        return new TravelOrderResource($travelOrder->load('user'));
    }

    public function updateStatus(UpdateTravelStatusRequest $request, TravelOrder $travelOrder, UpdateTravelOrderStatus $action): TravelOrderResource
    {
        $order = $action->execute($request->user(), $travelOrder, TravelStatus::from($request->validated('status')));

        return new TravelOrderResource($order->load('user'));
    }
}
