<?php declare(strict_types=1);

namespace MyDB\QueryBuilder;

use MyDB\MyDB;
use Closure;

interface IQueryBuilder {
    public function useDb(MyDB $db): void;

    public function from(string $table): self;
    /** @param array<string> $columns */
    public function select(array $columns): self;
    public function where(mixed ...$args): self;
    public function orWhere(mixed ...$args): self;
    public function whereNull(string $column): self;
    public function orWhereNull(string $column): self;
    public function whereNotNull(string $column): self;
    public function orWhereNotNull(string $column): self;
    public function whereIn(string $column, array|Closure $subQuery): self;
    public function orWhereIn(string $column, array|Closure $subQuery): self;
    public function whereNotIn(string $column, array|Closure $subQuery): self;
    public function orWhereNotIn(string $column, array|Closure $subQuery): self;
    public function whereBetween(string $column, array|Closure $subQuery): self;
    public function orWhereBetween(string $column, array|Closure $subQuery): self;
    public function getSelectQuery(): string;
    public function getDeleteQuery(): string;
    public function getSqlQuery(): string;

    public function orderBy(string $column, string $direction): self;
    public function groupBy(string $colum, string $direction = 'ASC'): self;
    public function having(Closure $condition): self;
    public function limit(int $take): self;
    public function skip(int $skip): self;
    public function take(int $take): self;


    public function count(): int;
    public function get(): array;
    public function first(): ?array;
    public function delete(): bool;

    public function relations(array $relations): self;
    public function normalize(array $rules): self;
    public function casts(array $rules): self;

    /**
     * @param array<string, int|string|float|bool|null> $data
     */
    public function insert(array $data): bool;
    /**
     * @param array<string, int|string|float|bool|null>[] $data
     */
    public function insertMultiple(array $data): bool;
    /**
     * @param array<string, int|string|float|bool|null> $data
     */
    public function update(array $data): bool;
    public function flush(): void;
}