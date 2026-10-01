<?php

namespace EliteDevSquad\SidecarLaravel\Activity;

use Illuminate\Http\Request;

final class TesterHeader
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    public static function from(Request $request): ?self
    {
        $header = $request->header('X-Sidecar-Tester');

        if (! is_string($header) || ! str_contains($header, ';')) {
            return null;
        }

        [$id, $name] = explode(';', $header, 2);
        $id = trim($id);
        $name = trim(str_replace(["\r", "\n"], '', $name));

        if (preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id) !== 1 || $name === '') {
            return null;
        }

        return new self($id, mb_substr($name, 0, 80));
    }
}
