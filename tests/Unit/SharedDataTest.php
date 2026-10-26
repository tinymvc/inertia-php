<?php

namespace Tests\Unit;

use Inertia\Inertia;
use Inertia\Props\OnceProp;
use Spark\Support\Collection;
use Spark\Testing\TestCase;
use Tests\Support\PropertyProvider;

final class SharedDataTest extends TestCase
{
    protected function setUp(): void { Inertia::flushShared(); }
    protected function tearDown(): void { Inertia::flushShared(); }

    public function test_shared_values_merge_by_key_and_support_dot_lookup(): void
    {
        Inertia::share('title', 'First');
        Inertia::share(['title' => 'Second', 'auth' => ['id' => 1, 'name' => null]]);
        Inertia::share(new Collection(['locale' => 'bn']));
        $this->assertSame('Second', Inertia::getShared('title'));
        $this->assertSame(1, Inertia::getShared('auth.id'));
        $this->assertNull(Inertia::getShared('auth.name', 'fallback'));
        $this->assertSame('fallback', Inertia::getShared('missing', 'fallback'));
        $this->assertSame('fallback', Inertia::getShared('title.nested', 'fallback'));
        $this->assertSame('bn', Inertia::getShared()['locale']);
    }

    public function test_shared_callbacks_and_providers_are_not_evaluated_when_registered(): void
    {
        $callback = fn () => throw new \RuntimeException('Must stay lazy.');
        $provider = new PropertyProvider($callback);
        Inertia::share('lazy', $callback);
        Inertia::share($provider);
        $this->assertSame($callback, Inertia::getShared('lazy'));
        $this->assertSame($provider, Inertia::getShared()[0]);
    }

    public function test_share_once_returns_the_same_configurable_prop(): void
    {
        $prop = Inertia::shareOnce('countries', fn () => ['BD'])->as('countries-v2');
        $this->assertInstanceOf(OnceProp::class, $prop);
        $this->assertSame($prop, Inertia::getShared('countries'));
        $this->assertSame(['BD'], $prop->resolve());
        $this->assertSame('countries-v2', $prop->getKey());
    }

    public function test_flushing_shared_data_removes_values_and_providers(): void
    {
        Inertia::share('value', 1);
        Inertia::share(new PropertyProvider(fn () => ['other' => 2]));
        Inertia::flushShared();
        $this->assertSame([], Inertia::getShared());
        $this->assertNull(Inertia::getShared('value'));
    }
}
