<?php

namespace EliteDevSquad\SidecarExtensionBridge;

class SidecarBridge
{
    public static string $userModel = \App\Models\User::class;

    public static array $userMap = [
        'id' => 'id',
        'name' => 'first_name',
        'role' => 'role',
        'email' => 'email',
    ];

    public static function getUserMap(): array
    {
        return self::$userMap;
    }

    public static function getUserModel(): string
    {
        return self::$userModel;
    }
}
