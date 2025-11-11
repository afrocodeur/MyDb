<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\MySQL;

use Closure;
use MyDB\MyDB;
use MyDB\QueryBuilder\Abstract\AQueryBuilder;
use MyDB\QueryBuilder\IQueryBuilder;

class QueryBuilder extends AQueryBuilder {

    protected function getWhereClause(): ?string {
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

    public function get(): array {
        $rowData = $this->db->get($this->getSelectQuery(), $this->getParams());

        foreach ($this->relations as $key => $relation) {
            $queryBuilder = MyDB::table($relation['table']);
            $localKey = $relation['localKey'] ?? 'id';
            $localValues = array_column($rowData, $localKey);
            $relationQuery = $queryBuilder->whereIn($relation['foreignKey'], $localValues);
            if(isset($relation['callback']) && is_callable($relation['callback'])) {
                $relationQuery = $relation['callback']($relationQuery);
            }
            $elements = $relationQuery->get($relation['relations'] ?? []);

            $elementsByForeignKey = [];

            foreach ($elements as $element) {
                $foreignKeyValue = $element[$relation['foreignKey']];
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

        return $rowData;
    }
    public function delete(): bool {
        return $this->db->execute($this->getDeleteQuery(), $this->getParams());
    }
    public function insert(array $data): bool {
        $columns = array_keys($data);
        $columns = array_map(fn($item) => "`$item`", $columns);
        $paramMarks = array_fill(0, count($data), '?');
        $sqlCode = 'INSERT INTO '.$this->table.'('.implode(',', $columns).') VALUES ('.implode(',', $paramMarks).')';
        $params = array_values($data);
        $this->addParams($params);
        return $this->db->execute($sqlCode, $params);
    }
    public function insertMultiple(array $data): bool {
        $firstItem = $data[0];
        $columns = array_map(fn($item) => "`$item`", array_keys($firstItem));

        $paramMark = '('.implode(',', array_fill(0, count($firstItem), '?')).')';
        $paramMarks = array_fill(0, count($data), $paramMark);
        $sqlCode = 'INSERT INTO '.$this->table.'('.implode(',', $columns).') VALUES '.implode(',', $paramMarks);
        $params = array_merge(...array_map(fn($item) => array_values($item), $data));
        $this->addParams($params);
        return $this->db->execute($sqlCode, $params);
    }
    public function update(array $data): bool {
        $sets = [];
        foreach ($data as $key => $value) {
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
        $this->select(['COUNT(*) as nb_elements']);
        $results = $this->get();
        return $results[0]['nb_elements'];
    }

}