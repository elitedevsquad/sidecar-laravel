<?php

namespace EliteDevSquad\SidecarLaravel;

use BackedEnum;
use EliteDevSquad\SidecarLaravel\Http\Resources\SidecarUserResource;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Throwable;

class UserDirectory
{
    public const PER_PAGE = 30;

    public const MAX_PER_PAGE = 100;

    /**
     * @var array<string, bool>
     */
    private array $columns = [];

    public function __construct(private readonly Sidecar $sidecar) {}

    /**
     * @param  array<int, int|string>  $ids
     * @return array{data: array<mixed>, meta: array{current_page: int, per_page: int, total: int, has_more: bool}}
     */
    public function page(?string $search = null, ?string $role = null, array $ids = [], int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $query = $this->query();
        $map = $this->sidecar->getUserMap();

        if ($ids !== []) {
            $query->whereKey($ids);
        }

        $search = trim((string) $search);

        if ($search !== '') {
            $this->applySearch($query, $search, $map);
        }

        $role = trim((string) $role);

        if ($role !== '' && isset($map['role'])) {
            $this->applyRole($query, $role, $map['role']);
        }

        if ($query->getQuery()->orders === null || $query->getQuery()->orders === []) {
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));

        return [
            'data' => SidecarUserResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @return array<int, string>|null
     */
    public function roles(): ?array
    {
        $path = $this->sidecar->getUserMap()['role'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $query = $this->query();

            if (! str_contains($path, '.')) {
                if (! $this->hasColumn($query->getModel(), $path)) {
                    return null;
                }

                $values = $query->reorder()->distinct()->limit(50)->pluck($path);
            } else {
                [$relation, $column] = $this->splitPath($path);
                $related = $this->relation($query->getModel(), $relation)?->getRelated();

                if ($related === null || ! $this->hasColumn($related, $column)) {
                    return null;
                }

                $values = $related->newQuery()->distinct()->limit(50)->pluck($column);
            }
        } catch (Throwable) {
            return null;
        }

        return $values
            ->map(fn (mixed $value) => $value instanceof BackedEnum ? (string) $value->value : $value)
            ->filter(fn (mixed $value) => is_scalar($value) && (string) $value !== '')
            ->map(fn (mixed $value) => (string) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        /** @var Builder<Model> $builder */
        $builder = $this->sidecar->getUserQueryBuilder();

        return clone $builder;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, string>  $map
     */
    private function applySearch(Builder $query, string $search, array $map): void
    {
        $term = '%'.$search.'%';
        $model = $query->getModel();

        $query->where(function (Builder $where) use ($map, $term, $search, $model) {
            $matched = false;

            if (ctype_digit($search)) {
                $where->orWhere($model->getQualifiedKeyName(), (int) $search);
                $matched = true;
            }

            foreach (['name', 'email'] as $field) {
                $path = $map[$field] ?? null;

                if (is_string($path) && $path !== '' && $this->orWherePath($where, $model, $path, 'like', $term)) {
                    $matched = true;
                }
            }

            if (! $matched) {
                $where->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyRole(Builder $query, string $role, string $path): void
    {
        $model = $query->getModel();

        $query->where(function (Builder $where) use ($model, $path, $role) {
            if (! $this->orWherePath($where, $model, $path, '=', $role)) {
                $where->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function orWherePath(Builder $query, Model $model, string $path, string $operator, string $value): bool
    {
        if (! str_contains($path, '.')) {
            if (! $this->hasColumn($model, $path)) {
                return false;
            }

            $query->orWhere($model->qualifyColumn($path), $operator, $value);

            return true;
        }

        [$relation, $column] = $this->splitPath($path);
        $related = $this->relation($model, $relation)?->getRelated();

        if ($related === null || ! $this->hasColumn($related, $column)) {
            return false;
        }

        $query->orWhereRelation($relation, $column, $operator, $value);

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPath(string $path): array
    {
        return [Str::beforeLast($path, '.'), Str::afterLast($path, '.')];
    }

    /**
     * @return Relation<Model, Model, mixed>|null
     */
    private function relation(Model $model, string $path): ?Relation
    {
        foreach (explode('.', $path) as $name) {
            if (! method_exists($model, $name)) {
                return null;
            }

            try {
                $relation = $model->{$name}();
            } catch (Throwable) {
                return null;
            }

            if (! $relation instanceof Relation) {
                return null;
            }

            $model = $relation->getRelated();
        }

        return $relation ?? null; // @phpstan-ignore-line
    }

    private function hasColumn(Model $model, string $column): bool
    {
        $key = $model->getConnectionName().'|'.$model->getTable().'|'.$column;

        return $this->columns[$key] ??= $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
