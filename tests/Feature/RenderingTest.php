<?php

namespace Tests\Feature;

use Inertia\Facades\Inertia as InertiaFacade;
use Inertia\Inertia;
use InvalidArgumentException;
use Spark\Facades\Route;
use Spark\Http\Header;
use Spark\Http\Resources\JsonResource;
use Spark\Http\Response;
use Spark\Support\Collection;
use Tests\Support\PropertyProvider;
use Tests\TestCase;

enum PageName { case Dashboard; }
enum PagePath: string { case Dashboard = 'Admin/Dashboard'; }
enum PageNumber: int { case Dashboard = 1; }

final class RenderingTest extends TestCase
{
    public function test_initial_visit_renders_the_root_view_and_script_page(): void
    {
        Route::get('/dashboard', fn () => inertia('Dashboard', ['user' => ['name' => 'Ada']]));
        $response = $this->get('/dashboard?tab=recent')->assertOk()
            ->assertHeader(Header::VARY, Header::X_INERTIA)
            ->assertHeaderMissing(Header::X_INERTIA)
            ->assertHeader(Header::CONTENT_TYPE, 'text/html; charset=utf-8')
            ->assertSee('<div id="app"></div>');
        $page = $this->pageFromHtml($response->content());
        $this->assertSame('Dashboard', $page['component']);
        $this->assertSame(['errors' => [], 'user' => ['name' => 'Ada']], $page['props']);
        $this->assertSame('/dashboard?tab=recent', $page['url']);
        $this->assertSame('', $page['version']);
        $this->assertSame(['errors'], $page['sharedProps']);
    }

    public function test_inertia_visit_returns_the_complete_json_page_and_protocol_headers(): void
    {
        Route::get('/dashboard', fn () => inertia('Dashboard', ['name' => 'বাংলা / Ada']));
        $this->get('/dashboard?tab=recent', self::INERTIA_HEADERS)->assertOk()
            ->assertHeader(Header::CONTENT_TYPE, 'application/json; charset=utf-8')
            ->assertHeader(Header::X_INERTIA, 'true')->assertHeader(Header::VARY, Header::X_INERTIA)
            ->assertExactJson([
                'component' => 'Dashboard', 'props' => ['errors' => [], 'name' => 'বাংলা / Ada'],
                'url' => '/dashboard?tab=recent', 'version' => '', 'sharedProps' => ['errors'],
            ])->assertSee('বাংলা / Ada')->assertSee('"errors":{}');
    }

    public function test_custom_root_template_and_directive_mount_id(): void
    {
        Route::get('/custom', function () {
            inertia()->setRootView('custom');
            return inertia('Custom');
        });
        $this->get('/custom')->assertOk()->assertSee('<main>')->assertSee('data-page="portal"')->assertSee('<div id="portal"></div>');
    }

    public function test_configured_root_element_id_is_used_by_the_default_directive(): void
    {
        Route::get('/custom', function () {
            inertia()->setRootElementId('dashboard');
            return inertia('Dashboard');
        });
        $this->get('/custom')->assertOk()->assertSee('data-page="dashboard"')->assertSee('<div id="dashboard"></div>');
    }

    public function test_root_element_escapes_ids_and_script_breakout_payloads(): void
    {
        $payload = ['message' => '</script><script>alert("x")</script>&\' বাংলা'];
        $html = (string) inertia()->renderRootElement($payload, 'app" onload="bad');
        $this->assertStringContainsString('data-page="app&quot; onload=&quot;bad"', $html);
        $this->assertStringContainsString('<div id="app&quot; onload=&quot;bad"></div>', $html);
        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertSame($payload, $this->pageFromHtml($html));
    }

    public function test_preencoded_root_payload_is_escaped_without_double_encoding(): void
    {
        $payload = ['message' => '</script>& বাংলা'];
        $html = (string) inertia()->renderRootElement(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->assertSame($payload, $this->pageFromHtml($html));
        $this->assertStringNotContainsString('</script>&', $html);
        $this->assertSame([], $this->pageFromHtml((string) inertia()->renderRootElement()));
    }

    public function test_root_payload_encoding_errors_are_not_silently_swallowed(): void
    {
        $this->assertThrows(\JsonException::class, fn () => inertia()->renderRootElement(['bad' => "\xB1\x31"]));
    }

    public function test_components_accept_unit_and_string_backed_enums(): void
    {
        Route::get('/unit', fn () => inertia(PageName::Dashboard));
        Route::get('/backed', fn () => inertia(PagePath::Dashboard));
        $this->get('/unit', self::INERTIA_HEADERS)->assertJsonPath('component', 'Dashboard');
        $this->get('/backed', self::INERTIA_HEADERS)->assertJsonPath('component', 'Admin/Dashboard');
    }

    public function test_integer_backed_components_are_rejected(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => inertia(PageNumber::Dashboard), 'string-backed enum');
    }

