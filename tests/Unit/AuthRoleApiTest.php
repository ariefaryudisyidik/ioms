<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use PHPUnit\Framework\TestCase;

final class AuthRoleApiTest extends TestCase
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

    public function testGuestGets401(): void
    {
        $halted = true;
        $this->capture(function () use (&$halted): void {
            $halted = Auth::requireRoleApi('Admin');
        });

        $this->assertTrue($halted);
        $this->assertSame(401, http_response_code());
    }

    public function testWrongRoleGets403AndMatchingRolePasses(): void
    {
        Auth::login(['id' => 7, 'name' => 'Sari', 'email' => 's@x.test', 'role' => 'Sales']);

        $blocked = null;
        $this->capture(function () use (&$blocked): void {
            $blocked = Auth::requireRoleApi('Admin');
        });
        $this->assertTrue($blocked);
        $this->assertSame(403, http_response_code());

        $this->assertFalse(Auth::requireRoleApi('Admin', 'Sales'));
        $this->assertSame(7, Auth::id());
    }
}
