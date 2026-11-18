<?php

namespace EliteDevSquad\SidecarLaravel;

use Illuminate\Database\Eloquent\{Builder as EloquentBuilder, Collection as EloquentCollection, Model};
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Facade;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class TinkerCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'builder' => $this->unique([
                ...$this->methods(EloquentBuilder::class, 'builder', static: false),
                ...$this->methods(QueryBuilder::class, 'builder', static: false),
            ]),
            'model' => $this->methods(Model::class, 'model', static: true),
            'instance' => $this->methods(Model::class, 'model', static: false),
            'collection' => $this->methods(EloquentCollection::class, 'collection', static: false),
            'facades' => $this->facades(),
            'helpers' => $this->helpers(),
        ];
    }

    /**
     * @param class-string $class
     * @return list<array{name: string, params: string, returns: string}>
     * @throws ReflectionException
     */
    private function methods(string $class, string $self, bool $static): array
    {
        $methods = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() !== $static || $this->isHidden($method)) {
                continue;
            }

            $methods[] = $this->entry($method, $self);
        }

        return $methods;
    }

    /**
     * @return array<string, list<array{name: string, params: string, returns: string}>>
     * @throws ReflectionException
     */
    private function facades(): array
    {
        $facades = [];
        $aliases = array_merge(Facade::defaultAliases()->all(), (array) config('app.aliases', []));
        ksort($aliases);

        foreach ($aliases as $alias => $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $facades[(string) $alias] = $reflection->isSubclassOf(Facade::class)
                ? $this->documented((string) $reflection->getDocComment())
                : $this->methods($class, 'static', static: true);
        }

        return array_filter($facades);
    }

    /**
     * Reads the `@method static` lines a facade documents, since its root would only resolve by running the app.
     *
     * @return list<array{name: string, params: string, returns: string}>
     */
    private function documented(string $docblock): array
    {
        preg_match_all('/@method\s+static\s+(.+?)\s+(\w+)\((.*)\)\s*$/m', $docblock, $matches, PREG_SET_ORDER);

        return $this->unique(array_map(fn (array $match): array => [
            'name' => $match[2],
            'params' => $this->shorten((string) preg_replace('/\s*=\s*[^,]*(?=,|$)/', ' = …', $match[3])),
            'returns' => $this->kind($match[1], 'static'),
        ], $matches));
    }

    /**
     * @return list<array{name: string, params: string, returns: string}>
     * @throws ReflectionException
     */
    private function helpers(): array
    {
        $helpers = [];

        foreach (get_defined_functions()['user'] as $name) {
            $function = new ReflectionFunction($name);
            $file = str_replace('\\', '/', (string) $function->getFileName());

            if (! str_contains($file, '/laravel/framework/') && ! str_contains($file, '/illuminate/')) {
                continue;
            }

            if (! $this->isHidden($function)) {
                $helpers[] = $this->entry($function, 'static');
            }
        }

        usort($helpers, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $helpers;
    }

    private function isHidden(ReflectionFunctionAbstract $function): bool
    {
        return str_starts_with($function->getName(), '__')
            || preg_match('/@(internal|deprecated)\b/', (string) $function->getDocComment()) === 1;
    }

    /**
     * @return array{name: string, params: string, returns: string}
     */
    private function entry(ReflectionFunctionAbstract $function, string $self): array
    {
        preg_match('/@return\s+(.+)$/m', (string) $function->getDocComment(), $documented);
        $native = $function->getReturnType();

        return [
            'name' => $function->getName(),
            'params' => $this->params($function->getParameters()),
            'returns' => $this->kind($documented[1] ?? ($native === null ? 'mixed' : (string) $native), $self),
        ];
    }

    /**
     * Turns a return type into what the editor chains on: model (`?model` when it may be null), collection,
     * builder, or the short name of anything else.
     */
    public function kind(string $type, string $self): string
    {
        $type = trim($type);

        if (str_starts_with($type, '(') && preg_match('/:\s*([^:]+)\)$/', $type, $conditional)) {
            $type = $conditional[1];
        }

        do {
            $type = (string) preg_replace('/<[^<>]*>/', '', $type, -1, $count);
        } while ($count > 0);

        $parts = array_map(fn (string $part) => ltrim(trim($part), '?\\'), explode('|', $type));
        $nullable = str_starts_with(trim($type), '?') || in_array('null', $parts, true);
        $main = current(array_filter($parts, fn (string $part) => $part !== 'null')) ?: 'mixed';

        $kind = match (true) {
            in_array($main, ['$this', 'static', 'self'], true) => $self === 'static' ? 'mixed' : $self,
            in_array($main, ['TModel', 'TValue'], true) => 'model',
            is_a($main, EloquentBuilder::class, true), is_a($main, QueryBuilder::class, true) => 'builder',
            is_a($main, Enumerable::class, true) => 'collection',
            is_a($main, Model::class, true) => 'model',
            default => class_basename($main),
        };

        return $kind === 'model' && $nullable ? '?model' : $kind;
    }

    /**
     * @param  array<int, ReflectionParameter>  $parameters
     */
    private function params(array $parameters): string
    {
        return implode(', ', array_map(function (ReflectionParameter $parameter): string {
            $type = $parameter->getType();
            $prefix = $type instanceof ReflectionNamedType ? class_basename($type->getName()).' ' : '';

            return $prefix.($parameter->isVariadic() ? '...' : '').'$'.$parameter->getName().($parameter->isOptional() && ! $parameter->isVariadic() ? ' = …' : '');
        }, $parameters));
    }

    private function shorten(string $text): string
    {
        return (string) preg_replace(['/(?:\w+\\\\)+/', '/\\\\(?=\w)/'], '', $text);
    }

    /**
     * @param  list<array{name: string, params: string, returns: string}>  $methods
     * @return list<array{name: string, params: string, returns: string}>
     */
    private function unique(array $methods): array
    {
        $seen = [];

        return array_values(array_filter($methods, function (array $method) use (&$seen): bool {
            if (isset($seen[$method['name']])) {
                return false;
            }

            return $seen[$method['name']] = true;
        }));
    }
}
