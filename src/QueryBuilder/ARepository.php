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
        foreach($requestedRelations as $relationName => $relation) {
            if(!isset($this->relations[$relationName])) {
                throw new Exception("Relation $relationName not found");
            }
            if($relation instanceof Relation) {
                $this->processedRelations[$relationName] = array_merge($this->processedRelations[$relationName], $relation->getRelationDescription());
                continue;
            }
            $this->processedRelations[$relationName] = $this->relations[$relationName];
            if(is_callable($relation)) {
                $this->processedRelations[$relationName]['callback'] = $relation;
                continue;
            }
            if(is_array($relation)) {
                $this->processedRelations[$relationName]['with'] = [];
                foreach($relation as $inlineRelationName => $relationDescription) {
                    if(is_callable($relationDescription) || $inlineRelationName === '$where') {
                        $this->processedRelations[$inlineRelationName]['callback'] = $relationDescription;
                        continue;
                    }
                    if(is_string($inlineRelationName)) {
                        $this->processedRelations[$inlineRelationName]['with'][$inlineRelationName] = $relationDescription;
                        continue;
                    }
                    $this->processedRelations[$inlineRelationName]['with'][] = $relationDescription;
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
            ->casts($this->casts());
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

}