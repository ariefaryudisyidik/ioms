<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Security;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    use SessionSupport;

    protected function setUp(): void
    {
        $this->startTestSession();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $this->stopTestSession();
    }

    public function testSendingHeadersWorksOverPlainHttpAndHttps(): void
    {
        Security::sendHeaders();
        $_SERVER['HTTPS'] = 'on';
        Security::sendHeaders();

        $this->assertTrue(Session::isHttps());
    }

    public function testHttpsDetectionHonoursTheServerFlagAndProxyHeader(): void
    {
        $this->assertFalse(Session::isHttps());
        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(Session::isHttps());
        $_SERVER['HTTPS'] = '1';
        $this->assertTrue(Session::isHttps());
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(Session::isHttps());
    }

    public function testSafeRequestsAndValidTokensPassTheCsrfGate(): void
    {
        $get = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x']);
        $this->assertFalse(Security::rejectForgedRequest($get));

        $valid = new Request([], [Csrf::FIELD => Csrf::token()], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/x']);
        $this->assertFalse(Security::rejectForgedRequest($valid));
    }

    public function testForgedHtmlRequestIsRejectedWith403Page(): void
    {
        $forged = new Request([], ['name' => 'x'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/categories']);
        $halted = null;

        $output = $this->capture(function () use ($forged, &$halted): void {
            $halted = Security::rejectForgedRequest($forged);
        });

        $this->assertTrue($halted);
        $this->assertSame(403, http_response_code());
        $this->assertStringContainsString('403', $output);
    }

    public function testForgedApiRequestIsRejectedWithJson(): void
    {
        $forged = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/products/X/availability']);
        $halted = null;

        $output = $this->capture(function () use ($forged, &$halted): void {
            $halted = Security::rejectForgedRequest($forged);
        });

        $this->assertTrue($halted);
        $this->assertSame(403, http_response_code());
        $this->assertSame('Invalid or missing CSRF token.', json_decode($output, true)['error']);
    }
}
