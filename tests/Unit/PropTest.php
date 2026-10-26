<?php

namespace Tests\Unit;

use DateInterval;
use DateTimeImmutable;
use Inertia\Contracts\PropsContract;
use Inertia\Inertia;
use Inertia\Props\{AlwaysProp, DeferredProp, MergeProp, OnceProp, OptionalProp};
use Spark\Contracts\Support\Arrayable;
use Spark\Testing\TestCase;

enum OnceName { case Countries; }
enum OnceKey: string { case Countries = 'countries-v2'; }
enum OnceId: int { case Countries = 42; }

function prop_callback(): string { return 'resolved'; }

final class PropCallback
{
    public function __invoke(): string { return 'resolved'; }
    public function value(): string { return 'resolved'; }
    public static function staticValue(): string { return 'resolved'; }
}

final class PropTest extends TestCase
{
    public function test_callable_factories_support_functions_methods_and_invokable_objects(): void
    {
        $object = new PropCallback();
        foreach ([__NAMESPACE__ . '\\prop_callback', [$object, 'value'], [PropCallback::class, 'staticValue'], PropCallback::class . '::staticValue', $object] as $callback) {
            foreach ([Inertia::optional($callback), Inertia::defer($callback), Inertia::once($callback)] as $prop) {
                $this->assertSame('resolved', $prop->resolve());
            }
        }
    }

    public function test_literal_callable_names_remain_data_for_mixed_value_factories(): void
    {
        foreach ([Inertia::always('phpversion'), Inertia::merge('phpversion')] as $prop) {
            $this->assertSame('phpversion', $prop->resolve());
        }
    }

    public function test_literal_values_preserve_their_types(): void
    {
        foreach ([null, false, 0, '', 'hello', ['id' => 1], (object) ['id' => 1]] as $value) {
            $prop = new AlwaysProp($value);
            $this->assertInstanceOf(PropsContract::class, $prop);
            $this->assertSame($value, $prop->resolve());
            $this->assertSame($value, $prop());
        }
    }

    public function test_callbacks_are_lazy_and_base_props_are_not_memoized(): void
    {
        $calls = 0;
        $prop = new AlwaysProp(function () use (&$calls) { return ++$calls; });
        $this->assertSame(0, $calls);
        $this->assertSame(1, $prop->resolve());
        $this->assertSame(2, $prop());
    }

    public function test_props_support_array_and_string_conversion(): void
    {
        $prop = new AlwaysProp(fn () => 'hello');
        $this->assertInstanceOf(Arrayable::class, $prop);
        $this->assertSame('hello', (string) $prop);
        $this->assertSame(['hello'], $prop->toArray());
        $this->assertSame([], (new AlwaysProp(null))->toArray());
        $this->assertSame(['id' => 1], (new AlwaysProp(fn () => ['id' => 1]))->toArray());
    }

    public function test_factories_create_the_expected_prop_types(): void
    {
        foreach ([
            [AlwaysProp::class, Inertia::always(1)],
            [OptionalProp::class, Inertia::optional(fn () => 1)],
            [DeferredProp::class, Inertia::defer(fn () => 1)],
            [OnceProp::class, Inertia::once(fn () => 1)],
            [MergeProp::class, Inertia::merge(1)],
        ] as [$class, $prop]) {
            $this->assertInstanceOf($class, $prop);
            $this->assertSame(1, $prop->resolve());
        }
    }

    public function test_deferred_group_rescue_and_merge_defaults(): void
    {
        $default = Inertia::defer(fn () => []);
        $this->assertSame('default', $default->getGroup());
        $this->assertFalse($default->shouldRescue());
        $this->assertFalse($default->shouldMerge());
        $this->assertFalse($default->shouldResolveOnce());
        $custom = Inertia::defer(fn () => [], 'reports', true);
        $this->assertSame('reports', $custom->getGroup());
        $this->assertTrue($custom->shouldRescue());
        $this->assertSame($custom, $custom->merge());
        $this->assertTrue($custom->shouldMerge());
    }

    public function test_merge_root_direction_can_be_toggled(): void
    {
        $prop = Inertia::merge([]);
        $this->assertTrue($prop->shouldMerge());
        $this->assertTrue($prop->appendsAtRoot());
        $this->assertFalse($prop->prependsAtRoot());
        $this->assertFalse($prop->shouldDeepMerge());
        $this->assertSame($prop, $prop->prepend());
        $this->assertTrue($prop->prependsAtRoot());
        $this->assertFalse($prop->appendsAtRoot());
        $this->assertSame($prop, $prop->prepend(false));
        $this->assertTrue($prop->appendsAtRoot());
        $prop->append(false);
        $this->assertTrue($prop->prependsAtRoot());
        $prop->append();
        $this->assertTrue($prop->appendsAtRoot());
    }

