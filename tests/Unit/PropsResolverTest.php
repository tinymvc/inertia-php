<?php

namespace Tests\Unit;

use DateTimeImmutable;
use Inertia\{Inertia, PropertyContext, PropsResolver, RenderContext};
use Inertia\Props\ScrollMetadata;
use RuntimeException;
use Spark\Http\Header;
use Spark\Support\Collection;
use Spark\Testing\TestCase;
use Tests\Support\{ContextProperty, PropertyProvider, Requests};

final class PropsResolverTest extends TestCase
{
    use Requests;

    private function resolve(array $props, array $headers = [], array $shared = []): array
    {
        return (new PropsResolver($this->makeRequest($headers), 'Dashboard'))->resolve($shared, $props);
    }

    private function partial(array $headers = []): array
    {
        return [Header::X_INERTIA => 'true', Header::X_INERTIA_PARTIAL_COMPONENT => 'Dashboard', ...$headers];
    }

    private function unexpected(): never
    {
        throw new RuntimeException('Excluded callback was evaluated.');
    }

    public function test_html_requests_ignore_partial_component_and_selection_headers(): void
    {
        [$props, $meta] = $this->resolve(['a' => 1, 'b' => 2, 'optional' => Inertia::optional($this->unexpected(...))], [
            Header::X_INERTIA_PARTIAL_COMPONENT => 'Dashboard', Header::X_INERTIA_PARTIAL_DATA => 'a',
        ]);
        $this->assertSame(['a' => 1, 'b' => 2], $props);
        $this->assertSame([], $meta);
    }

    public function test_arrayable_containers_preserve_nested_deferred_and_merge_wrappers(): void
    {
        [$props, $meta] = $this->resolve(['data' => new Collection([
            'later' => Inertia::defer($this->unexpected(...)),
            'items' => Inertia::merge([1]),
        ])]);
        $this->assertSame(['data' => ['items' => [1]]], $props);
        $this->assertSame(['default' => ['data.later']], $meta['deferredProps']);
        $this->assertSame(['data.items'], $meta['mergeProps']);
    }

    public function test_json_serializable_containers_preserve_nested_lazy_wrappers(): void
    {
        $value = new class(Inertia::optional($this->unexpected(...))) implements \JsonSerializable {
            public function __construct(private mixed $prop) {}
            public function jsonSerialize(): mixed { return ['optional' => $this->prop, 'value' => 1]; }
        };
        [$props] = $this->resolve(['data' => $value]);
        $this->assertSame(['data' => ['value' => 1]], $props);
    }

    public function test_missing_resource_values_are_omitted_and_lists_remain_lists(): void
    {
        [$props] = $this->resolve(['items' => [1, \Spark\Http\Resources\MissingValue::Missing, 2], 'object' => ['visible' => true, 'hidden' => \Spark\Http\Resources\MissingValue::Missing]]);
        $this->assertSame(['items' => [1, 2], 'object' => ['visible' => true]], $props);
    }

