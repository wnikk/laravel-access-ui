<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The panel page and the rule that guards every route: without a prefix and a middleware stack
 * in config/accessUi.php nothing is registered at all.
 */
class PanelTest extends TestCase
{
    public function test_the_page_carries_what_the_bundle_needs_to_start(): void
    {
        $response = $this->get('/access-control')->assertOk();

        $response->assertSee('accessUiPanel', false)->assertSee('/vendor/accessui/accessUi.js', false);

        $payload = $this->bootstrap($response->getContent());

        $this->assertSame(['rules', 'owners', 'permissions', 'inherit', 'explain', 'health', 'xacml'], array_keys($payload['screens']));
        $this->assertTrue($payload['screens']['xacml']['enabled'], 'every screen is on until config says otherwise');
        $this->assertSame(['role', 'user'], array_column($payload['entities'], 'key'));
        $this->assertSame([], $payload['problems']);
        $this->assertStringEndsWith('/access-control/owners/__OWNER__/permissions', $payload['routes']['permissions']);
        $this->assertStringEndsWith('/access-control/xacml/export', $payload['routes']['xacmlExport']);
    }

    public function test_the_middleware_of_the_group_guards_every_route(): void
    {
        $this->admin = false;

        $this->get('/access-control')->assertForbidden();
        $this->api('GET', '/rules')->assertForbidden();
        $this->api('GET', '/xacml/export')->assertForbidden();
    }

    public function test_a_screen_that_is_off_hides_and_refuses(): void
    {
        config(['accessUi.screens.health.enabled' => false, 'accessUi.screens.rules.write' => false, 'access.rule_tree_inheritance' => true]);

        $payload = $this->bootstrap($this->get('/access-control')->getContent());
        $this->assertFalse($payload['screens']['health']['enabled']);
        $this->assertFalse($payload['screens']['rules']['write']);
        $this->assertTrue($payload['ruleTree'], 'the matrix says once that a row on a rule covers the rules below it');

        $this->api('GET', '/health')->assertForbidden();
        $this->api('GET', '/rules')->assertOk();
        $this->api('POST', '/rules', ['guard_name' => 'x'])->assertForbidden();
    }

    public function test_an_entity_with_an_unknown_type_is_reported_and_not_fatal(): void
    {
        config(['accessUi.entities.team' => ['type' => 'Nowhere', 'label' => 'Teams']]);

        $payload = $this->bootstrap($this->get('/access-control')->getContent());

        $this->assertArrayHasKey('team', $payload['problems']);
        $this->assertSame(['role', 'user'], array_column($payload['entities'], 'key'));
    }

    public function test_nothing_is_registered_without_a_prefix_and_a_middleware_stack(): void
    {
        $this->assertTrue(Route::has('accessUi.index'));

        // The provider reads config at boot: a fresh application with the middleware missing.
        $this->middleware = [];
        $this->refreshApplication();

        $this->assertFalse(Route::has('accessUi.index'));
        $this->get('/access-control')->assertNotFound();
    }

    /**
     * A page of the application that shows an account gets what the card uses and nothing else:
     * four routes, the entities it may hand out, locale, theme, token. The payload of the panel
     * names every route and every screen, and none of that belongs on a user page.
     */
    public function test_the_card_carries_only_what_it_uses(): void
    {
        $ids  = $this->seedAccess();
        $html = Blade::render("@accessUiWidget(['owner_id' => ".$ids['user'].", 'title' => 'Access', 'write' => false])");

        $this->assertStringContainsString('wacu-widget-root', $html);
        $payload = $this->bootstrap($html);

        $this->assertSame(['locale', 'theme', 'csrfToken', 'picker', 'entities', 'routes', 'owner', 'title', 'compact', 'readOnly', 'write'], array_keys($payload));
        $this->assertSame(['inherit', 'inheritAdd', 'inheritRemove', 'pick'], array_keys($payload['routes']));
        $this->assertSame(['role'], array_column($payload['entities'], 'key'), 'only what may be handed out');
        $this->assertSame([$ids['user'], 'Access', false, true, true], [$payload['owner'], $payload['title'], $payload['compact'], $payload['readOnly'], $payload['write']]);

        config(['accessUi.widget.write' => false]);
        $this->assertFalse($this->bootstrap(Blade::render("@accessUiWidget(['owner_id' => ".$ids['user'].'])'))['write']);
    }

    private function bootstrap(string $html): array
    {
        preg_match('/var options = (\{.*?\});\n/s', $html, $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