    public function test_arrayable_and_property_provider_page_inputs_are_supported(): void
    {
        Route::get('/arrayable', fn () => inertia('Data', new Collection(['id' => 1])));
        Route::get('/provider', fn () => inertia('Data', new PropertyProvider(fn ($context) => ['component' => $context->component])));
        $this->get('/arrayable', self::INERTIA_HEADERS)->assertJsonPath('props.id', 1);
        $this->get('/provider', self::INERTIA_HEADERS)->assertJsonPath('props.component', 'Data');
    }

    public function test_json_resources_are_resolved_without_response_wrapping_or_private_fields(): void
    {
        Route::get('/resource', fn () => inertia('User', ['user' => new class(['id' => 1, 'secret' => 'hidden']) extends JsonResource {
            public function toArray(?\Spark\Http\Request $request = null): array { return ['id' => $this->resource['id']]; }
        }]));
        $this->get('/resource', self::INERTIA_HEADERS)->assertJsonPath('props.user', ['id' => 1])->assertDontSee('secret')->assertDontSee('hidden');
    }

    public function test_shared_props_and_shared_once_values_are_exposed_with_page_precedence(): void
    {
        Inertia::share('title', 'Shared');
        Inertia::share(new Collection(['locale' => 'bn']));
        Inertia::share(new PropertyProvider(fn () => ['tenant' => 7]));
        Inertia::shareOnce('countries', fn () => ['BD'])->as('countries-v2');
        Route::get('/shared', fn () => inertia('Shared', ['title' => 'Page']));
        $this->get('/shared', self::INERTIA_HEADERS)->assertJsonPath('props.title', 'Page')
            ->assertJsonPath('props.locale', 'bn')->assertJsonPath('props.tenant', 7)
            ->assertJsonPath('props.countries', ['BD'])
            ->assertJsonPath('sharedProps', ['errors', 'title', 'locale', 'tenant', 'countries'])
            ->assertJsonPath('onceProps.countries-v2', ['prop' => 'countries', 'expiresAt' => null]);
    }

    public function test_history_flags_are_omitted_by_default_and_can_be_enabled(): void
    {
        Route::get('/history', function () {
            return inertia()->withEncryptedHistory()->withClearedHistory()->render('History');
        });
        $this->get('/history', self::INERTIA_HEADERS)->assertJsonPath('encryptHistory', true)->assertJsonPath('clearHistory', true);
    }

    public function test_disabled_history_flags_and_empty_flash_are_absent(): void
    {
        Route::get('/history', fn () => inertia()->withEncryptedHistory()->withEncryptedHistory(false)->withClearedHistory()->withClearedHistory(false)->render('History'));
        $page = $this->get('/history', self::INERTIA_HEADERS)->assertOk()->json();
        foreach (['encryptHistory', 'clearHistory', 'preserveFragment', 'flash'] as $key) {
            $this->assertArrayNotHasKey($key, $page);
        }
    }

    public function test_provider_helper_facade_and_instance_resolve_the_same_service(): void
    {
        $adapter = $this->app->get(Inertia::class);
        $this->assertSame($adapter, inertia());
        $this->assertSame($adapter, Inertia::instance());
        $this->assertSame($adapter, InertiaFacade::getFacadeRoot());
        InertiaFacade::version('facade-version');
        $this->assertSame('facade-version', $adapter->getVersion());
        $this->assertInstanceOf(Response::class, InertiaFacade::redirect('/home'));
    }

    public function test_route_macro_renders_component_and_props(): void
    {
        Route::inertia('/about', 'About', ['name' => 'TinyMVC']);
        $this->get('/about', self::INERTIA_HEADERS)->assertOk()->assertJsonPath('component', 'About')->assertJsonPath('props.name', 'TinyMVC');
    }

    public function test_head_returns_page_headers_without_a_body(): void
    {
        Route::inertia('/about', 'About');
        $this->head('/about', self::INERTIA_HEADERS)->assertOk()->assertContent('')->assertHeader(Header::X_INERTIA, 'true');
    }

    public function test_sequential_visits_use_the_current_request_and_representation(): void
    {
        Route::inertia('/first', 'First');
        Route::inertia('/second', 'Second');
        $this->get('/first')->assertHeaderMissing(Header::X_INERTIA);
        $this->get('/second?q=next', self::INERTIA_HEADERS)->assertHeader(Header::X_INERTIA, 'true')->assertJsonPath('url', '/second?q=next');
    }

    public function test_application_configuration_survives_request_rebinding(): void
    {
        $adapter = inertia();
        $adapter->setRootElementId('persistent');
        $adapter->version('v1');
        Route::inertia('/first', 'First');
        Route::inertia('/second', 'Second');
        $this->get('/first')->assertSee('<div id="persistent"></div>');
        $this->get('/second', [...self::INERTIA_HEADERS, Header::X_INERTIA_VERSION => 'v1'])->assertOk()->assertJsonPath('version', 'v1')->assertJsonPath('url', '/second');
        $this->assertSame($adapter, inertia());
    }
}
