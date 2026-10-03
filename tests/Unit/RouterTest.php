<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router resolves handler arguments by parameter name, so handlers declare
 * only what they need ($request, $params, both, or neither).
 */
final class RouterTest extends TestCase
{
    private function request(string $method, string $uri, array $body = []): Request
    {
        return new Request([], $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
    }

    public function testHandlerWithoutArgumentsIsInvoked(): void
    {
        $router = new Router();
        $router->get('/ping', [RouterFixtureController::class, 'noArgs']);

        $this->assertSame('pong', $router->dispatch($this->request('GET', '/ping')));
    }

    public function testHandlerReceivesOnlyParamsWhenItDeclaresOnlyParams(): void
    {
        $router = new Router();
        $router->get('/items/{id}', [RouterFixtureController::class, 'paramsOnly']);

        $this->assertSame('id=42', $router->dispatch($this->request('GET', '/items/42')));
    }

    public function testHandlerReceivesOnlyRequestWhenItDeclaresOnlyRequest(): void
    {
        $router = new Router();
        $router->post('/submit', [RouterFixtureController::class, 'requestOnly']);

        $this->assertSame('POST', $router->dispatch($this->request('POST', '/submit')));
    }

    public function testHandlerReceivesRequestAndParamsRegardlessOfDeclarationOrder(): void
    {
        $router = new Router();
        $router->get('/a/{x}/b/{y}', [RouterFixtureController::class, 'both']);

        $this->assertSame('GET:1,2', $router->dispatch($this->request('GET', '/a/1/b/2')));
    }

    public function testClosureHandlerArgumentsAreResolvedByName(): void
    {
        $router = new Router();
        $router->get('/users/{name}', static fn (array $params): string => 'hi ' . $params['name']);

        $this->assertSame('hi sari', $router->dispatch($this->request('GET', '/users/sari')));
    }

    public function testPostWithMethodOverrideDispatchesToDeleteRoute(): void
    {
        $router = new Router();
        $router->delete('/items/{id}', [RouterFixtureController::class, 'paramsOnly']);

        $result = $router->dispatch($this->request('POST', '/items/7', ['_method' => 'delete']));

        $this->assertSame('id=7', $result);
    }

    public function testUnknownRouteUsesNotFoundHandler(): void
    {
        $router = new Router();
        $router->setNotFoundHandler(static fn (Request $request): string => 'missing ' . $request->path());

        $this->assertSame('missing /nope', $router->dispatch($this->request('GET', '/nope')));
    }

    public function testPatchAndAnyRegisterRoutesForTheirMethods(): void
    {
        $router = new Router();
        $router->patch('/patched', [RouterFixtureController::class, 'requestOnly']);
        $router->any(['get', 'put'], '/multi', [RouterFixtureController::class, 'requestOnly']);

        $this->assertSame('PATCH', $router->dispatch($this->request('PATCH', '/patched')));
        $this->assertSame('GET', $router->dispatch($this->request('GET', '/multi')));
        $this->assertSame('PUT', $router->dispatch($this->request('PUT', '/multi')));
    }

    public function testMissingRouteWithoutCustomHandlerRespondsWithJson404(): void
    {
        ob_start();
        $result = (new Router())->dispatch($this->request('GET', '/nowhere'));
        $output = (string) ob_get_clean();
        http_response_code(200);

        $this->assertNull($result);
        $this->assertSame(['error' => 'Not Found'], json_decode($output, true));
    }

    public function testRootRouteMatchesEmptyAndSlashPaths(): void
    {
        $router = new Router();
        $router->get('/', [RouterFixtureController::class, 'noArgs']);

        $this->assertSame('pong', $router->dispatch($this->request('GET', '/')));
    }
}

final class RouterFixtureController
{
    public function noArgs(): string
    {
        return 'pong';
    }

    public function paramsOnly(array $params): string
    {
        return 'id=' . $params['id'];
    }

    public function requestOnly(Request $request): string
    {
        return $request->method();
    }

    public function both(array $params, Request $request): string
    {
        return $request->method() . ':' . $params['x'] . ',' . $params['y'];
    }
}
