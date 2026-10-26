<?php

namespace Tests\Support;

use Nyholm\Psr7\ServerRequest;
use Spark\Http\Request;

trait Requests
{
    protected function makeRequest(array $headers = [], string $method = 'GET', string $uri = 'https://example.test/dashboard'): Request
    {
        return Request::fromPsrRequest(new ServerRequest($method, $uri, $headers));
    }
}
