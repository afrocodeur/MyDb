<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\MySQL;

use Closure;
use MyDB\Migration\Raw;
use MyDB\MyDB;
use MyDB\QueryBuilder\Abstract\AQueryBuilder;
use MyDB\QueryBuilder\ARepository;
use MyDB\QueryBuilder\IQueryBuilder;

class QueryBuilder extends AQueryBuilder {

    protected function getWhereClause(): ?string {
        $this->terminateWhereClause();
        if(!isset($this->where[0])) {
            return null;
        }
        $clauses = [];
        foreach ($this->where as $item) {
            if(is_string($item)) {
                $clauses[] = $item;
                continue;
            }
            if($item instanceof IQueryBuilder) {
                $clauses[] = '('.$item->getSelectQuery().')';
                $this->addParams($item->getParams());
                continue;
            }
            [$column, $operator, $valueSource] = $item;
            $value = $valueSource;
            if($valueSource instanceof IQueryBuilder) {
                $value = '('.$valueSource->getSelectQuery().')';
                $this->addParams($valueSource->getParams());
            }
            else if(is_array($valueSource)) {
                $value = array_fill(0, count($valueSource), '?');
                $value = '('.implode(',', $value).')';
                $this->addParams($valueSource);
            }
            else if($operator !== 'IS') {
                $this->addParams([$valueSource]);
                $value = '?';
            }
            $clauses[] = "$column $operator $value";
        }
        return implode(' ', $clauses);
    }
    public function getGroupByClause(): ?string {
        if(empty($this->groupBy)) {
            return '';
        }
        return implode(',', $this->groupBy);
    }
    protected function getOrderByClause(): ?string {
        if(empty($this->orderBy)) {
            return null;
        }
        $orderBys = [];
        foreach ($this->orderBy as $column => $direction) {
            $orderBys[] = "$column $direction";
        }

        return implode(',', $orderBys);
    }

    private function addConditionClauses(string $sqlCode): string {
        $whereClause = $this->getWhereClause();
        $groupByClause = $this->getGroupByClause();
        $orderByClause = $this->getOrderByClause();

        if($whereClause) {
            $sqlCode .= ' WHERE '.$whereClause;
        }
        if($groupByClause) {
            $sqlCode .= ' GROUP BY '.$groupByClause;
        }
        if($orderByClause) {
            $sqlCode .= ' ORDER BY '.$orderByClause;
        }
        if($this->having) {
            $sqlCode .= ' HAVING '.$this->having->getSelectQuery();
        }

        if($this->limitTake > 0) {
            $sqlCode .= " LIMIT {$this->limitStart}, {$this->limitTake}";
        }
        return $sqlCode;
    }

    public function getSelectQuery(): string {
        $this->cleanParam();
        if(!$this->table) {
            return $this->getWhereClause();
        }
        $columns = implode(', ', $this->columns);

        return $this->addConditionClauses("SELECT {$columns} FROM {$this->table}");
    }
    public function getDeleteQuery(): string {
        return $this->addConditionClauses('DELETE FROM '.$this->table);
    }

