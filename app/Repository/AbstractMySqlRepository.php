<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Shared PDO helpers for the MySQL repositories (prepared statements,
 * filter-to-WHERE mapping, ordered/paginated listing, counting).
 */
abstract class AbstractMySqlRepository
{
    protected const COL_STATUS = 'status';
    protected const COL_WAREHOUSE_ID = 'warehouse_id';
    protected const COL_DATE_FROM = 'date_from';
    protected const COL_DATE_TO = 'date_to';
    protected const SQL_WHERE = ' WHERE ';

    public function __construct(protected PDO $pdo)
    {
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchRow(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @param array<int|string, mixed> $params */
    protected function execute(string $sql, array $params, ?PDO $conn = null): void
    {
        $stmt = ($conn ?? $this->pdo)->prepare($sql);
        $stmt->execute($params);
    }

    /** @param array<int|string, mixed> $params */
    protected function insert(string $sql, array $params, ?PDO $conn = null): int
    {
        $conn ??= $this->pdo;
        $this->execute($sql, $params, $conn);

        return (int) $conn->lastInsertId();
    }

    protected function valueExists(string $table, string $column, string $value): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE {$column} = ?");
        $stmt->execute([$value]);

        return $stmt->fetch() !== false;
    }

    /**
     * @param array<string,array{0:string,1:int,2:string}> $checks field => [table, id, message];
     *        tables are code constants, never user input
     * @return array<string,string> messages for the checks whose row is missing or inactive
     */
    protected function failedReferenceChecks(array $checks): array
    {
        $errors = [];
        foreach ($checks as $field => [$table, $id, $message]) {
            $row = $this->fetchRow("SELECT id FROM {$table} WHERE id = ? AND is_active = 1", [$id]);
            if ($row === null) {
                $errors[$field] = $message;
            }
        }

        return $errors;
    }

    /** @return array<string, int> */
    protected function countsByStatusFor(string $table, ?int $createdBy = null): array
    {
        $where = $createdBy === null ? '' : ' WHERE created_by = ?';
        $counts = [];
        $sql = "SELECT status, COUNT(*) AS cnt FROM {$table}{$where} GROUP BY status";
        foreach ($this->fetchRows($sql, $createdBy === null ? [] : [$createdBy]) as $row) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Build a WHERE clause from filters. Each rule is [filterKey, sqlCondition, cast, valueSuffix];
     * cast "like" binds the (wildcard-escaped) value as %value% to every placeholder of the condition.
     *
     * @param array<string, mixed> $filters
     * @param array<int, array<int, string>> $rules
     * @return array{0: string, 1: array<int, int|string>}
     */
    protected function buildWhere(array $filters, array $rules): array
    {
        $where = [];
        $params = [];
        foreach ($rules as $rule) {
            if (empty($filters[$rule[0]])) {
                continue;
            }
            $where[] = $rule[1];
            array_push($params, ...$this->ruleParams($rule, $filters[$rule[0]]));
        }

        return [$where ? self::SQL_WHERE . implode(' AND ', $where) : '', $params];
    }

    /**
     * @param array<int, string> $rule
     * @return array<int, int|string>
     */
    private function ruleParams(array $rule, mixed $value): array
    {
        if ($rule[2] === 'int') {
            return [(int) $value];
        }
        if ($rule[2] === 'like') {
            return array_fill(0, substr_count($rule[1], '?'), '%' . addcslashes((string) $value, '%_\\') . '%');
        }

        return [(string) $value . ($rule[3] ?? '')];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array<int, string>> $rules
     * @return array<int, array<string, mixed>>
     */
    protected function searchRows(
        string $selectFrom,
        array $filters,
        array $rules,
        string $orderBy,
        int $defaultLimit
    ): array {
        [$where, $params] = $this->buildWhere($filters, $rules);
        $limit = (int) ($filters['limit'] ?? $defaultLimit);
        $offset = (int) ($filters['offset'] ?? 0);

        return $this->fetchRows("{$selectFrom}{$where} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}", $params);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array<int, string>> $rules
     */
    protected function countRows(string $table, array $filters, array $rules): int
    {
        [$where, $params] = $this->buildWhere($filters, $rules);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table}{$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $filters */
    protected function dateOrder(array $filters, string $prefix = ''): string
    {
        $direction = ($filters['sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        return "{$prefix}order_date {$direction}, {$prefix}created_at {$direction}";
    }
}
