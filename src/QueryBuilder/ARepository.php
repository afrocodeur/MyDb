<?php declare(strict_types=1);

namespace MyDB\QueryBuilder;

use Closure;
use MyDB\MyDB;
use Exception;

abstract class ARepository {

    protected string $table;
    protected string $primaryKey = 'id';
    protected string $orderKey = 'id';

    protected array $relations = [];

    protected array $processedRelations = [];

    protected function setRelations(array $requestedRelations): self {
        $this->processedRelations = [];
        foreach($requestedRelations as $relationNam => $relation) {
            if(!isset($this->relations[$relationNam])) {
                throw new Exception("Relation $relationNam not found");
            }
            $this->processedRelations[$relationNam] = $this->relations[$relationNam];
            if(is_callable($relation)) {
                $this->processedRelations[$relationNam]['callback'] = $relation;
            }
        }
        return $this;
    }

    protected function table(): IQueryBuilder {
        return MyDB::table($this->table)->relations($this->processedRelations);
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