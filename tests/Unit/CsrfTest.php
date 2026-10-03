<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    use SessionSupport;

    protected function setUp(): void
    {
        $this->startTestSession();
    }

    protected function tearDown(): void
    {
        $this->stopTestSession();
    }

    public function testTokenIsRandomHexAndStableWithinASession(): void
    {
        $token = Csrf::token();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Csrf::token());
    }

    #[DataProvider('methods')]
    public function testOnlyStateChangingMethodsRequireTheToken(string $method, bool $required): void
    {
        $this->assertSame($required, Csrf::requiresCheck($method));
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function methods(): array
    {
        return [
            'GET' => ['GET', false], 'head' => ['head', false], 'OPTIONS' => ['OPTIONS', false],
            'POST' => ['POST', true], 'put' => ['put', true], 'DELETE' => ['DELETE', true], 'PATCH' => ['PATCH', true],
        ];
    }

    public function testTokenIsAcceptedFromTheFormFieldOrTheHeaderOnly(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::isValid(new Request([], [Csrf::FIELD => $token], ['REQUEST_METHOD' => 'POST'])));
        $this->assertTrue(Csrf::isValid(new Request([], [], ['HTTP_X_CSRF_TOKEN' => $token])));
        $this->assertFalse(Csrf::isValid(new Request([], [Csrf::FIELD => 'wrong'], [])));
        $this->assertFalse(Csrf::isValid(new Request([], [Csrf::FIELD => ''], [])));
        $this->assertFalse(Csrf::isValid(new Request([], [], [])));
        $this->assertFalse(Csrf::isValid(new Request([], [Csrf::FIELD => [$token]], [])));
        $this->assertFalse(Csrf::isValid(new Request([Csrf::FIELD => $token], [], [])), 'a token in the query string is not accepted');
    }

    public function testInjectAddsTheHiddenFieldToPostFormsOnly(): void
    {
        $token = Csrf::token();
        $html = '<form method="post" action="/a"><button>x</button></form>'
            . "<form action='/b' METHOD='POST'></form>"
            . '<form method="get" action="/c"></form>'
            . '<form action="/d"></form>';

        $result = Csrf::inject($html);

        $this->assertSame(2, substr_count($result, '<input type="hidden" name="_csrf" value="' . $token . '">'));
        $this->assertStringContainsString('<form method="get" action="/c"></form>', $result);
        $this->assertStringContainsString('<form action="/d"></form>', $result);
    }

    public function testInjectLeavesPagesWithoutFormsUntouched(): void
    {
        $this->assertSame('<p>no forms</p>', Csrf::inject('<p>no forms</p>'));
    }
}
