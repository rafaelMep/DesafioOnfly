<?php

namespace App\Http\Controllers;

use App\Services\DestinationSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function __invoke(Request $request, DestinationSearch $search): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:150']]);

        return response()->json($search->search($validated['q']));
    }
}
