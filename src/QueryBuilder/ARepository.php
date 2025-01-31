<?php declare(strict_types=1);

namespace MyDB\QueryBuilder;

use Closure;
use MyDB\MyDB;

/**
 * @template T
 *
 */
abstract class ARepository {

    protected string $table;
    protected string $primaryKey = 'id';
    protected string $orderKey = 'id';

    /** @var array<string> */
    protected array $with = [];

    protected function table(): IQueryBuilder {
        return MyDB::table($this->table);
    }
    public function getPrimaryKey(): string {
        return $this->primaryKey;
    }
    public function getTableName(): string {
        return $this->table;
    }


    public function all(): array {
        return $this->table()->get();
    }
    public function first(): mixed {
        return $this->table()->first();
    }
    public function last(): ?array {
        return $this->table()->orderBy($this->orderKey ?? $this->primaryKey, 'DESC')->first();
    }

    public function with(string $repository, ?Closure $condition): self {

        return $this;
    }

}