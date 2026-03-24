<?php

namespace EliteDevSquad\SidecarLaravel;

use App\Models\User;

class Sidecar
{
    public static string $userModel = User::class; // @phpstan-ignore-line

    public static mixed $userBuilder = null;

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

    public static function getUserQueryBuilder(): mixed
    {
        return self::$userBuilder ?? self::$userModel::query(); // @codeCoverageIgnore
    }
}
