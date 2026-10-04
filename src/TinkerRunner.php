<?php

namespace EliteDevSquad\SidecarLaravel;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use JsonSerializable;
use Laravel\Tinker\ClassAliasAutoloader;
use Psy\CodeCleaner\NoReturnValue;
use Psy\{Configuration, Shell};
use Psy\VersionUpdater\Checker;
use Stringable;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;
use UnitEnum;

class TinkerRunner
{
    public static function supportsShell(): bool
    {
        return class_exists(Shell::class) && class_exists(ClassAliasAutoloader::class);
    }

    public function run(string $code): string
    {
        try {
            $output = static::supportsShell() ? $this->runInShell($code) : $this->runInCommand($code);
        } catch (Throwable $e) {
            $output = 'Error executing code: '.$e->getMessage();
        }

        return $this->withoutAliasNotices($output);
    }

    private function runInCommand(string $code): string
    {
        Artisan::call('tinker', ['--execute' => $code]);

        return Artisan::output();
    }

    private function runInShell(string $code): string
    {
        $config = new Configuration();
        $config->setUpdateCheck(Checker::NEVER);
        $config->setRawOutput(true);

        $shell = new Shell($config);
        $output = new BufferedOutput();
        $shell->setOutput($output);

        $vendor = Env::get('COMPOSER_VENDOR_DIR');
        $vendor = is_string($vendor) && $vendor !== '' ? $vendor : base_path('vendor');

        /** @var array<int, string> $alias */
        $alias = config('tinker.alias', []);

        /** @var array<int, string> $dontAlias */
        $dontAlias = config('tinker.dont_alias', []);

        $loader = ClassAliasAutoloader::register($shell, $vendor.'/composer/autoload_classmap.php', $alias, $dontAlias);

        try {
            $result = $shell->execute($code, true);
        } finally {
            $loader->unregister();
        }

        $variables = array_diff_key($shell->getScopeVariables(false), $shell->getSpecialScopeVariables(false));

        $lines = array_map(
            fn (string $name, mixed $value): string => '$'.$name.' = '.$this->present($value).';',
            array_keys($variables),
            $variables
        );

        if ($lines === [] && $result !== null && ! $result instanceof NoReturnValue) {
            $lines[] = $this->present($result);
        }

        return trim($output->fetch()."\n".implode("\n", $lines));
    }

    private function present(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value), is_string($value) => var_export($value, true),
            $value instanceof DateTimeInterface => var_export($value->format('Y-m-d H:i:s'), true),
            $value instanceof BackedEnum => $value::class.'::'.$value->name.' ('.var_export($value->value, true).')',
            $value instanceof UnitEnum => $value::class.'::'.$value->name,
            is_array($value), $value instanceof Arrayable => $this->json($value),
            $value instanceof Stringable => var_export((string) $value, true),
            $value instanceof JsonSerializable => $this->json($value),
            is_object($value) => $value::class,
            default => get_debug_type($value),
        };
    }

    private function json(mixed $value): string
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? get_debug_type($value) : $json;
    }

    private function withoutAliasNotices(string $output): string
    {
        $output = preg_replace('/^.*Aliasing .* for this Tinker session\.\s*$/m', '', $output) ?? $output;

        return trim($output);
    }
}
