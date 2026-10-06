<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;

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
