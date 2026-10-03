<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\User;
use App\Repository\InMemoryLoginAttemptRepository;
use App\Repository\InMemoryUserRepository;
use App\Service\AuthService;
use App\Service\LoginThrottle;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    private InMemoryLoginAttemptRepository $attempts;
    private LoginThrottle $throttle;

    protected function setUp(): void
    {
        $this->attempts = new InMemoryLoginAttemptRepository();
        $this->throttle = new LoginThrottle($this->attempts);
    }

    private function failTimes(string $email, string $ip, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->throttle->recordFailure($email, $ip);
        }
    }

    public function testAccountIsLockedAfterTooManyFailuresFromOneAddress(): void
    {
        $this->failTimes('a@x.test', '10.0.0.1', LoginThrottle::MAX_FAILURES_PER_ACCOUNT - 1);
        $this->assertFalse($this->throttle->isLocked('a@x.test', '10.0.0.1'));

        $this->failTimes('a@x.test', '10.0.0.1', 1);
        $this->assertTrue($this->throttle->isLocked('A@X.test ', '10.0.0.1'), 'email is normalised');
        $this->assertFalse($this->throttle->isLocked('a@x.test', '10.0.0.2'), 'other addresses are unaffected');
        $this->assertFalse($this->throttle->isLocked('b@x.test', '10.0.0.1'), 'other accounts are unaffected');
    }

    public function testAddressIsLockedAfterManyFailuresAcrossAccounts(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_IP; $i++) {
            $this->throttle->recordFailure("user{$i}@x.test", '10.0.0.9');
        }

        $this->assertTrue($this->throttle->isLocked('fresh@x.test', '10.0.0.9'));
    }

    public function testSuccessfulLoginResetsTheCounter(): void
    {
        $this->failTimes('a@x.test', '10.0.0.1', LoginThrottle::MAX_FAILURES_PER_ACCOUNT);
        $this->throttle->reset('a@x.test', '10.0.0.1');

        $this->assertFalse($this->throttle->isLocked('a@x.test', '10.0.0.1'));
    }

    public function testOldFailuresAgeOutOfTheWindowAndArePurged(): void
    {
        $old = time() - LoginThrottle::WINDOW_SECONDS - 60;
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_ACCOUNT; $i++) {
            $this->attempts->recordFailureAt('a@x.test', '10.0.0.1', $old);
        }
        $this->assertFalse($this->throttle->isLocked('a@x.test', '10.0.0.1'));

        $ancient = time() - LoginThrottle::WINDOW_SECONDS * 10;
        $this->attempts->recordFailureAt('a@x.test', '10.0.0.1', $ancient);
        $this->throttle->recordFailure('a@x.test', '10.0.0.1');

        // 5 recent-ish failures + the new one remain; the ancient one was purged.
        $this->assertSame(6, $this->attempts->countRecent('a@x.test', '10.0.0.1', LoginThrottle::WINDOW_SECONDS * 20));
    }

    public function testAuthServiceRejectsUnknownInactiveAndWrongPasswordsButAcceptsTheRightOne(): void
    {
        $users = new InMemoryUserRepository();
        $users->save(new User(null, 'Ann', 'ann@x.test', password_hash('Correct-horse-1', PASSWORD_BCRYPT), 'Admin'));
        $users->save(new User(null, 'Off', 'off@x.test', password_hash('Correct-horse-1', PASSWORD_BCRYPT), 'Sales', false));
        $service = new AuthService($users);

        $this->assertNotNull($service->attempt('ann@x.test', 'Correct-horse-1'));
        $this->assertNull($service->attempt('ann@x.test', 'wrong'));
        $this->assertNull($service->attempt('off@x.test', 'Correct-horse-1'));
        $this->assertNull($service->attempt('ghost@x.test', 'Correct-horse-1'));
    }
}
