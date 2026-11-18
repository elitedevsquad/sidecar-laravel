<?php

namespace Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Article extends BaseModel
{
    protected $casts = ['published_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public static bool $ran = false;

    public function reviewer(): BelongsTo
    {
        static::$ran = true;

        return $this->belongsTo(Author::class, 'reviewer_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    public function scopeWrittenBy(Builder $query, Author $author, int $limit = 10): void
    {
        $query->whereBelongsTo($author)->limit($limit);
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('title', 'like', '%!');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function getShoutAttribute(): string
    {
        return strtoupper((string) $this->title);
    }

    protected function slug(): Attribute
    {
        return Attribute::get(fn () => str($this->title)->slug());
    }

    protected function secret(): string
    {
        return 'hidden';
    }
}
