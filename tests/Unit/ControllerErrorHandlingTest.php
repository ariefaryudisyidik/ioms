<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controller\Controller;
use App\Core\Session;
use App\Service\Exception\AuthorizationException;
use App\Service\Exception\InsufficientStockException;
use App\Service\Exception\InvalidStatusTransitionException;
use App\Service\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * ERR-01 contract of Controller::handle(): domain exceptions map to
 * 422/403/409, anything else to a generic 500 without leaking details.
 */
final class ControllerErrorHandlingTest extends TestCase
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

    private function failWith(Throwable $failure, bool $isApi): string
    {
        $controller = new class extends Controller {
            public function execute(callable $action, bool $isApi): mixed
            {
                return $this->handle($action, $isApi, '/fallback');
            }
        };

        return $this->capture(static fn () => $controller->execute(static function () use ($failure): void {
            throw $failure;
        }, $isApi));
    }

    /**
     * @return array<string,array{0:Throwable,1:int}>
     */
    public static function apiFailures(): array
    {
        return [
            'validation' => [new ValidationException(['name' => 'Required']), 422],
            'forbidden' => [AuthorizationException::forbidden('Not yours'), 403],
            'invalid transition' => [InvalidStatusTransitionException::make('Draft', 'Fulfilled'), 409],
            'insufficient stock' => [InsufficientStockException::forProduct(1, 5, 2), 409],
            'unexpected' => [new RuntimeException('secret detail'), 500],
        ];
    }

    #[DataProvider('apiFailures')]
    public function testApiFailuresReturnJsonWithTheMappedStatus(Throwable $failure, int $status): void
    {
        $output = $this->failWith($failure, true);

        $this->assertSame($status, http_response_code());
        $this->assertIsArray(json_decode($output, true));
        $this->assertStringNotContainsString('secret detail', $output);
    }

    public function testHtmlValidationFailureStoresErrorsForTheRedirectTarget(): void
    {
        $this->failWith(new ValidationException(['name' => 'Required']), false);

        $this->assertSame(['name' => 'Required'], Session::getErrors());
    }

    public function testHtmlForbiddenAndUnexpectedRenderGenericPages(): void
    {
        $forbidden = $this->failWith(AuthorizationException::forbidden('Not <yours>'), false);
        $this->assertStringContainsString('403 Forbidden', $forbidden);
        $this->assertStringContainsString('Not &lt;yours&gt;', $forbidden);
        $this->assertSame(403, http_response_code());

        $unexpected = $this->failWith(new RuntimeException('secret detail'), false);
        $this->assertStringContainsString('500 Internal Server Error', $unexpected);
        $this->assertStringNotContainsString('secret detail', $unexpected);
        $this->assertSame(500, http_response_code());
    }

    public function testHtmlDomainConflictFlashesTheMessage(): void
    {
        $this->failWith(InvalidStatusTransitionException::make('Draft', 'Fulfilled'), false);

        $this->assertTrue(Session::hasFlash('error'));
        $this->assertStringContainsString('Draft', (string) Session::getFlash('error'));
    }

    public function testSuccessfulActionReturnsItsResult(): void
    {
        $controller = new class extends Controller {
            public function execute(): mixed
            {
                return $this->handle(static fn () => 'done', false);
            }
        };

        $this->assertSame('done', $controller->execute());
    }
}