    public function test_empty_optional_only_partial_response_keeps_always_data(): void
    {
        [$props] = $this->resolve(['optional' => Inertia::optional(fn () => []), 'always' => Inertia::always([])], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'optional']));
        $this->assertSame(['optional' => [], 'always' => []], $props);
    }

    public function test_nested_metadata_for_unselected_children_of_resolved_parents_is_omitted(): void
    {
        [$props, $meta] = $this->resolve(['data' => fn () => ['value' => 1, 'merge' => Inertia::merge([1]), 'once' => Inertia::once(fn () => 2)]],
            $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'data.value']));
        $this->assertSame(1, $props['data']['value']);
        $this->assertSame([], $meta);
    }

    public function test_nested_once_props_retain_paths_and_skip_cached_values(): void
    {
        [$props, $meta] = $this->resolve(['auth' => ['roles' => Inertia::once($this->unexpected(...))->as('roles-cache'), 'id' => 1]],
            [Header::X_INERTIA => 'true', Header::X_INERTIA_EXCEPT_ONCE_PROPS => 'roles-cache']);
        $this->assertSame(['auth' => ['id' => 1]], $props);
        $this->assertSame(['roles-cache' => ['prop' => 'auth.roles', 'expiresAt' => null]], $meta['onceProps']);
    }

    public function test_nested_scroll_props_retain_metadata(): void
    {
        [$props, $meta] = $this->resolve(['nested' => ['feed' => Inertia::scroll(['data' => [1]], metadata: new ScrollMetadata('page'))]]);
        $this->assertSame(['nested' => ['feed' => ['data' => [1]]]], $props);
        $this->assertSame(['nested.feed.data'], $meta['mergeProps']);
        $this->assertArrayHasKey('nested.feed', $meta['scrollProps']);
    }

    public function test_deferred_scroll_initial_metadata_uses_the_wrapper_and_direction(): void
    {
        [$props, $meta] = $this->resolve(['feed' => Inertia::scroll($this->unexpected(...), 'items', new ScrollMetadata('page'))->defer()],
            [Header::X_INERTIA_INFINITE_SCROLL_MERGE_INTENT => 'prepend']);
        $this->assertSame([], $props);
        $this->assertSame(['feed.items'], $meta['prependProps']);
        $this->assertArrayNotHasKey('mergeProps', $meta);
    }

    public function test_rescuable_deferred_props_returned_by_closures_are_omitted_on_failure(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'inertia-rescue-');
        $previous = ini_set('error_log', $log);
        try {
            [$props, $meta] = $this->resolve(['broken' => fn () => Inertia::defer($this->unexpected(...), rescue: true)->merge()->once()],
                $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'broken']));
            $this->assertSame([], $props);
            $this->assertSame(['rescuedProps' => ['broken']], $meta);
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

    public function test_reusing_a_resolver_does_not_leak_previous_page_metadata(): void
    {
        $resolver = new PropsResolver($this->makeRequest(), 'Dashboard');
        $resolver->resolve(['shared' => 1], ['items' => Inertia::merge([1]), 'later' => Inertia::defer($this->unexpected(...)), 'once' => Inertia::once(fn () => 1)]);
        $this->assertSame([['plain' => 2], []], $resolver->resolve([], ['plain' => 2]));
    }

    public function test_empty_props_omit_empty_metadata(): void
    {
        $this->assertSame([[], []], $this->resolve([]));
    }

    public function test_plain_values_and_lazy_values_are_resolved(): void
    {
        [$props, $meta] = $this->resolve(['name' => 'Ada', 'count' => fn () => 3, 'nil' => null, 'false' => false]);
        $this->assertSame(['name' => 'Ada', 'count' => 3, 'nil' => null, 'false' => false], $props);
        $this->assertSame([], $meta);
    }

    public function test_page_props_override_shared_props_and_shared_roots_are_unique(): void
    {
        [$props, $meta] = $this->resolve(['title' => 'Page'], shared: ['title' => 'Shared', 'auth.name' => 'Ada', 'auth.id' => 1]);
        $this->assertSame(['title' => 'Page', 'auth' => ['name' => 'Ada', 'id' => 1]], $props);
        $this->assertSame(['title', 'auth'], $meta['sharedProps']);
    }

    public function test_initial_visits_exclude_optional_and_deferred_callbacks(): void
    {
        foreach ([[], [Header::X_INERTIA => 'true']] as $headers) {
            [$props, $meta] = $this->resolve([
                'visible' => 1,
                'optional' => Inertia::optional($this->unexpected(...)),
                'first' => Inertia::defer($this->unexpected(...)),
                'second' => Inertia::defer($this->unexpected(...), 'reports'),
                'third' => Inertia::defer($this->unexpected(...), 'reports'),
            ], $headers);
            $this->assertSame(['visible' => 1], $props);
            $this->assertSame(['deferredProps' => ['default' => ['first'], 'reports' => ['second', 'third']]], $meta);
        }
    }

    public function test_partial_only_evaluates_selected_props_and_always_props(): void
    {
        [$props] = $this->resolve([
            'selected' => fn () => 1,
            'optional' => Inertia::optional(fn () => 2),
            'deferred' => Inertia::defer(fn () => 3),
            'ignored' => $this->unexpected(...),
            'always' => Inertia::always(fn () => 4),
        ], $this->partial([Header::X_INERTIA_PARTIAL_DATA => ' selected, optional, deferred, , ']));
        $this->assertSame(['selected' => 1, 'optional' => 2, 'deferred' => 3, 'always' => 4], $props);
    }

    public function test_except_wins_over_only_but_cannot_exclude_always_props(): void
    {
        [$props] = $this->resolve(['a' => 1, 'b' => $this->unexpected(...), 'always' => Inertia::always(2)], $this->partial([
            Header::X_INERTIA_PARTIAL_DATA => 'a,b', Header::X_INERTIA_PARTIAL_EXCEPT => 'b,always',
        ]));
        $this->assertSame(['a' => 1, 'always' => 2], $props);
    }

    public function test_partial_except_without_only_filters_selected_paths(): void
    {
        [$props] = $this->resolve(['a' => 1, 'b' => $this->unexpected(...)], $this->partial([Header::X_INERTIA_PARTIAL_EXCEPT => 'b']));
        $this->assertSame(['a' => 1], $props);
    }

    public function test_partial_headers_for_another_component_do_not_filter_props(): void
    {
        [$props, $meta] = $this->resolve(['a' => 1, 'b' => 2, 'later' => Inertia::defer($this->unexpected(...))], [
            Header::X_INERTIA => 'true', Header::X_INERTIA_PARTIAL_COMPONENT => 'Other', Header::X_INERTIA_PARTIAL_DATA => 'a',
        ]);
        $this->assertSame(['a' => 1, 'b' => 2], $props);
        $this->assertSame(['default' => ['later']], $meta['deferredProps']);
    }

    public function test_blank_partial_header_lists_are_ignored(): void
    {
        [$props] = $this->resolve(['a' => 1, 'b' => 2], $this->partial([
            Header::X_INERTIA_PARTIAL_DATA => ' , , ', Header::X_INERTIA_PARTIAL_EXCEPT => ' , ',
        ]));
        $this->assertSame(['a' => 1, 'b' => 2], $props);
    }

    public function test_nested_only_does_not_evaluate_excluded_siblings(): void
    {
        [$props] = $this->resolve(['auth' => ['name' => fn () => 'Ada', 'secret' => $this->unexpected(...)], 'other' => $this->unexpected(...)],
            $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'auth.name']));
        $this->assertSame(['auth' => ['name' => 'Ada']], $props);
    }

    public function test_nested_except_excludes_descendants_without_matching_similar_prefixes(): void
    {
        [$props] = $this->resolve(['auth' => ['name' => 'Ada', 'private' => ['token' => $this->unexpected(...)]], 'author' => 3],
            $this->partial([Header::X_INERTIA_PARTIAL_EXCEPT => 'auth.private']));
        $this->assertSame(['auth' => ['name' => 'Ada'], 'author' => 3], $props);
    }

    public function test_parent_only_includes_all_descendants_but_not_similar_prefixes(): void
    {
        [$props] = $this->resolve(['auth' => ['id' => 1, 'name' => 'Ada'], 'author' => $this->unexpected(...)],
            $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'auth']));
        $this->assertSame(['auth' => ['id' => 1, 'name' => 'Ada']], $props);
    }

    public function test_dot_props_expand_and_merge_into_existing_arrays(): void
    {
        [$props] = $this->resolve(['auth' => ['id' => 1], 'auth.name' => 'Ada', 'auth.settings.theme' => 'dark', 'new.path' => true]);
        $this->assertSame(['auth' => ['id' => 1, 'name' => 'Ada', 'settings' => ['theme' => 'dark']], 'new' => ['path' => true]], $props);
    }

    public function test_dot_props_expand_closure_and_arrayable_parents_and_replace_scalars(): void
    {
        foreach ([fn () => ['id' => 1], new Collection(['id' => 1])] as $parent) {
            [$props] = $this->resolve(['auth' => $parent, 'auth.name' => 'Ada']);
            $this->assertSame(['auth' => ['id' => 1, 'name' => 'Ada']], $props);
        }
        [$props] = $this->resolve(['auth' => false, 'auth.name' => 'Ada']);
        $this->assertSame(['auth' => ['name' => 'Ada']], $props);
    }

    public function test_nested_optional_and_deferred_props_remain_lazy(): void
    {
        foreach ([false, true] as $lazyParent) {
            $nested = ['name' => 'Ada', 'details' => Inertia::optional($this->unexpected(...)), 'stats' => Inertia::defer($this->unexpected(...), 'stats')];
            [$props, $meta] = $this->resolve(['auth' => $lazyParent ? fn () => $nested : $nested]);
            $this->assertSame(['auth' => ['name' => 'Ada']], $props);
            $this->assertSame(['stats' => ['auth.stats']], $meta['deferredProps']);
        }
    }

    public function test_closures_can_return_prop_wrappers_without_losing_metadata(): void
    {
        [$props, $meta] = $this->resolve([
            'later' => fn () => Inertia::defer($this->unexpected(...)),
            'optional' => fn () => Inertia::optional($this->unexpected(...)),
            'items' => fn () => Inertia::merge([1]),
        ]);
        $this->assertSame(['items' => [1]], $props);
        $this->assertSame(['default' => ['later']], $meta['deferredProps']);
        $this->assertSame(['items'], $meta['mergeProps']);
    }

    public function test_property_providers_receive_component_and_request_and_preserve_order(): void
    {
        $request = $this->makeRequest();
        $provider = new PropertyProvider(function (RenderContext $context) use ($request) {
            $this->assertSame('Dashboard', $context->component);
            $this->assertSame($request, $context->request);
            yield 'title' => 'Provided';
            yield 'count' => fn () => 2;
        });
        [$props, $meta] = (new PropsResolver($request, 'Dashboard'))->resolve([$provider], ['title' => 'Page']);
        $this->assertSame(['title' => 'Page', 'count' => 2], $props);
        $this->assertSame(['title', 'count'], $meta['sharedProps']);
        [$props] = (new PropsResolver($request, 'Dashboard'))->resolve([], ['title' => 'Before', $provider]);
        $this->assertSame('Provided', $props['title']);
    }

    public function test_property_providers_can_be_nested(): void
    {
        [$props] = $this->resolve(['nested' => [new PropertyProvider(fn () => ['name' => 'Ada'])]]);
        $this->assertSame(['nested' => ['name' => 'Ada']], $props);
    }

    public function test_context_properties_receive_full_path_siblings_and_request(): void
    {
        $request = $this->makeRequest();
        $property = new ContextProperty(function (PropertyContext $context) use ($request) {
            $this->assertSame('auth.label', $context->key);
            $this->assertSame('Ada', $context->props['name']);
            $this->assertSame($request, $context->request);
            return strtoupper($context->props['name']);
        });
        [$props] = (new PropsResolver($request, 'Dashboard'))->resolve([], ['auth' => ['name' => 'Ada', 'label' => $property]]);
        $this->assertSame(['auth' => ['name' => 'Ada', 'label' => 'ADA']], $props);
    }

    public function test_context_properties_can_return_arrays_and_wrappers(): void
    {
        [$props, $meta] = $this->resolve([
            'items' => new ContextProperty(fn () => Inertia::merge([1, 2])),
            'user' => fn () => new ContextProperty(fn () => ['name' => 'Ada']),
        ]);
        $this->assertSame(['items' => [1, 2], 'user' => ['name' => 'Ada']], $props);
        $this->assertSame(['items'], $meta['mergeProps']);
    }

    public function test_arrayable_json_serializable_and_dates_are_normalized(): void
    {
        $serializable = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed { return ['id' => 2]; }
        };
        [$props] = $this->resolve(['collection' => new Collection(['id' => 1]), 'json' => $serializable, 'date' => new DateTimeImmutable('2025-01-01T12:00:00+00:00')]);
        $this->assertSame(['collection' => ['id' => 1], 'json' => ['id' => 2], 'date' => '2025-01-01T12:00:00+00:00'], $props);
    }

    public function test_merge_metadata_supports_append_prepend_deep_and_nested_paths(): void
    {
        [$props, $meta] = $this->resolve([
            'append' => Inertia::merge([1]),
            'prepend' => Inertia::prepend([2], 'id'),
            'deep' => Inertia::deepMerge(['messages' => []], 'messages.id'),
            'mixed' => Inertia::merge(['data' => [], 'news' => []])->append('data', 'id')->prepend('news', 'slug'),
        ]);
        $this->assertSame([1], $props['append']);
        $this->assertSame(['append', 'mixed.data'], $meta['mergeProps']);
        $this->assertSame(['prepend', 'mixed.news'], $meta['prependProps']);
        $this->assertSame(['deep'], $meta['deepMergeProps']);
        $this->assertSame(['prepend.id', 'deep.messages.id', 'mixed.data.id', 'mixed.news.slug'], $meta['matchPropsOn']);
    }

    public function test_nested_merge_paths_and_duplicate_metadata_are_normalized(): void
    {
        [$props, $meta] = $this->resolve(['nested' => ['items' => Inertia::merge(['data' => [1]])->append(['data', 'data'])->matchOn(['data.id', 'data.id'])]]);
        $this->assertSame(['nested' => ['items' => ['data' => [1]]]], $props);
        $this->assertSame(['nested.items.data'], $meta['mergeProps']);
        $this->assertSame(['nested.items.data.id'], $meta['matchPropsOn']);
    }

    public function test_reset_removes_merge_and_match_metadata_but_keeps_values(): void
    {
        [$props, $meta] = $this->resolve(['items' => Inertia::merge([1])->matchOn('id'), 'deep' => Inertia::deepMerge([])], [Header::X_INERTIA_RESET => ' items, deep, ']);
        $this->assertSame(['items' => [1], 'deep' => []], $props);
        $this->assertSame([], $meta);
    }

    public function test_excluded_partial_props_do_not_contribute_metadata(): void
    {
        [$props, $meta] = $this->resolve(['selected' => 1, 'items' => Inertia::merge($this->unexpected(...)), 'once' => Inertia::once($this->unexpected(...)), 'later' => Inertia::defer($this->unexpected(...))],
            $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'selected']));
        $this->assertSame(['selected' => 1], $props);
        $this->assertSame([], $meta);
    }

    public function test_deferred_merge_and_once_metadata_is_sent_before_resolution(): void
    {
        [$props, $meta] = $this->resolve(['items' => Inertia::defer($this->unexpected(...), 'feed')->merge()->append('data', 'id')->once(as: 'feed-cache')]);
        $this->assertSame([], $props);
        $this->assertSame(['feed' => ['items']], $meta['deferredProps']);
        $this->assertSame(['items.data'], $meta['mergeProps']);
        $this->assertSame(['items.data.id'], $meta['matchPropsOn']);
        $this->assertSame(['feed-cache' => ['prop' => 'items', 'expiresAt' => null]], $meta['onceProps']);
    }

    public function test_successful_deferred_partial_response_has_no_deferred_metadata(): void
    {
        [$props, $meta] = $this->resolve(['items' => Inertia::defer(fn () => [1])->merge()], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'items']));
        $this->assertSame(['items' => [1]], $props);
        $this->assertSame(['mergeProps' => ['items']], $meta);
    }

    public function test_unrescued_callback_errors_propagate(): void
    {
        foreach ([$this->unexpected(...), Inertia::defer($this->unexpected(...)), Inertia::optional($this->unexpected(...))] as $prop) {
            $this->assertThrows(RuntimeException::class, fn () => $this->resolve(['value' => $prop], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'value'])), 'Excluded callback');
        }
    }

    public function test_rescued_deferred_failures_are_logged_and_omitted(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'inertia-rescue-');
        $previous = ini_set('error_log', $log);
        try {
            [$props, $meta] = $this->resolve(['broken' => Inertia::defer($this->unexpected(...), rescue: true), 'healthy' => 1], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'broken,healthy']));
            $this->assertSame(['healthy' => 1], $props);
            $this->assertSame(['rescuedProps' => ['broken']], $meta);
            $this->assertStringContainsString('Inertia rescued deferred prop [broken]', file_get_contents($log));
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

    public function test_successful_rescuable_deferred_values_are_included(): void
    {
        [$props, $meta] = $this->resolve(['value' => Inertia::defer(fn () => null, rescue: true)], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'value']));
        $this->assertSame(['value' => null], $props);
        $this->assertSame([], $meta);
    }

    public function test_once_props_emit_path_key_and_expiry_metadata(): void
    {
        [$props, $meta] = $this->resolve(['countries' => Inertia::once(fn () => ['BD']), 'roles' => Inertia::once(fn () => ['admin'])->as('roles-v2')]);
        $this->assertSame(['countries' => ['BD'], 'roles' => ['admin']], $props);
        $this->assertSame(['countries' => ['prop' => 'countries', 'expiresAt' => null], 'roles-v2' => ['prop' => 'roles', 'expiresAt' => null]], $meta['onceProps']);
    }

    public function test_loaded_once_keys_skip_callbacks_on_inertia_visits(): void
    {
        [$props, $meta] = $this->resolve(['countries' => Inertia::once($this->unexpected(...)), 'roles' => Inertia::once($this->unexpected(...))->as('roles-v2')],
            [Header::X_INERTIA => 'true', Header::X_INERTIA_EXCEPT_ONCE_PROPS => ' countries, roles-v2, ']);
        $this->assertSame([], $props);
        $this->assertCount(2, $meta['onceProps']);
    }

    public function test_html_visits_do_not_skip_once_values_due_to_client_cache_headers(): void
    {
        [$props] = $this->resolve(['value' => Inertia::once(fn () => 1)], [Header::X_INERTIA_EXCEPT_ONCE_PROPS => 'value']);
        $this->assertSame(['value' => 1], $props);
    }

    public function test_fresh_or_explicitly_selected_once_props_are_resolved(): void
    {
        [$props] = $this->resolve(['value' => Inertia::once(fn () => 1)->fresh()], [Header::X_INERTIA => 'true', Header::X_INERTIA_EXCEPT_ONCE_PROPS => 'value']);
        $this->assertSame(['value' => 1], $props);
        [$props] = $this->resolve(['value' => Inertia::once(fn () => 2)], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'value', Header::X_INERTIA_EXCEPT_ONCE_PROPS => 'value']));
        $this->assertSame(['value' => 2], $props);
    }

    public function test_disabled_once_props_emit_no_cache_metadata(): void
    {
        $this->assertSame([['value' => 1], []], $this->resolve(['value' => Inertia::once(fn () => 1)->once(false)]));
    }

    public function test_optional_once_and_loaded_deferred_once_keep_cache_metadata(): void
    {
        [$props, $meta] = $this->resolve(['optional' => Inertia::optional($this->unexpected(...))->once(), 'deferred' => Inertia::defer($this->unexpected(...))->once()],
            [Header::X_INERTIA => 'true', Header::X_INERTIA_EXCEPT_ONCE_PROPS => 'deferred']);
        $this->assertSame([], $props);
        $this->assertArrayNotHasKey('deferredProps', $meta);
        $this->assertSame(['optional', 'deferred'], array_keys($meta['onceProps']));
    }

    public function test_scroll_metadata_and_merge_direction_are_sent(): void
    {
        foreach (['append', 'prepend'] as $intent) {
            [$props, $meta] = $this->resolve(['feed' => Inertia::scroll(['items' => [1]], 'items', new ScrollMetadata('page', 1, 3, 2))],
                [Header::X_INERTIA_INFINITE_SCROLL_MERGE_INTENT => $intent]);
            $this->assertSame(['feed' => ['items' => [1]]], $props);
            $this->assertSame(['feed.items'], $meta[$intent === 'append' ? 'mergeProps' : 'prependProps']);
            $this->assertSame(['pageName' => 'page', 'previousPage' => 1, 'nextPage' => 3, 'currentPage' => 2, 'reset' => false], $meta['scrollProps']['feed']);
        }
    }

    public function test_scroll_reset_omits_merge_metadata_and_sets_reset_flag(): void
    {
        [$props, $meta] = $this->resolve(['feed' => Inertia::scroll(['data' => []], metadata: new ScrollMetadata('page'))], [Header::X_INERTIA_RESET => 'feed']);
        $this->assertSame(['feed' => ['data' => []]], $props);
        $this->assertTrue($meta['scrollProps']['feed']['reset']);
        $this->assertArrayNotHasKey('mergeProps', $meta);
    }

    public function test_deferred_scroll_skips_value_and_metadata_until_requested(): void
    {
        [$props, $meta] = $this->resolve(['feed' => Inertia::scroll($this->unexpected(...), metadata: $this->unexpected(...))->defer('feed')]);
        $this->assertSame([], $props);
        $this->assertSame(['feed' => ['feed']], $meta['deferredProps']);
        $this->assertArrayNotHasKey('scrollProps', $meta);
        [$props, $meta] = $this->resolve(['feed' => Inertia::scroll(['data' => [1]], metadata: new ScrollMetadata('page'))->defer()], $this->partial([Header::X_INERTIA_PARTIAL_DATA => 'feed']));
        $this->assertSame(['feed' => ['data' => [1]]], $props);
        $this->assertArrayHasKey('feed', $meta['scrollProps']);
        $this->assertArrayNotHasKey('deferredProps', $meta);
    }
}
