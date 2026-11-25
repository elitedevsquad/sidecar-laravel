<?php

namespace EliteDevSquad\SidecarLaravel;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

class TinkerCompletions
{
    /** @var list<string>|null */
    public static ?array $paths = null;

    public static int $perPage = 20;

    /**
     * Describes one page of the models, so a big application never answers in one slow request.
     *
     * @return array{models: list<array<string, mixed>>, page: int, last_page: int, total: int}
     *
     * @throws ReflectionException
     */
    public function page(int $page): array
    {
        $classes = $this->classes();
        usort($classes, fn (string $a, string $b) => [class_basename($a), $a] <=> [class_basename($b), $b]);

        $models = [];

        foreach (array_slice($classes, ($page - 1) * static::$perPage, static::$perPage) as $class) {
            $model = $this->describe($class);

            if ($model !== null) {
                $models[] = $model;
            }
        }

        return [
            'models' => $models,
            'page' => $page,
            'last_page' => max(1, (int) ceil(count($classes) / static::$perPage)),
            'total' => count($classes),
        ];
    }

    /**
     * @return list<class-string>
     */
    private function classes(): array
    {
        $classes = [];

        foreach (static::$paths ?? [app_path('Models')] as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classIn($file->getContents());

                if ($class !== null) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * @return class-string|null
     */
    private function classIn(string $source): ?string
    {
        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
            return null;
        }

        if (! preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $source, $class)) {
            return null;
        }

        $name = trim($namespace[1]).'\\'.$class[1];

        return class_exists($name) && is_subclass_of($name, Model::class) && ! (new ReflectionClass($name))->isAbstract() ? $name : null;
    }

    /**
     * @param  class-string  $class
     * @return array<string, mixed>|null
     *
     * @throws ReflectionException
     */
    private function describe(string $class): ?array
    {
        $reflection = new ReflectionClass($class);

        try {
            /** @var Model $model */
            $model = $reflection->newInstance();
        } catch (Throwable) {
            return null;
        }

        $relations = [];
        $scopes = [];
        $methods = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || $method->isStatic()) {
                continue;
            }

            if ($this->isScope($method)) {
                $scopes[] = [
                    'name' => $this->scopeName($method),
                    'params' => $this->params(array_slice($method->getParameters(), 1)),
                ];

                continue;
            }

            if (! $method->isPublic() || $this->isInternal($method)) {
                continue;
            }

            $returns = $this->returnType($method);

            if ($returns !== null && is_subclass_of($returns, Relation::class) && $method->getNumberOfRequiredParameters() === 0) {
                $relations[] = [
                    'name' => $method->getName(),
                    'type' => class_basename($returns),
                    'related' => $this->related($method),
                ];

                continue;
            }

            $methods[] = [
                'name' => $method->getName(),
                'params' => $this->params($method->getParameters()),
                'returns' => $returns === null ? null : class_basename($returns),
            ];
        }

        return [
            'name' => $reflection->getShortName(),
            'class' => $class,
            'table' => $model->getTable(),
            'columns' => $this->columns($model),
            'scopes' => $scopes,
            'relations' => $relations,
            'methods' => $methods,
        ];
    }

    private function isScope(ReflectionMethod $method): bool
    {
        if (class_exists(Scope::class) && $method->getAttributes(Scope::class) !== []) {
            return true;
        }

        return strlen($method->getName()) > 5 && str_starts_with($method->getName(), 'scope') && ctype_upper($method->getName()[5]);
    }

    private function scopeName(ReflectionMethod $method): string
    {
        $name = $method->getName();

        return str_starts_with($name, 'scope') && strlen($name) > 5 && ctype_upper($name[5]) ? lcfirst(substr($name, 5)) : $name;
    }

    private function isInternal(ReflectionMethod $method): bool
    {
        $name = $method->getName();

        return str_starts_with($name, '__')
            || str_starts_with($name, 'boot')
            || str_starts_with($name, 'initialize')
            || preg_match('/^[gs]et\w+Attribute$/', $name) === 1
            || $this->returnType($method) === Attribute::class
            || in_array($name, ['casts', 'newFactory', 'newCollection', 'newEloquentBuilder'], true);
    }

    private function returnType(ReflectionMethod $method): ?string
    {
        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }

    /**
     * @param  array<int, ReflectionParameter>  $parameters
     */
    private function params(array $parameters): string
    {
        return implode(', ', array_map(function (ReflectionParameter $parameter): string {
            $type = $parameter->getType();
            $prefix = $type instanceof ReflectionNamedType ? class_basename($type->getName()).' ' : '';

            return $prefix.'$'.$parameter->getName().($parameter->isOptional() ? ' = …' : '');
        }, $parameters));
    }

    /**
     * Reads the related model from the method source, so no application code runs.
     */
    private function related(ReflectionMethod $method): ?string
    {
        $file = $method->getFileName();
        $start = $method->getStartLine();
        $end = $method->getEndLine();

        if ($file === false || $start === false || $end === false) {
            return null;
        }

        $source = implode('', array_slice(file($file) ?: [], $start - 1, $end - $start + 1));
        $pattern = '/(?:hasOne|hasMany|belongsTo|belongsToMany|morphOne|morphMany|morphToMany|morphedByMany|hasOneThrough|hasManyThrough)\(\s*\\\\?([\w\\\\]+)::class/';

        return preg_match($pattern, $source, $match) === 1 ? class_basename($match[1]) : null;
    }

    /**
     * @return list<array{name: string, type: string, cast: string|null}>
     */
    private function columns(Model $model): array
    {
        try {
            /** @var list<array{name: string, type: string}> $columns */
            $columns = $model->getConnection()->getSchemaBuilder()->getColumns($model->getTable());
        } catch (Throwable) {
            return [];
        }

        $casts = $model->getCasts();

        return array_map(fn (array $column): array => [
            'name' => $column['name'],
            'type' => $column['type'],
            'cast' => isset($casts[$column['name']]) ? class_basename($casts[$column['name']]) : null,
        ], $columns);
    }
}
