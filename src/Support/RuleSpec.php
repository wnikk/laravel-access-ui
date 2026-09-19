<?php

namespace Wnikk\LaravelAccessUi\Support;

use Closure;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The option spec of a rule, and why it has to be checked.
 *
 * A rule may declare options as a Laravel validation-rule string, for example `nullable|in:read,write`.
 * That string is not documentation: access-rules applies it verbatim to the option value every time a
 * permission on that rule is granted (see the `PermissionOption` cast). So a spec that cannot be
 * parsed does not fail where it was typed — it fails later, on somebody else's screen, as an
 * unknown-rule crash. Rejecting it the moment the rule is saved keeps the failure where the mistake
 * was made.
 *
 * Implemented as a closure rule rather than a rule object so it works the same from Laravel 8 to 12,
 * where the validation-rule interfaces have changed twice.
 */
class RuleSpec
{
    /**
     * Can this string be used as a validation rule at all?
     *
     * The test is whether compiling it throws, not whether it passes. An unknown rule name, or a
     * rule missing its required parameters, throws; a rule that merely fails the sample value does
     * not, and must not be rejected — `in:read,write` is a perfectly good spec that no probe value
     * is guaranteed to satisfy.
     *
     * @param mixed $spec
     * @return bool
     */
    public static function usable($spec): bool
    {
        if ($spec === null || $spec === '') {
            return true;
        }

        if (!is_string($spec)) {
            return false;
        }

        try {
            // A non-null probe, so the rules actually run instead of being skipped as absent.
            Validator::make(['option' => '1'], ['option' => $spec])->passes();
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * A closure validation rule that accepts only usable specs.
     *
     * @return Closure
     */
    public static function validator(): Closure
    {
        return static function ($attribute, $value, $fail) {
            if (!RuleSpec::usable($value)) {
                $fail(__('The :attribute must be a valid Laravel validation-rule string.', [
                    'attribute' => $attribute,
                ]));
            }
        };
    }

    /**
     * Check one option value against the spec of its rule.
     *
     * Returns the first error message, or null when the value is acceptable. A rule without a spec
     * accepts no option at all: access-rules refuses to store one, so saying so here turns a 500
     * into a message the screen can show.
     *
     * @param string|null $spec
     * @param mixed $value
     * @return string|null
     */
    public static function check($spec, $value)
    {
        if ($spec === null || $spec === '') {
            return ($value === null || $value === '')
                ? null
                : __('This rule takes no option value.');
        }

        try {
            $validator = Validator::make(['option' => $value], ['option' => $spec]);

            if ($validator->fails()) {
                return $validator->errors()->first('option');
            }
        } catch (Throwable $e) {
            return __('The option spec of this rule cannot be applied: :message', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
