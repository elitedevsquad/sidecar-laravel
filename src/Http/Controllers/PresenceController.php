<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use EliteDevSquad\SidecarLaravel\Activity\ActivityLog;
use Illuminate\Http\{JsonResponse, Request};

class PresenceController
{
    public function __construct(private readonly ActivityLog $log) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'name' => ['required', 'string', 'max:80'],
            'ttl' => ['required', 'integer', 'min:60', 'max:86400'],
        ]);

        return response()->json($this->log->heartbeat(
            $request->string('id')->toString(),
            $request->string('name')->trim()->toString(),
            $request->integer('ttl'),
        ));
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
        ]);

        return response()->json($this->log->stop($request->string('id')->toString()));
    }
}
