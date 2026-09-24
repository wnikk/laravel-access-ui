<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * What a condition may name, for the autocompletion of the editor.
 *
 * The models come from config access.resources, the columns from the schema, the relations from
 * the return types of public methods, which is the rule of the core: a method counts as a
 * relation when it declares a Relation as its return type, and config may list more by name.
 * The core never calls a method to find out what it returns, and neither does this class.
 *
 * Approximate on purpose. The core compiles the condition and has the last word; a name missing
 * here costs a suggestion, not a wrong write.
 */
final class Vocabulary
{
    public const FUNCTIONS = [
        'time'      => ['now()', 'today()', "ago('7 days')", "after('3 days')", 'monthStart(0)', 'yearStart()'],
        'text'      => ["startsWith(x, 'A')", "endsWith(x, 'A')", "contains(x, 'A')", 'lower(x)'],
        'aggregate' => ['count(x.items)', 'sum(x.items.price)', 'min(x.items.price)', 'max(x.items.price)', 'exists(x.items, filter)'],
        'tree'      => ["below('alias.column', value)", "belowOrSelf('alias.column', value)", "above('alias.column', value)", "aboveOrSelf('alias.column', value)"],
        'author'    => ['isAuthor()'],
    ];

    public const USER = ['user.id', 'user.tenant', 'user.roles', 'user.guest'];

    public const ENV = ['env.now', 'env.today', 'env.time', 'env.hour', 'env.weekday', 'env.ip', 'env.app'];

    /**
     * @return array{resources:array<string, array{model:string, columns:list<string>, relations:array<string, string>}>, user:list<string>, env:list<string>, functions:array<string, list<string>>, custom:array{attributes:list<string>, functions:list<string>}}
     */
    public static function build(): array
    {
        $resources = [];

        foreach ((array) config('access.resources', []) as $alias => $definition) {
            $model  = is_array($definition) ? ($definition['model'] ?? null) : $definition;
            $listed = is_array($definition) ? (array) ($definition['relations'] ?? []) : [];

            if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
                continue;
            }

            $resources[(string) $alias] = [
                'model'     => $model,
                'columns'   => self::columns($model),
                'relations' => self::relations($model, $listed),
            ];
        }

        return [
            'resources' => $resources,
            'user'      => self::USER,
            'env'       => array_merge(self::ENV, array_keys((array) config('access.attributes', []))),
            'functions' => self::FUNCTIONS,
            'custom'    => [
                'attributes' => array_keys((array) config('access.attributes', [])),
                'functions'  => array_map(static fn (string $name): string => $name.'()', array_keys((array) config('access.functions', []))),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function columns(string $model): array
    {
        try {
            $instance = new $model;

            return Schema::connection($instance->getConnectionName())->getColumnListing($instance->getTable());
        } catch (Throwable) {
            // A model whose table is not migrated yet, or a connection that is down: no columns to suggest.
            return [];
        }
    }

    /**
     * @param  list<string>          $listed Relations named in config for methods without a return type.
     * @return array<string, string> Relation name => the model it leads to, or "?" when the type does not say.
     */
    private static function relations(string $model, array $listed): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || $method->getDeclaringClass()->getName() === Model::class) {
                continue;
            }

            $type = $method->getReturnType();
            $name = $method->getName();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), Relation::class, true)) {
                $relations[$name] = class_basename($type->getName());
            } elseif (in_array($name, $listed, true)) {
                $relations[$name] = '?';
            }
        }

        ksort($relations);

        return $relations;
    }
}
