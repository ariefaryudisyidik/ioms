<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    use SessionSupport;

    protected function setUp(): void
    {
        $this->startTestSession();
        View::setViewsPath(dirname(__DIR__, 2) . '/views');
    }

    protected function tearDown(): void
    {
        $this->stopTestSession();
    }

    public function testForbiddenEmitsJsonWith403(): void
    {
        $output = $this->capture(static fn () => Response::forbidden('Nope'));

        $this->assertSame(['error' => 'Nope'], json_decode($output, true));
        $this->assertSame(403, http_response_code());
    }

    public function testUnauthorizedAndNotFoundEmitJson(): void
    {
        $this->assertSame(['error' => 'Unauthorized'], json_decode($this->capture(static fn () => Response::unauthorized()), true));
        $this->assertSame(401, http_response_code());
        $this->assertSame(['error' => 'Not Found'], json_decode($this->capture(static fn () => Response::notFound()), true));
        $this->assertSame(404, http_response_code());
    }

    public function testServerErrorIsJsonForApiPathsAndHtmlOtherwise(): void
    {
        $json = $this->capture(static fn () => Response::serverError('/api/products/X/availability'));
        $this->assertSame(['error' => 'An unexpected error occurred.'], json_decode($json, true));
        $this->assertSame(500, http_response_code());

        $html = $this->capture(static fn () => Response::serverError('/dashboard'));
        $this->assertStringContainsString('<', $html);
        $this->assertStringNotContainsString('"error"', $html);
        $this->assertSame(500, http_response_code());
    }

    public function testRedirectHtmlAndCsvSetStatusCodes(): void
    {
        $this->capture(static fn () => Response::redirect('/x'));
        $this->assertSame(302, http_response_code());

        $this->assertSame('hi', $this->capture(static fn () => Response::html('hi', 201)));
        $this->assertSame(201, http_response_code());

        $this->assertSame('a,b', $this->capture(static fn () => Response::csv('x.csv', 'a,b')));
        $this->assertSame(200, http_response_code());
    }
}
