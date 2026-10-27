<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * The models of the site and the plugins: every `models/{ClassName}.php` defines a class extending Model (in any
 * namespace). They are loaded when the app is created; a site model wins over a plugin's with the same file name.
 */
class Models
{
    /** @var array<string, class-string<Model>> by model name */
    private static array $classes = [];

    public static function load(App $app): void
    {
        self::$classes = [];

        foreach ($app->siteDirs("models") as $dir) {
            foreach (glob("$dir/*.php") ?: [] as $file) {
                $className = basename($file, ".php");

                if (isset(self::$classes[lcfirst($className)])) {
                    continue;
                }

                // a class that is already declared (e.g. by a plugin's autoloader) isn't loaded again
                if (self::declaredClass($className) === null) {
                    (static fn() => require_once func_get_arg(0))($file);
                }

                self::$classes[lcfirst($className)] = self::declaredClass($className)
                    ?? throw new \LogicException("$file must define the class $className extending " . Model::class);
            }
        }

        ksort(self::$classes);
    }

    /**
     * @return array<string, class-string<Model>>
     */
    public static function all(): array
    {
        return self::$classes;
    }

    /**
     * @return class-string<Model>|null
     */
    public static function get(string $name): ?string
    {
        return self::$classes[$name] ?? null;
    }

    /**
     * The record of a reference, e.g. "pageRating://19qiajkmcaw3x8bp".
     */
    public static function resolve(string $reference): ?Model
    {
        if (!preg_match('#^([\w-]+)://([\w-]+)$#', $reference, $matches) || ($class = self::get($matches[1])) === null) {
            return null;
        }

        return $class::find($matches[2]);
    }

    /**
     * The models with `panel: true` in their blueprint.
     *
     * @return array<string, class-string<Model>>
     */
    public static function inPanel(): array
    {
        return array_filter(self::$classes, fn(string $class) => $class::blueprint()->get("panel") === true);
    }

    private static function declaredClass(string $shortName): ?string
    {
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Model::class) && (new \ReflectionClass($class))->getShortName() === $shortName) {
                return $class;
            }
        }

        return null;
    }
}
