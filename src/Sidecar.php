<?php

namespace EliteDevSquad\SidecarLaravel;

class Sidecar
{
    public static string $userModel = \App\Models\User::class; // @phpstan-ignore-line

    /**
     * @var array<string, string>
     */
    public static array $userMap = [
        'id' => 'id',
        'name' => 'first_name',
        'role' => 'role',
        'email' => 'email',
    ];

    /**
     * @return array<string, string>
     */
    public static function getUserMap(): array
    {
        return self::$userMap; // @codeCoverageIgnore
    }

    public static function getUserModel(): string
    {
        return self::$userModel;
    }
}
