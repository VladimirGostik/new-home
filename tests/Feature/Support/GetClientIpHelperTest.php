<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Illuminate\Http\Request;
use Tests\TestCase;

final class GetClientIpHelperTest extends TestCase
{
    public function test_ignores_x_real_ip_header_from_an_untrusted_peer(): void
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_REAL_IP' => '203.0.113.7']);
        $this->app->instance('request', $request);

        $this->assertSame('198.51.100.9', get_client_ip());
    }

    public function test_falls_back_to_request_ip_when_header_missing(): void
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $this->app->instance('request', $request);

        $this->assertSame('10.0.0.1', get_client_ip());
    }

    public function test_uses_x_real_ip_header_when_request_comes_from_a_trusted_proxy(): void
    {
        $originalTrustedProxies = Request::getTrustedProxies();

        try {
            $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_REAL_IP' => '203.0.113.7']);
            Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
            $this->app->instance('request', $request);

            $this->assertSame('203.0.113.7', get_client_ip());
        } finally {
            Request::setTrustedProxies($originalTrustedProxies, Request::HEADER_X_FORWARDED_FOR);
        }
    }
}
