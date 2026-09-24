<?php

declare(strict_types=1);

namespace Wnikk\LaravelAccessUi\Support;

use Wnikk\LaravelAccessRules\Exceptions\AccessRulesException;
use Wnikk\LaravelAccessRules\Exceptions\InvalidConditionException;
use Wnikk\LaravelAccessRules\Exceptions\UntranslatableConditionException;

/**
 * What a refusal of the core becomes on the wire.
 *
 * The core throws one exception class with a numeric code, and the code is the stable part: the
 * next release may reword the message. So the HTTP status and the translation key hang on the
 * code, and the message of the core travels as a second line for the person who reads English.
 */
final class Errors
{
    /**
     * @return array{status:int, code:string, message:string}
     */
    public static function describe(\Throwable $e): array
    {
        if ($e instanceof InvalidConditionException || $e instanceof UntranslatableConditionException) {
            return ['status' => 422, 'code' => 'condition', 'message' => $e->getMessage()];
        }

        if (! $e instanceof AccessRulesException) {
            return ['status' => 500, 'code' => 'unknown', 'message' => $e->getMessage()];
        }

        [$status, $code] = match ($e->getCode()) {
            AccessRulesException::OWNER_NOT_FOUND      => [404, 'owner_not_found'],
            AccessRulesException::RULE_NOT_FOUND       => [404, 'rule_not_found'],
            AccessRulesException::DUPLICATE_PERMISSION => [409, 'duplicate_permission'],
            AccessRulesException::RULE_IN_USE          => [409, 'rule_in_use'],
            AccessRulesException::INHERITANCE_LOOP     => [422, 'inheritance_loop'],
            AccessRulesException::INVALID_OPTION       => [422, 'invalid_option'],
            AccessRulesException::RULE_MANAGED_BY_CODE => [403, 'rule_managed_by_code'],
            default                                    => [422, 'access_rules'],
        };

        return ['status' => $status, 'code' => $code, 'message' => $e->getMessage()];
    }
}
