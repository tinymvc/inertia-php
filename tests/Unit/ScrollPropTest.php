<?php

namespace Tests\Unit;

use Inertia\Inertia;
use Inertia\Props\{ScrollMetadata, ScrollProp};
use InvalidArgumentException;
use Spark\Http\Header;
use Spark\Testing\TestCase;
use Tests\Support\Requests;

final class ScrollPropTest extends TestCase
{
    use Requests;

    private const METADATA = ['pageName' => 'cursor', 'previousPage' => 'before', 'nextPage' => 'after', 'currentPage' => 'here'];

    public function test_metadata_defaults_and_cursor_values(): void
    {
        $this->assertSame(['pageName' => 'page', 'previousPage' => null, 'nextPage' => null, 'currentPage' => null], (new ScrollMetadata('page'))->toArray());
        $metadata = new ScrollMetadata('cursor', 'before', 'after', 'here');
        $this->assertSame(self::METADATA, $metadata->toArray());
        $this->assertSame('cursor', $metadata->getPageName());
        $this->assertSame('before', $metadata->getPreviousPage());
        $this->assertSame('after', $metadata->getNextPage());
        $this->assertSame('here', $metadata->getCurrentPage());
    }

    public function test_scroll_value_is_lazy_and_memoized_even_when_null(): void
    {
        foreach ([null, ['data' => [1]]] as $value) {
            $calls = 0;
            $prop = Inertia::scroll(function () use (&$calls, $value) { $calls++; return $value; }, metadata: new ScrollMetadata('page'));
            $this->assertInstanceOf(ScrollProp::class, $prop);
            $this->assertSame(0, $calls);
            $this->assertSame($value, $prop->resolve());
            $this->assertSame($value, $prop());
            $prop->getMetadata();
            $this->assertSame(1, $calls);
        }
    }

    public function test_metadata_callback_receives_the_original_value_once_resolved(): void
    {
        $value = (object) ['items' => [1]];
        $calls = 0;
        $prop = Inertia::scroll(function () use (&$calls, $value) { $calls++; return $value; }, metadata: function ($received) use ($value) {
            $this->assertSame($value, $received);
            return self::METADATA;
        });
        $this->assertSame(self::METADATA, $prop->getMetadata());
        $this->assertSame($value, $prop->resolve());
        $this->assertSame(1, $calls);
    }

    public function test_metadata_accepts_a_provider_or_callback_returning_a_provider(): void
    {
        $metadata = new ScrollMetadata('cursor', 'before', 'after', 'here');
        foreach ([$metadata, fn () => $metadata] as $provider) {
            $this->assertSame(self::METADATA, Inertia::scroll([], metadata: $provider)->getMetadata());
        }
    }

    public function test_metadata_requires_each_key_but_accepts_null_pages(): void
    {
        foreach (array_keys(self::METADATA) as $missing) {
            $metadata = self::METADATA;
            unset($metadata[$missing]);
            $this->assertThrows(InvalidArgumentException::class, fn () => Inertia::scroll([], metadata: fn () => $metadata)->getMetadata(), "[{$missing}]");
        }
        $metadata = (new ScrollMetadata('page'))->toArray();
        $this->assertSame($metadata, Inertia::scroll([], metadata: fn () => $metadata)->getMetadata());
    }

    public function test_invalid_metadata_callback_results_fail_clearly(): void
    {
        foreach ([false, 'invalid', 42, new \stdClass()] as $value) {
            $this->assertThrows(InvalidArgumentException::class, fn () => Inertia::scroll([], metadata: fn () => $value)->getMetadata(), 'must return an array');
        }
    }

    public function test_non_paginators_require_explicit_metadata(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => ScrollMetadata::fromPaginator([]), 'not a TinyMVC paginator');
        $this->assertThrows(InvalidArgumentException::class, fn () => Inertia::scroll([])->getMetadata(), 'Provide a metadata callback');
    }

    public function test_merge_intent_uses_the_configured_wrapper(): void
    {
        foreach ([null, 'append', 'unknown', 'prepend'] as $intent) {
            $headers = $intent === null ? [] : [Header::X_INERTIA_INFINITE_SCROLL_MERGE_INTENT => $intent];
            $prop = Inertia::scroll([], 'items', new ScrollMetadata('page'));
            $this->assertSame($prop, $prop->configureMergeIntent($this->makeRequest($headers)));
            $this->assertSame($intent === 'prepend' ? [] : ['items'], $prop->getAppendPaths());
            $this->assertSame($intent === 'prepend' ? ['items'] : [], $prop->getPrependPaths());
        }
    }

    public function test_scroll_can_be_deferred_in_default_or_custom_groups(): void
    {
        $prop = Inertia::scroll([]);
        $this->assertFalse($prop->shouldDefer());
        $this->assertSame($prop, $prop->defer());
        $this->assertTrue($prop->shouldDefer());
        $this->assertSame('default', $prop->getGroup());
        $prop->defer('feed');
        $this->assertSame('feed', $prop->getGroup());
    }

    public function test_changing_scroll_merge_intent_replaces_the_previous_wrapper_direction(): void
    {
        $prop = Inertia::scroll([], 'data', new ScrollMetadata('page'));
        $prop->configureMergeIntent($this->makeRequest());
        $prop->configureMergeIntent($this->makeRequest([Header::X_INERTIA_INFINITE_SCROLL_MERGE_INTENT => 'prepend']));
        $prop->configureMergeIntent($this->makeRequest([Header::X_INERTIA_INFINITE_SCROLL_MERGE_INTENT => 'prepend']));
        $this->assertSame([], $prop->getAppendPaths());
        $this->assertSame(['data'], $prop->getPrependPaths());
    }
}
