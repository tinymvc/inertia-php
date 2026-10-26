<?php

namespace Tests\Feature;

use Spark\Facades\Route;
use Spark\Http\{Header, Request};
use Tests\TestCase;

final class NavigationTest extends TestCase
{
    public function test_get_and_post_redirects_keep_302_and_vary_header(): void
    {
        Route::any('/redirect', fn () => inertia()->redirect('/destination'));
        $this->get('/redirect', self::INERTIA_HEADERS)->assertRedirect('/destination')->assertHeader(Header::VARY, Header::X_INERTIA);
        $this->post('/redirect', headers: self::INERTIA_HEADERS)->assertRedirect('/destination');
    }

    public function test_put_patch_and_delete_redirects_use_303(): void
    {
        Route::any('/redirect', fn () => inertia()->redirect('/destination'));
        foreach (['put', 'patch', 'delete'] as $method) {
            $this->$method('/redirect', headers: self::INERTIA_HEADERS)->assertRedirect('/destination', 303)->assertHeader(Header::VARY, Header::X_INERTIA);
        }
    }

    public function test_explicit_redirect_status_is_preserved(): void
    {
        Route::put('/redirect', fn () => inertia()->redirect('/destination', 307));
        $this->put('/redirect', headers: self::INERTIA_HEADERS)->assertRedirect('/destination', 307);
    }

    public function test_non_inertia_redirects_use_normal_navigation_even_for_fragments(): void
    {
        Route::get('/redirect', fn () => inertia()->redirect('https://elsewhere.test/path#section'));
        $this->get('/redirect')->assertRedirect('https://elsewhere.test/path#section')->assertHeaderMissing(Header::X_INERTIA_LOCATION)->assertHeaderMissing(Header::X_INERTIA_REDIRECT);
    }

    public function test_external_redirects_use_location_responses(): void
    {
        Route::get('/redirect', fn (Request $request) => inertia()->redirect($request->query('target')));
        foreach (['https://elsewhere.test/path', 'https://localhost/path', 'http://localhost:8080/path'] as $target) {
            $this->get('/redirect?target=' . urlencode($target), self::INERTIA_HEADERS)->assertStatus(409)
                ->assertHeader(Header::X_INERTIA_LOCATION, $target)->assertHeader(Header::VARY, Header::X_INERTIA)
                ->assertHeaderMissing(Header::LOCATION)->assertContent('');
        }
    }

    public function test_same_origin_absolute_urls_use_normal_redirects(): void
    {
        Route::get('/redirect', fn () => inertia()->redirect('http://localhost/destination'));
        $this->get('/redirect', self::INERTIA_HEADERS)->assertRedirect('http://localhost/destination');
    }

    public function test_fragment_redirects_use_the_v3_redirect_header(): void
    {
        Route::get('/redirect', fn () => inertia()->redirect('/destination#section'));
        $this->get('/redirect', self::INERTIA_HEADERS)->assertStatus(409)
            ->assertHeader(Header::X_INERTIA_REDIRECT, '/destination#section')->assertHeader(Header::VARY, Header::X_INERTIA)
            ->assertHeaderMissing(Header::LOCATION)->assertHeaderMissing(Header::X_INERTIA_LOCATION);
    }

    public function test_prefetch_fragment_redirects_remain_normal_redirects(): void
    {
        Route::get('/redirect', fn () => inertia()->redirect('/destination#section'));
        $this->get('/redirect', [...self::INERTIA_HEADERS, Header::PURPOSE => 'PREFETCH'])->assertRedirect('/destination#section')->assertHeaderMissing(Header::X_INERTIA_REDIRECT);
    }

    public function test_location_uses_conflict_for_inertia_visits(): void
    {
        Route::get('/location', fn () => inertia()->location('https://elsewhere.test'));
        $this->get('/location', self::INERTIA_HEADERS)->assertStatus(409)
            ->assertHeader(Header::X_INERTIA_LOCATION, 'https://elsewhere.test')->assertHeader(Header::VARY, Header::X_INERTIA)->assertContent('');
    }

    public function test_location_uses_normal_redirect_for_html_visits(): void
    {
        Route::get('/location', fn () => inertia()->location('https://elsewhere.test'));
        $this->get('/location')->assertRedirect('https://elsewhere.test')->assertHeaderMissing(Header::X_INERTIA_LOCATION);
    }

