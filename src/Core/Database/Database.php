<?php

declare(strict_types=1);

namespace Northvia\Core\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database connection and query builder
 */
class Database
{
    private PDO $connection;
    private array $config;
    private ?string $table = null;
    private array $wheres = [];
    private array $orders = [];
    private array $joins = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $selects = ['*'];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']} COLLATE {$this->config['collation']}"
        ];

        try {
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        } catch (PDOException $e) {
            throw new DatabaseException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Test database connection
     */
    public function testConnection(): bool
    {
        try {
            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Set table for query
     */
    public function table(string $table): self
    {
        $instance = clone $this;
        $instance->table = $table;
        $instance->reset();
        return $instance;
    }

    /**
     * Select columns
     */
    public function select(array $columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    /**
     * Add where condition
     */
    public function where(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add OR where condition
     */
    public function orWhere(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        return $this;
    }

    /**
     * Add WHERE IN condition
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add WHERE NULL condition
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add ORDER BY
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];

        return $this;
    }

    /**
     * Add LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Add OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Get first record
     */
    public function first(): ?object
    {
        $result = $this->limit(1)->get();
        return $result[0] ?? null;
    }

    /**
     * Get all records
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll();
    }

    /**
     * Get count of records
     */
    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as count'];
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        
        $result = $stmt->fetch();
        $this->selects = $originalSelects;

        return (int) $result->count;
    }

    /**
     * Check if records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Insert record
     */
    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    /**
     * Insert record and get ID
     */
    public function insertGetId(array $data): int
    {
        $this->insert($data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        $sets = [];
        $values = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s', $this->table, implode(', ', $sets));
        
        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause($values);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $sql = sprintf('DELETE FROM %s', $this->table);
        $bindings = [];

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause($bindings);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Execute raw SQL query
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Build SELECT query
     */
    private function buildSelectQuery(): string
    {
        $sql = sprintf('SELECT %s FROM %s', implode(', ', $this->selects), $this->table);

        if (!empty($this->wheres)) {
            $bindings = [];
            $sql .= ' WHERE ' . $this->buildWhereClause($bindings);
        }

        if (!empty($this->orders)) {
            $orderClauses = [];
            foreach ($this->orders as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Build WHERE clause
     */
    private function buildWhereClause(array &$bindings): string
    {
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    $bindings[] = $where['value'];
                    break;

                case 'in':
                    $placeholders = str_repeat('?,', count($where['values']) - 1) . '?';
                    $clauses[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    $bindings = array_merge($bindings, $where['values']);
                    break;

                case 'null':
                    $clauses[] = $boolean . "{$where['column']} IS NULL";
                    break;

                case 'not_null':
                    $clauses[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
            }
        }

        return implode('', $clauses);
    }

    /**
     * Get all bindings for current query
     */
    private function getBindings(): array
    {
        $bindings = [];
        $this->buildWhereClause($bindings);
        return $bindings;
    }

    /**
     * Reset query builder state
     */
    private function reset(): void
    {
        $this->wheres = [];
        $this->orders = [];
        $this->joins = [];
        $this->limit = null;
        $this->offset = null;
        $this->selects = ['*'];
    }

    /**
     * Get database schema information
     */
    public function getTableSchema(string $tableName): array
    {
        $sql = "DESCRIBE {$tableName}";
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get all tables in database
     */
    public function getAllTables(): array
    {
        $sql = "SHOW TABLES";
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Execute multiple SQL statements
     */
    public function executeMultiple(string $sql): bool
    {
        try {
            $this->connection->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new DatabaseException("Failed to execute SQL: " . $e->getMessage());
        }
    }
}

/**
 * Database exception class
 */
class DatabaseException extends \Exception
{
}