    /**
     * @throws \Exception
     */
    public function get(): array {
        $rowData = $this->db->get($this->getSelectQuery(), $this->getParams());
        if(empty($rowData)) {
            return [];
        }

        foreach ($this->relations as $key => $relation) {
            /** @var ARepository $repositoryInstance */
            $repositoryInstance = new $relation['repository'];

            $localKey = $relation['localKey'] ?? 'id';
            $localValues = array_values(array_unique(array_filter(array_column($rowData, $localKey))));

            if (empty($localValues)) {
                foreach ($rowData as &$row) {
                    $row[$key] = (isset($relation['type']) && strtolower($relation['type']) === 'hasone') ? null : [];
                }
                continue;
            }

            $queryBuilder = $repositoryInstance->with($relation['with'] ?? [])->table();
            $foreignKey = $relation['foreignKey'] ?? '';
            $type = strtolower($relation['type'] ?? 'hasmany');

            if ($type === 'belongstomany') {
                $this->handleBelongsToMany($key, $rowData, $localValues, $relation, $queryBuilder, $repositoryInstance);
                continue;
            }
            else if(isset($relation['morph'])) {
                $morph = $relation['morph'];
                $foreignKey = $morph.'_id';
                $queryBuilder->where($morph.'_type', $relation['morphType']);
            }

            $queryBuilder->whereIn($foreignKey, $localValues);
            $this->customWhereCondition($relation, $queryBuilder);

            $elements = $queryBuilder->get();

            $elementsByForeignKey = [];

            foreach ($elements as $element) {
                $foreignKeyValue = $element[$foreignKey];
                if (!isset($elementsByForeignKey[$foreignKeyValue])) {
                    $elementsByForeignKey[$foreignKeyValue] = [];
                }
                $elementsByForeignKey[$foreignKeyValue][] = $element;
            }

            foreach ($rowData as &$row) {
                $localKeyValue = $row[$localKey];

                if(isset($relation['type']) && strtolower($relation['type']) === 'hasone') {
                    $row[$key] = $elementsByForeignKey[$localKeyValue][0] ?? null;
                } else {
                    $row[$key] = $elementsByForeignKey[$localKeyValue] ?? [];
                }
            }
        }

        return array_map(fn($row) => $this->runCasts($row), $rowData);
    }
    private function customWhereCondition(array $relation, IQueryBuilder &$queryBuilder, string $callbackKey = 'callback', string $whereKey = 'where'): void {
        if (isset($relation[$callbackKey]) && is_callable($relation[$callbackKey])) {
            $result = $relation[$callbackKey]($queryBuilder);
            if($result instanceof IQueryBuilder) {
                $queryBuilder = $result;
            }
        }
        if(isset($relation[$whereKey])) {
            if(is_callable($relation[$whereKey])) {
                $relation[$whereKey]($queryBuilder);
            }
            else {
                foreach($relation[$whereKey] as $relationKey => $value) {
                    if(is_array($value)) {
                        $queryBuilder->whereIn($relationKey, $value);
                        continue;
                    }
                    $queryBuilder->where($relationKey, $value);
                }
            }
        }
    }
    private function handleBelongsToMany(string|int $key, array &$rowData, array $localValues, array $relation, IQueryBuilder $queryBuilder, ARepository $repositoryInstance): void {

        $pivotTable      = $relation['pivotTable'];
        $localKey        = $relation['localKey'] ?? 'id';
        $foreignPivotKey = $relation['foreignPivotKey'] ?? $relation['foreignKey'];
        $relatedPivotKey = $relation['relatedPivotKey'];
        $relatedKey      = $relation['relatedKey'] ?? $repositoryInstance->getPrimaryKey() ?? 'id';
        $pivotColumns    = $relation['pivotColumns'] ?? [];

        // Get the pivot data
        $pivotQuery = $this->db->queryBuilder()->from($pivotTable)
            ->whereIn($foreignPivotKey, $localValues)
            ->select($foreignPivotKey, $relatedPivotKey, $pivotColumns);
        $this->customWhereCondition($relation, $pivotQuery, callbackKey: 'callbackPivot', whereKey: 'wherePivot');

        $pivotRows = $pivotQuery->get();

        if (empty($pivotRows)) {
            foreach ($rowData as &$row) {
                $row[$key] = [];
            }
            return;
        }

        $targetValues = array_values(array_unique(array_filter(array_column($pivotRows, $relatedPivotKey))));
        if (empty($targetValues)) {
            foreach ($rowData as &$row) {
                $row[$key] = [];
            }
            return;
        }

        $queryBuilder->whereIn($relatedKey, $targetValues);
        $this->customWhereCondition($relation, $queryBuilder);

        $elements = $queryBuilder->get();

        $elementsById = [];
        foreach ($elements as $element) {
            $elementsById[$element[$relatedKey]] = $element;
        }

        $elementsByParentKey = [];
        foreach ($pivotRows as $pivotRow) {
            $foreignValue = $pivotRow[$foreignPivotKey];
            $relatedValue = $pivotRow[$relatedPivotKey];

            if (isset($elementsById[$relatedValue])) {
                $item = $elementsById[$relatedValue];

                // Extract custom pivot attributes
                $pivotData = array_diff_key($pivotRow, [
                    $foreignPivotKey => true,
                    $relatedPivotKey => true,
                ]);
                if(isset($relation['merge']) && $relation['merge'] === true) {
                    $item = array_merge($item, $pivotData);
                } else {
                    $item['pivot'] = $pivotData;
                }

                $elementsByParentKey[$foreignValue][] = $item;
            }
        }

        foreach ($rowData as &$row) {
            $lKey = $row[$localKey] ?? null;
            $row[$key] = $elementsByParentKey[$lKey] ?? [];
        }

        unset($row);
    }
    public function delete(): bool {
        return $this->db->execute($this->getDeleteQuery(), $this->getParams());
    }
    public function insert(array $data): bool {
        $columns = array_keys($data);
        $columns = array_map(fn($item) => "`$item`", $columns);
        $paramMarks = array_fill(0, count($data), '?');
        $sqlCode = 'INSERT INTO '.$this->table.'('.implode(',', $columns).') VALUES ('.implode(',', $paramMarks).')';
        $params = array_values($this->runNormalize($data));
        $this->setParams($params);
        return $this->db->execute($sqlCode, $this->getParams());
    }
    public function insertMultiple(array $data): bool {
        $firstItem = $data[0];
        $columns = array_map(fn($item) => "`$item`", array_keys($firstItem));

        $paramMark = '('.implode(',', array_fill(0, count($firstItem), '?')).')';
        $paramMarks = array_fill(0, count($data), $paramMark);
        $sqlCode = 'INSERT INTO '.$this->table.'('.implode(',', $columns).') VALUES '.implode(',', $paramMarks);
        $params = array_merge(...array_map(fn($item) => array_values($this->runNormalize($item)), $data));
        $this->setParams($params);
        return $this->db->execute($sqlCode, $this->getParams());
    }
    public function update(array $data): bool {
        $sets = [];
        $data = $this->runNormalize($data);
        foreach ($data as $key => $value) {
            $key = $this->wrapName($key);
            if(is_callable($value)) {
                $builder = new static();
                $value($builder);
                $sets[] = $key.' = ('.$builder->getSelectQuery().')';
                $this->addParams($builder->getParams());
                continue;
            }
            $sets[] = "$key = ?";
            $this->addParams([$value]);
        }

        return $this->db->execute(
            $this->addConditionClauses('UPDATE '.$this->table.' SET '.implode(', ', $sets)),
            $this->getParams()
        );
    }

    public function count(): int {
        $results = $this->select([Raw::sql('COUNT(*) as nb_elements')])->get();
        return $results[0]['nb_elements'];
    }

}