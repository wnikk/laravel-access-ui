<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The provider must not build the Blade compiler at boot: the compiler throws when the view cache
 * directory is missing, and that would stop every artisan command of an application that has no
 * storage/framework/views yet.
 */
class BootTest extends TestCase
{
    public function test_boot_does_not_resolve_the_blade_compiler(): void
    {
        $this->assertFalse($this->app->resolved('blade.compiler'));
    }

    public function test_directives_are_registered_once_the_compiler_is_resolved(): void
    {
        $this->assertArrayHasKey('accessUiAssets', Blade::getCustomDirectives());
        $this->assertArrayHasKey('accessUiWidget', Blade::getCustomDirectives());
    }
}