    public function test_merge_nested_paths_and_matching_keys(): void
    {
        $prop = Inertia::merge([])->append(['data' => 'id', 'tags'])->prepend(['news' => 'slug', 'alerts']);
        $this->assertSame(['data', 'tags'], $prop->getAppendPaths());
        $this->assertSame(['news', 'alerts'], $prop->getPrependPaths());
        $this->assertSame(['data.id', 'news.slug'], $prop->getMatchPaths());
        $this->assertFalse($prop->appendsAtRoot());
        $this->assertFalse($prop->prependsAtRoot());
        $this->assertSame($prop, $prop->matchOn(['id', 'id', 'uuid']));
        $this->assertSame(['id', 'uuid'], $prop->getMatchPaths());
        $prop->matchOn('slug');
        $this->assertSame(['slug'], $prop->getMatchPaths());
    }

    public function test_prepend_and_deep_merge_factories(): void
    {
        $this->assertTrue(Inertia::prepend([])->prependsAtRoot());
        $this->assertSame(['id'], Inertia::prepend([], 'id')->getMatchPaths());
        $prop = Inertia::deepMerge([], ['messages.id', 'users.id']);
        $this->assertTrue($prop->shouldMerge());
        $this->assertTrue($prop->shouldDeepMerge());
        $this->assertSame(['messages.id', 'users.id'], $prop->getMatchPaths());
        $this->assertSame([], Inertia::deepMerge([])->getMatchPaths());
        $deferred = Inertia::defer(fn () => [])->deepMerge();
        $this->assertTrue($deferred->shouldMerge());
        $this->assertTrue($deferred->shouldDeepMerge());
    }

    public function test_once_defaults_and_fresh_toggle(): void
    {
        $prop = Inertia::once(fn () => []);
        $this->assertTrue($prop->shouldResolveOnce());
        $this->assertFalse($prop->shouldBeRefreshed());
        $this->assertNull($prop->getKey());
        $this->assertNull($prop->getExpiresAt());
        $this->assertSame($prop, $prop->fresh());
        $this->assertTrue($prop->shouldBeRefreshed());
        $prop->fresh(false)->once(false);
        $this->assertFalse($prop->shouldBeRefreshed());
        $this->assertFalse($prop->shouldResolveOnce());
    }

    public function test_once_composes_with_optional_deferred_and_merge_props(): void
    {
        foreach ([Inertia::optional(fn () => []), Inertia::defer(fn () => []), Inertia::merge([])] as $prop) {
            $this->assertFalse($prop->shouldResolveOnce());
            $this->assertSame($prop, $prop->once(as: 'cache', until: 30));
            $this->assertTrue($prop->shouldResolveOnce());
            $this->assertSame('cache', $prop->getKey());
            $this->assertNotNull($prop->getExpiresAt());
        }
    }

    public function test_once_keys_accept_strings_and_all_enum_types(): void
    {
        foreach (['key' => 'key', 'Countries' => OnceName::Countries, 'countries-v2' => OnceKey::Countries, 42 => OnceId::Countries] as $expected => $key) {
            $prop = Inertia::once(fn () => [])->as($key);
            $this->assertSame((string) $expected, $prop->getKey());
        }
    }

    public function test_once_expiration_accepts_seconds_intervals_and_dates_in_milliseconds(): void
    {
        foreach ([60, new DateInterval('PT1M'), new DateTimeImmutable('+60 seconds')] as $expiration) {
            $before = time();
            $prop = Inertia::once(fn () => [])->until($expiration);
            $this->assertGreaterThanOrEqual(($before + 59) * 1000, $prop->getExpiresAt());
            $this->assertLessThanOrEqual((time() + 61) * 1000, $prop->getExpiresAt());
        }
    }

    public function test_past_date_expiration_is_clamped_to_now(): void
    {
        $before = time() * 1000;
        $prop = Inertia::once(fn () => [])->until(new DateTimeImmutable('-1 day'));
        $this->assertGreaterThanOrEqual($before, $prop->getExpiresAt());
        $this->assertLessThanOrEqual(time() * 1000, $prop->getExpiresAt());
    }
}
