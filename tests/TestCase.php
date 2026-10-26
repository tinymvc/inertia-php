<?php

namespace Tests;

use Inertia\Inertia;
use Inertia\InertiaServiceProvider;
use Spark\Foundation\Application;
use Spark\Http\Header;
use Spark\Testing\ApplicationTestCase;

abstract class TestCase extends ApplicationTestCase
{
    protected const INERTIA_HEADERS = [
        Header::X_INERTIA => 'true',
        Header::ACCEPT => 'text/html, application/xhtml+xml',
    ];

    protected function createApplication(): Application
    {
        Inertia::flushShared();

        file_put_contents($this->storagePath . '/app.blade.php', '<!doctype html><html><body>@inertia</body></html>');
        file_put_contents($this->storagePath . '/custom.blade.php', '<main>@inertia("portal")</main>');

        return (new Application($this->storagePath))->withApp(
            config: ['app' => [
                'debug' => false,
                'key' => str_repeat('a', 32),
                'storage_dir' => $this->storagePath,
                'views_dir' => $this->storagePath,
            ]],
            providers: [InertiaServiceProvider::class],
        );
    }

    protected function tearDown(): void
    {
        Inertia::flushShared();
        parent::tearDown();
    }

    protected function pageFromHtml(string $html): array
    {
        $this->assertSame(1, preg_match('/<script data-page="[^"]*" type="application\/json">(.*?)<\/script>/s', $html, $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
