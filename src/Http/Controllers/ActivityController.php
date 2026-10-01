<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Activity\ActivityLog;
use Illuminate\Http\{JsonResponse, Request};

class ActivityController
{
    public function __construct(private readonly ActivityLog $log) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('full')) {
            $day = $request->string('day')->toString();
            $tester = $request->string('tester')->toString();

            return response()->json($this->log->full($day !== '' ? $day : null, $tester !== '' ? $tester : null));
        }

        return response()->json($this->log->snapshot());
    }

    public function show(string $id): JsonResponse
    {
        $entry = $this->log->entry($id);

        if ($entry === null) {
            abort(404, 'Activity entry not found.');
        }

        return response()->json($entry);
    }
}
