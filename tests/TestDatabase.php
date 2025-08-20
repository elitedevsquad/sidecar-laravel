<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

class TestDatabase
{
    public static function up(): void
    {
        self::migrate();
        self::seed();
    }

    public static function down(): void
    {
        Schema::dropIfExists('users');
    }

    public static function migrate(): void
    {
        self::down();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public static function seed(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('users')->truncate();

        Schema::enableForeignKeyConstraints();

        DB::table('users')->insert([
            'name' => 'Luan',
            'email' => 'luanfreitas10@protonmail.com',
        ]);

        DB::table('users')->insert([
            'name' => 'John Doe',
            'email' => 'jonh_doe@gmail.com',
        ]);
    }
}
