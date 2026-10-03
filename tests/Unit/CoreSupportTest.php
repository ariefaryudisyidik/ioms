<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\StockLedgerService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CoreSupportTest extends TestCase
{
    use SessionSupport;

    protected function setUp(): void
    {
        $this->startTestSession();
    }

    protected function tearDown(): void
    {
        Database::reset();
        $this->stopTestSession();
    }

    public function testDatabaseAcceptsAnInjectedConnectionAndCanBeReset(): void
    {
        $pdo = new PDO('sqlite::memory:');

        Database::setConnection($pdo);
        $this->assertSame($pdo, Database::connection());

        Database::reset();
        $reflection = new ReflectionClass(Database::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $reflection->getConstructor()?->invoke($instance);
        $this->assertInstanceOf(Database::class, $instance);
    }

    public function testViewReturnsEmptyStringForMissingTemplate(): void
    {
        View::setViewsPath(dirname(__DIR__, 2) . '/views');
        $log = (string) ini_get('error_log');

        $this->assertSame('', View::render('does.not.exist'));
        $this->assertStringContainsString('Template not found', (string) file_get_contents($log));
    }

    public function testSessionFlashHelpersTrackPresenceAndConsumeOnRead(): void
    {
        $this->assertFalse(Session::hasFlash('success'));

        Session::flash('success', 'Saved');
        $this->assertTrue(Session::hasFlash('success'));
        $this->assertSame('Saved', Session::getFlash('success'));
        $this->assertFalse(Session::hasFlash('success'));
    }

    public function testFlashPartialRendersSuccessErrorAndInfoMessages(): void
    {
        View::setViewsPath(dirname(__DIR__, 2) . '/views');
        Session::flash('success', 'All good');
        Session::flash('error', 'Bad thing');
        Session::flash('info', 'FYI');

        $html = View::render('partials.flash');

        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringContainsString('All good', $html);
        $this->assertStringContainsString('alert-error', $html);
        $this->assertStringContainsString('alert-info', $html);
    }

    public function testUserListShowsEmptyStateWhenThereAreNoUsers(): void
    {
        View::setViewsPath(dirname(__DIR__, 2) . '/views');

        $html = View::render('user.index', ['users' => [], 'auth_user' => ['id' => 1, 'name' => 'A', 'email' => 'a@x.test', 'role' => 'Admin']]);

        $this->assertStringContainsString('empty-state', $html);
    }

    public function testStockLedgerServiceDelegatesSearchToTheRepository(): void
    {
        $repository = $this->createMock(StockLedgerRepositoryInterface::class);
        $repository->expects($this->once())->method('search')->with(['product_id' => 3])->willReturn(['row']);

        $this->assertSame(['row'], (new StockLedgerService($repository))->search(['product_id' => 3]));
    }
}
