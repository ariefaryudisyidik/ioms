<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_FILES['avatar']);
    }

    public function testQueryReturnsAllValuesOrASingleKeyWithDefault(): void
    {
        $request = new Request(['a' => '1'], [], []);

        $this->assertSame(['a' => '1'], $request->query());
        $this->assertSame('1', $request->query('a'));
        $this->assertSame('fallback', $request->query('missing', 'fallback'));
    }

    public function testFormBodyIsUsedForInputAndMergedOverQueryInAll(): void
    {
        $request = new Request(['page' => '2', 'name' => 'query'], ['name' => 'body'], ['REQUEST_METHOD' => 'post']);

        $this->assertSame(['name' => 'body'], $request->input());
        $this->assertSame('body', $request->input('name'));
        $this->assertNull($request->input('page'));
        $this->assertSame(['page' => '2', 'name' => 'body'], $request->all());
        $this->assertSame('POST', $request->method());
        $this->assertFalse($request->isJson());
    }

    public function testJsonContentTypeSwitchesInputSourceAndEmptyBodyDecodesToEmptyArray(): void
    {
        $request = new Request([], ['ignored' => 'form'], ['CONTENT_TYPE' => 'Application/JSON; charset=utf-8']);

        $this->assertTrue($request->isJson());
        $this->assertSame([], $request->jsonBody());
        $this->assertSame([], $request->input());
        $this->assertSame('d', $request->input('x', 'd'));
        $this->assertTrue((new Request([], [], ['HTTP_CONTENT_TYPE' => 'application/json']))->isJson());
    }

    public function testServerHeaderPathAndFileAccessors(): void
    {
        $request = new Request([], [], ['REQUEST_URI' => '/items?x=1', 'HTTP_X_TEST_HEADER' => 'v']);

        $this->assertSame('/items', $request->path());
        $this->assertSame('/', (new Request())->path());
        $this->assertSame('GET', (new Request())->method());
        $this->assertSame('/items?x=1', $request->server('REQUEST_URI'));
        $this->assertSame('dflt', $request->server('NOPE', 'dflt'));
        $this->assertArrayHasKey('HTTP_X_TEST_HEADER', $request->server());
        $this->assertSame('v', $request->header('X-Test-Header'));
        $this->assertNull($request->header('X-Missing'));

        $this->assertNull($request->file('avatar'));
        $_FILES['avatar'] = ['name' => 'a.png'];
        $this->assertSame(['name' => 'a.png'], $request->file('avatar'));
    }
}
