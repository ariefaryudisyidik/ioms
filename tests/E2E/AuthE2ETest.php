<?php

declare(strict_types=1);

namespace Tests\E2E;

final class AuthE2ETest extends E2ETestCase
{
    public function testLoginPageIsShownToGuestsAndRootRedirectsToItself(): void
    {
        $client = $this->client();

        $this->assertSame(200, $client->get('/login')->status);
        $this->assertSame(200, $client->get('/')->status);
    }

    public function testValidLoginRedirectsToDashboardAndLoggedInUserSkipsLoginPage(): void
    {
        $client = $this->client();
        $response = $client->post('/login', ['email' => self::ADMIN, 'password' => self::PASSWORD]);

        $this->assertSame(302, $response->status);
        $this->assertStringEndsWith('/dashboard', $response->location);
        $this->assertSame(302, $client->get('/login')->status);
    }

    public function testLoginAcceptsAJsonBody(): void
    {
        $client = $this->client();

        $response = $client->postJson('/login', ['email' => self::ADMIN, 'password' => self::PASSWORD]);

        $this->assertSame(302, $response->status);
        $this->assertStringEndsWith('/dashboard', $response->location);
        $this->assertSame(200, $client->get('/dashboard')->status);
    }

    public function testWrongPasswordReturnsToLoginWithErrorAndKeepsOldEmail(): void
    {
        $client = $this->client();
        $response = $client->post('/login', ['email' => self::ADMIN, 'password' => 'nope-nope']);

        $this->assertSame(302, $response->status);
        $this->assertStringEndsWith('/login', $response->location);

        $page = $client->get('/login');
        $this->assertStringContainsString('Invalid credentials', $page->body);
        $this->assertStringContainsString(self::ADMIN, $page->body);
    }

    public function testUnknownEmailIsRejected(): void
    {
        $response = $this->client()->post('/login', ['email' => 'ghost@ioms.test', 'password' => self::PASSWORD]);

        $this->assertStringEndsWith('/login', $response->location);
    }

    public function testDeactivatedAccountCannotLogIn(): void
    {
        $this->db()->exec("UPDATE users SET is_active = 0 WHERE email = '" . self::SALES . "'");

        $response = $this->client()->post('/login', ['email' => self::SALES, 'password' => self::PASSWORD]);

        $this->assertStringEndsWith('/login', $response->location);
    }

    public function testLogoutViaPostAndGetEndsTheSession(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $this->assertSame(200, $client->get('/dashboard')->status);

        $this->assertStringEndsWith('/login', $client->post('/logout')->location);
        $this->assertSame(302, $client->get('/dashboard')->status);

        $client = $this->loginAs(self::ADMIN);
        $this->assertSame(404, $client->get('/logout')->status, 'logging out must not be possible via GET (CSRF)');
        $this->assertSame(200, $client->get('/dashboard')->status);
    }

    public function testGuestIsRedirectedToLoginFromProtectedPages(): void
    {
        $client = $this->client();

        $paths = [
            '/dashboard', '/products', '/products/1', '/products/create', '/purchase-orders', '/purchase-orders/1',
            '/purchase-orders/create', '/sales-orders', '/sales-orders/1', '/sales-orders/create', '/reports',
            '/categories', '/users', '/customers/create',
        ];
        foreach ($paths as $path) {
            $response = $client->get($path);
            $this->assertSame(302, $response->status, $path);
            $this->assertStringEndsWith('/login', $response->location, $path);
        }
    }

    public function testGuestGetsJsonUnauthorizedFromApi(): void
    {
        $response = $this->client()->get('/api/products/SKU-0001/availability');

        $this->assertSame(401, $response->status);
        $this->assertStringContainsString('application/json', $response->contentType);
    }

    public function testUnknownRoutesReturnHtmlOrJson404(): void
    {
        $client = $this->loginAs(self::ADMIN);

        $html = $client->get('/definitely-not-a-page');
        $this->assertSame(404, $html->status);
        $this->assertStringContainsString('text/html', $html->contentType);

        $json = $client->get('/api/nope');
        $this->assertSame(404, $json->status);
        $this->assertStringContainsString('application/json', $json->contentType);
    }

    public function testForbiddenPageIsRenderedForWrongRole(): void
    {
        $client = $this->loginAs(self::SALES);

        $this->assertSame(403, $client->get('/users')->status);
        $this->assertSame(403, $client->get('/purchase-orders/create')->status);
    }
}
