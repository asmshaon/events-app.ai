<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendeeRequest;
use App\Models\Event;
use App\Services\AttendeeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AttendeeController extends Controller
{
    public function store(StoreAttendeeRequest $request, Event $event, AttendeeService $attendees): RedirectResponse
    {
        $result = $attendees->register($event, $request->attendee());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['created']
                ? "You're on the list — check your email for confirmation."
                : "You're already registered for this event.",
        ]);

        return back();
    }
}
