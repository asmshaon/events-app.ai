<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventListingRequest;
use App\Models\Event;
use App\Services\EventListingService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(EventListingService $listing): Response
    {
        return Inertia::render('Events/Index', [
            'statuses' => EventListingService::STATUSES,
            'filterOptions' => $listing->filterOptions(),
        ]);
    }

    public function visualOne(EventListingService $listing): Response
    {
        return Inertia::render('Events/VisualOne', [
            'statuses' => EventListingService::STATUSES,
            'filterOptions' => $listing->filterOptions(),
        ]);
    }

    public function visualTwo(EventListingService $listing): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'statuses' => EventListingService::STATUSES,
            'filterOptions' => $listing->filterOptions(),
        ]);
    }

    public function data(EventListingRequest $request, EventListingService $listing): JsonResponse
    {
        [$events, $stats] = $listing->paginate($request->filters());

        return response()->json([
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load(['user', 'images']);

        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }
}
