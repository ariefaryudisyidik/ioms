<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Session;
use App\Entity\User;
use App\Repository\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class SessionHardeningTest extends TestCase
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

    private function reopenSession(): void
    {
        session_write_close();
        Session::start();
    }

    public function testIdleSessionIsClearedAndReceivesANewId(): void
    {
        Session::set('secret', 'value');
        $oldId = session_id();
        $_SESSION['_last_activity'] = time() - 4000;

        $this->reopenSession();

        $this->assertNull(Session::get('secret'));
        $this->assertNotSame($oldId, session_id());
    }

    public function testSessionPastItsAbsoluteLifetimeIsCleared(): void
    {
        Session::set('secret', 'value');
        $_SESSION['_created_at'] = time() - 40000;

        $this->reopenSession();

        $this->assertNull(Session::get('secret'));
    }

    public function testActiveSessionKeepsItsDataAndId(): void
    {
        Session::set('secret', 'value');
        $id = session_id();

        $this->reopenSession();

        $this->assertSame('value', Session::get('secret'));
        $this->assertSame($id, session_id());
    }

    private function loginAs(InMemoryUserRepository $users, string $role = 'Sales'): User
    {
        $user = $users->save(new User(null, 'Sari', 'sari@x.test', 'hash', $role));
        Auth::login(['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role]);

        return $user;
    }

    public function testRefreshIsANoOpForGuests(): void
    {
        Auth::refresh(new InMemoryUserRepository());

        $this->assertFalse(Auth::check());
    }

    public function testRefreshSignsOutDeactivatedAndDeletedUsers(): void
    {
        $users = new InMemoryUserRepository();
        $user = $this->loginAs($users);
        $user->isActive = false;
        $users->save($user);

        Auth::refresh($users);
        $this->assertFalse(Auth::check());

        $this->stopTestSession();
        $this->startTestSession();
        $this->loginAs(new InMemoryUserRepository());
        Auth::refresh(new InMemoryUserRepository());
        $this->assertFalse(Auth::check());
    }

    public function testRefreshAppliesRoleChangesImmediately(): void
    {
        $users = new InMemoryUserRepository();
        $user = $this->loginAs($users, 'Admin');
        $this->assertSame('Admin', Auth::role());

        $user->role = 'WarehouseStaff';
        $user->name = 'Renamed';
        $users->save($user);
        Auth::refresh($users);

        $this->assertSame('WarehouseStaff', Auth::role());
        $this->assertSame('Renamed', Auth::user()['name']);
    }
}