    public function test_back_uses_referer_and_redirect_status_conversion(): void
    {
        Route::patch('/back', fn () => inertia()->back());
        $this->patch('/back', headers: [...self::INERTIA_HEADERS, Header::REFERER => 'http://localhost/previous'])->assertRedirect('http://localhost/previous', 303);
    }

    public function test_back_defaults_to_root_and_honors_explicit_status(): void
    {
        Route::get('/back', fn () => inertia()->back(301));
        $this->get('/back', self::INERTIA_HEADERS)->assertRedirect('/', 301);
    }

    public function test_version_mismatch_forces_reload_before_evaluating_props(): void
    {
        Route::get('/version', function () {
            inertia()->version('current');
            return inertia('Version', ['expensive' => fn () => throw new \RuntimeException('Must not evaluate on mismatch.')]);
        });
        $this->get('/version?q=1', [...self::INERTIA_HEADERS, Header::X_INERTIA_VERSION => 'old'])->assertStatus(409)
            ->assertHeader(Header::X_INERTIA_LOCATION, 'http://localhost/version?q=1')
            ->assertHeader(Header::X_INERTIA_VERSION, 'current')->assertHeader(Header::VARY, Header::X_INERTIA)->assertContent('');
    }

    public function test_matching_versions_render_normally(): void
    {
        Route::get('/version', function () {
            inertia()->version('current');
            return inertia('Version', ['value' => 1]);
        });
        $this->get('/version', [...self::INERTIA_HEADERS, Header::X_INERTIA_VERSION => 'current'])->assertOk()->assertJsonPath('version', 'current')->assertJsonPath('props.value', 1);
    }

    public function test_non_get_inertia_requests_do_not_force_version_refresh(): void
    {
        Route::post('/version', function () {
            inertia()->version('current');
            return inertia('Version');
        });
        $this->post('/version', headers: [...self::INERTIA_HEADERS, Header::X_INERTIA_VERSION => 'old'])->assertOk()->assertJsonPath('version', 'current');
    }

    public function test_html_visits_ignore_version_mismatch(): void
    {
        Route::get('/version', function () {
            inertia()->version('current');
            return inertia('Version');
        });
        $page = $this->pageFromHtml($this->get('/version', [Header::X_INERTIA_VERSION => 'old'])->assertOk()->content());
        $this->assertSame('current', $page['version']);
    }

    public function test_force_refresh_can_be_requested_explicitly(): void
    {
        Route::get('/refresh', function () {
            inertia()->version(123);
            return inertia()->forceRefresh();
        });
        $this->get('/refresh?q=1')->assertStatus(409)->assertHeader(Header::X_INERTIA_LOCATION, 'http://localhost/refresh?q=1')->assertHeader(Header::X_INERTIA_VERSION, '123');
    }

    public function test_versions_accept_null_integers_strings_and_lazy_callbacks(): void
    {
        $adapter = inertia();
        $this->assertSame('', $adapter->getVersion());
        foreach ([null => null, 42 => 42, 'release' => 'release'] as $expected => $version) {
            $adapter->version($version);
            $this->assertSame((string) $expected, $adapter->getVersion());
        }
        $calls = 0;
        $adapter->version(function () use (&$calls) { return ++$calls; });
        $this->assertSame(0, $calls);
        $this->assertSame('1', $adapter->getVersion());
        $this->assertSame('2', $adapter->getVersion());
    }

    public function test_default_asset_manifest_is_hashed_on_construction(): void
    {
        $manifest = $this->storagePath . '/public/build/.vite/manifest.json';
        mkdir(dirname($manifest), 0700, true);
        file_put_contents($manifest, '{"app.js":{"file":"assets/app-abc.js"}}');
        $this->assertSame(hash_file('xxh128', $manifest), inertia()->getVersion());
    }

    public function test_custom_build_directory_is_trimmed_and_manifest_updates_change_version(): void
    {
        $manifest = $this->storagePath . '/public/assets/.vite/manifest.json';
        mkdir(dirname($manifest), 0700, true);
        file_put_contents($manifest, '{"version":1}');
        $adapter = inertia();
        $adapter->setBuildDirectory('/assets/');
        $first = $adapter->getVersion();
        $this->assertSame(hash_file('xxh128', $manifest), $first);
        file_put_contents($manifest, '{"version":2}');
        $adapter->setBuildDirectory('assets');
        $this->assertNotSame($first, $adapter->getVersion());
        $this->assertSame(hash_file('xxh128', $manifest), $adapter->getVersion());
    }
}
