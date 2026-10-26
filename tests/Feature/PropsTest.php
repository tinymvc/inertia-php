<?php

namespace Tests\Feature;

use Inertia\Inertia;
use Inertia\Props\ScrollMetadata;
use Spark\Facades\Route;
use Spark\Http\{Header, Request};
use Spark\Paginator\Paginator;
use Tests\TestCase;

final class PropsTest extends TestCase
{
    public function test_initial_page_announces_deferred_props_without_evaluating_them(): void
    {
        Route::get('/dashboard', fn () => inertia('Dashboard', [
            'title' => 'Dashboard',
            'optional' => Inertia::optional(fn () => throw new \RuntimeException('Optional evaluated.')),
            'stats' => Inertia::defer(fn () => throw new \RuntimeException('Deferred evaluated.'), 'stats'),
        ]));
        $this->get('/dashboard', self::INERTIA_HEADERS)->assertJsonPath('props', ['errors' => [], 'title' => 'Dashboard'])->assertJsonPath('deferredProps', ['stats' => ['stats']]);
    }

    public function test_partial_response_selects_optional_and_deferred_values(): void
    {
        Route::get('/dashboard', fn () => inertia('Dashboard', [
            'ignored' => fn () => throw new \RuntimeException('Ignored evaluated.'),
            'optional' => Inertia::optional(fn () => ['id' => 1]),
            'stats' => Inertia::defer(fn () => ['total' => 2]),
            'badge' => Inertia::always(3),
        ]));
        $response = $this->get('/dashboard', [...self::INERTIA_HEADERS, Header::X_INERTIA_PARTIAL_COMPONENT => 'Dashboard', Header::X_INERTIA_PARTIAL_DATA => 'optional,stats']);
        $response->assertJsonPath('props', ['errors' => [], 'optional' => ['id' => 1], 'stats' => ['total' => 2], 'badge' => 3]);
        $this->assertArrayNotHasKey('deferredProps', $response->json());
    }

    public function test_once_client_cache_header_skips_shared_callback_but_keeps_metadata(): void
    {
        Inertia::shareOnce('countries', fn () => throw new \RuntimeException('Cached prop evaluated.'))->as('countries-v2');
        Route::inertia('/once', 'Once');
        $this->get('/once', [...self::INERTIA_HEADERS, Header::X_INERTIA_EXCEPT_ONCE_PROPS => 'countries-v2'])
            ->assertJsonPath('props', ['errors' => []])->assertJsonPath('onceProps.countries-v2', ['prop' => 'countries', 'expiresAt' => null]);
    }

    public function test_merge_reset_is_reflected_in_the_http_page_metadata(): void
    {
        Route::get('/feed', fn () => inertia('Feed', ['items' => Inertia::merge([1])->matchOn('id'), 'news' => Inertia::prepend([2])]));
        $page = $this->get('/feed', [...self::INERTIA_HEADERS, Header::X_INERTIA_RESET => 'items'])->assertJsonPath('props.items', [1])->assertJsonPath('prependProps', ['news'])->json();
        $this->assertArrayNotHasKey('mergeProps', $page);
        $this->assertArrayNotHasKey('matchPropsOn', $page);
    }

    public function test_numbered_paginator_generates_scroll_metadata_and_serializes_data(): void
    {
        Route::get('/feed', fn () => inertia('Feed', ['posts' => Inertia::scroll(Paginator::make(25, 10, 'posts')->setData([['id' => 11]]))]));
        $this->get('/feed?posts=2', self::INERTIA_HEADERS)->assertOk()->assertJsonPath('props.posts.data', [['id' => 11]])
            ->assertJsonPath('mergeProps', ['posts.data'])->assertJsonPath('scrollProps.posts', [
                'pageName' => 'posts', 'previousPage' => 1, 'nextPage' => 3, 'currentPage' => 2, 'reset' => false,
            ]);
    }

    public function test_paginator_metadata_handles_first_last_and_empty_pages(): void
    {
        Route::get('/metadata', function (Request $request) {
            $paginator = Paginator::make((int) $request->query('total', 25), 10, 'posts');
            return json(ScrollMetadata::fromPaginator($paginator)->toArray());
        });
        $this->get('/metadata?posts=1')->assertExactJson(['pageName' => 'posts', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1]);
        $this->get('/metadata?posts=3')->assertExactJson(['pageName' => 'posts', 'previousPage' => 2, 'nextPage' => null, 'currentPage' => 3]);
        $this->get('/metadata?total=0')->assertExactJson(['pageName' => 'posts', 'previousPage' => null, 'nextPage' => null, 'currentPage' => 1]);
        $this->get('/metadata?total=5')->assertExactJson(['pageName' => 'posts', 'previousPage' => null, 'nextPage' => null, 'currentPage' => 1]);
    }

    public function test_scroll_prepend_intent_and_reset_are_honored(): void
    {
        Route::get('/feed', fn () => inertia('Feed', ['posts' => Inertia::scroll(['items' => [1]], 'items', new ScrollMetadata('cursor', null, 'next', 'current'))]));
        $this->get('/feed', [...self::INERTIA_HEADERS, Header::X_INERTIA_INFINITE_SCROLL_MERGE_INTENT => 'prepend'])
            ->assertJsonPath('prependProps', ['posts.items'])->assertJsonPath('scrollProps.posts.currentPage', 'current');
    }

    public function test_null_scroll_metadata_callback_falls_back_to_paginator_metadata(): void
    {
        Route::get('/feed', fn () => inertia('Feed', ['posts' => Inertia::scroll(Paginator::make(5, 10), metadata: fn () => null)]));
        $this->get('/feed', self::INERTIA_HEADERS)->assertJsonPath('scrollProps.posts', [
            'pageName' => 'page', 'previousPage' => null, 'nextPage' => null, 'currentPage' => 1, 'reset' => false,
        ]);
    }
}
