<?php declare(strict_types=1);

namespace MyDB\QueryBuilder;

use Closure;
use MyDB\MyDB;
use Exception;
use MyDB\QueryBuilder\Abstract\Plugin\IPlugin;

abstract class ARepository {

    protected string $table;
    protected string $primaryKey = 'id';
    protected string $orderKey = 'id';

    protected array $relations = [];

    protected array $processedRelations = [];


    /**
     * @return array<IPlugin>
     */
    public function localPlugins(): array {
        return [];
    }

    protected function setRelations(array $requestedRelations): self {
        $this->processedRelations = [];
        foreach($requestedRelations as $relationName => $relation) {
            if(!isset($this->relations[$relationName])) {
                throw new Exception("Relation $relationName not found");
            }
            $this->processedRelations[$relationName] = $this->relations[$relationName];
            if($relation instanceof Relation) {
                $this->processedRelations[$relationName] = array_merge($this->processedRelations[$relationName], $relation->getRelationDescription());
                continue;
            }
            if(is_callable($relation)) {
                $this->processedRelations[$relationName]['callback'] = $relation;
                continue;
            }
            if(is_array($relation)) {
                $this->processedRelations[$relationName]['with'] = [];
                foreach($relation as $inlineRelationName => $relationDescription) {
                    if(is_callable($relationDescription) || $inlineRelationName === '$where') {
                        $whereKey = in_array('pivotWhere', array_keys($this->processedRelations[$relationName])) ? 'pivotWhere' : 'where';
                        $this->processedRelations[$relationName][$whereKey] = $relationDescription;
                        continue;
                    }
                    if(is_string($inlineRelationName)) {
                        $this->processedRelations[$relationName]['with'][$inlineRelationName] = $relationDescription;
                        continue;
                    }
                    $this->processedRelations[$relationName]['with'][] = $relationDescription;
                }
            }
        }
        return $this;
    }

    protected function casts(): array {
        return [];
    }

    protected function normalize(): array {
        return $this->casts();
    }

    /**
     * @throws Exception
     */
    public function with(array $relations): self {
        $processedRelations = [];
        foreach ($relations as $key => $item) {
            if(is_string($item)) {
                $processedRelations[$item] = true;
                continue;
            }
            $processedRelations[$key] = $item;
        }
        return $this->setRelations($processedRelations);
    }

    public function table(): IQueryBuilder {
        return MyDB::table($this->table)
            ->relations($this->processedRelations)
            ->normalize($this->normalize())
            ->casts($this->casts())
            ->registerPlugins($this->localPlugins());
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

    public function lastInsertedId(): ?int {
        return MyDB::lastInsertedId();
    }

    /**
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed {
        return MyDB::transaction(fn() => $callback($this));
    }

}