<?php

declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Master-data CRUD flows that share one controller design:
 * categories, warehouses, suppliers, customers and users.
 */
final class CrudResourcesE2ETest extends E2ETestCase
{
    /**
     * @return array<string,array{0:string,1:string,2:array<string,string>,3:array<string,string>,4:string,5:bool,6:array<int,string>,7:array<int,string>,8:string}>
     */
    public static function resources(): array
    {
        $deactivate = '/deactivate';

        return [
            'categories' => [
                '/categories', 'categories', ['name' => 'E2E Category', 'description' => 'created'],
                ['name' => ''], 'name', true, [self::ADMIN], [self::SALES, self::WAREHOUSE], '/delete',
            ],
            'warehouses' => [
                '/warehouses', 'warehouses', ['name' => 'E2E Warehouse', 'location' => 'Bandung', 'is_active' => '1'],
                ['name' => ''], 'name', true, [self::ADMIN], [self::SALES, self::WAREHOUSE], $deactivate,
            ],
            'suppliers' => [
                '/suppliers', 'suppliers', ['name' => 'E2E Supplier', 'contact' => '0811', 'address' => 'Jl. Uji', 'is_active' => '1'],
                ['name' => ''], 'name', true, [self::ADMIN], [self::SALES, self::WAREHOUSE], $deactivate,
            ],
            'customers' => [
                '/customers', 'customers', ['name' => 'E2E Customer', 'contact' => '0822', 'address' => 'Jl. Uji', 'is_active' => '1'],
                ['name' => ''], 'name', true, [self::ADMIN], [self::SALES, self::WAREHOUSE], $deactivate,
            ],
            'users' => [
                '/users', 'users',
                ['name' => 'E2E User', 'email' => 'e2e.user@ioms.test', 'password' => 'Secret123!', 'role' => 'Sales', 'is_active' => '1'],
                ['name' => '', 'email' => 'not-an-email', 'password' => 'short', 'role' => 'Hacker'],
                'email', false, [self::ADMIN], [self::SALES, self::WAREHOUSE], $deactivate,
            ],
        ];
    }

    #[DataProvider('resources')]
    public function testListAndFormsRenderForAllowedRoles(string $base, string $table, array $create, array $invalid, string $key, bool $readByAll): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $this->assertSame(200, $admin->get($base)->status);
        $this->assertSame(200, $admin->get($base . '/create')->status);
        $this->assertSame(200, $admin->get($base . '/1/edit')->status);
        $this->assertSame(404, $admin->get($base . '/99999/edit')->status);

        foreach ([self::SALES, self::WAREHOUSE] as $email) {
            $this->assertSame($readByAll ? 200 : 403, $this->loginAs($email)->get($base)->status);
        }
    }

    #[DataProvider('resources')]
    public function testCreateStoresRowAndShowsItInTheList(string $base, string $table, array $create, array $invalid, string $key): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $before = (int) $this->value("SELECT COUNT(*) FROM {$table}");

        $response = $admin->post($base, $create);

        $this->assertSame(302, $response->status);
        $this->assertStringEndsWith($base, $response->location);
        $this->assertSame($before + 1, (int) $this->value("SELECT COUNT(*) FROM {$table}"));
        $this->assertStringContainsString(htmlspecialchars($create[$key]), $admin->get($base)->body);
    }

    #[DataProvider('resources')]
    public function testInvalidCreateReturnsToFormAndKeepsErrorsAndOldInput(string $base, string $table, array $create, array $invalid): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $before = (int) $this->value("SELECT COUNT(*) FROM {$table}");

        $response = $admin->post($base, array_merge($create, $invalid));

        $this->assertSame(302, $response->status);
        $this->assertStringEndsWith($base . '/create', $response->location);
        $this->assertSame($before, (int) $this->value("SELECT COUNT(*) FROM {$table}"));

        $form = $admin->get($base . '/create');
        $this->assertSame(200, $form->status);
        $this->assertStringContainsString('field-error', $form->body);
    }

    #[DataProvider('resources')]
    public function testUpdateChangesRowAndInvalidUpdateIsRejected(string $base, string $table, array $create, array $invalid, string $key): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $admin->post($base, $create);
        $id = (int) $this->value("SELECT MAX(id) FROM {$table}");

        $changed = array_merge($create, ['name' => 'E2E Renamed', 'email' => 'e2e.renamed@ioms.test', 'password' => '']);
        $ok = $admin->post($base . '/' . $id, array_merge($changed, ['_method' => 'PUT']));
        $this->assertSame(302, $ok->status);
        $this->assertStringEndsWith($base, $ok->location);
        $this->assertSame('E2E Renamed', $this->value("SELECT name FROM {$table} WHERE id = ?", [$id]));

        $bad = $admin->post($base . '/' . $id, array_merge($changed, $invalid, ['_method' => 'PUT']));
        $this->assertSame(302, $bad->status);
        $this->assertStringEndsWith($base . '/' . $id . '/edit', $bad->location);
        $this->assertStringContainsString('field-error', $admin->get($base . '/' . $id . '/edit')->body);

        $missing = $admin->post($base . '/99999', array_merge($changed, ['_method' => 'PUT']));
        $this->assertSame(302, $missing->status);
    }

    #[DataProvider('resources')]
    public function testRemoveOrDeactivateWorksAndMissingIdIsHandled(string $base, string $table, array $create, array $invalid, string $key, bool $readByAll, array $writers, array $blocked, string $removeSuffix): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $admin->post($base, $create);
        $id = (int) $this->value("SELECT MAX(id) FROM {$table}");

        $response = $admin->post($base . '/' . $id . $removeSuffix);
        $this->assertSame(302, $response->status);

        $missing = $admin->post($base . '/99999' . $removeSuffix);
        $this->assertSame(302, $missing->status);

        if ($removeSuffix === '/delete') {
            $this->assertSame(0, (int) $this->value("SELECT COUNT(*) FROM {$table} WHERE id = ?", [$id]));
        } else {
            $this->assertSame(0, (int) $this->value("SELECT is_active FROM {$table} WHERE id = ?", [$id]));
        }
    }

    #[DataProvider('resources')]
    public function testWritesAreBlockedForUnauthorizedRoles(string $base, string $table, array $create, array $invalid, string $key, bool $readByAll, array $writers, array $blocked, string $removeSuffix): void
    {
        foreach ($blocked as $email) {
            $client = $this->loginAs($email);

            $this->assertSame(403, $client->get($base . '/create')->status, "$email create form");
            $this->assertSame(403, $client->get($base . '/1/edit')->status, "$email edit form");
            $this->assertSame(403, $client->post($base, $create)->status, "$email store");
            $this->assertSame(403, $client->post($base . '/1', array_merge($create, ['_method' => 'PUT']))->status, "$email update");
            $this->assertSame(403, $client->post($base . '/1' . $removeSuffix)->status, "$email remove");
        }

        foreach ($writers as $email) {
            $this->assertSame(200, $this->loginAs($email)->get($base . '/create')->status, "$email create form");
        }
    }

    public function testSalesCanStillReadCustomersForOrderEntry(): void
    {
        $this->assertSame(200, $this->loginAs(self::SALES)->get('/customers')->status);
        $this->assertSame(403, $this->loginAs(self::SALES)->post('/customers/1/deactivate')->status);
        $this->assertSame(302, $this->loginAs(self::ADMIN)->post('/customers/1/deactivate')->status);
    }
}
