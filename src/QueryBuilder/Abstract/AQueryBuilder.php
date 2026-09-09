<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract;

use MyDB\Migration\Raw;
use MyDB\MyDB;
use MyDB\QueryBuilder\IQueryBuilder;
use Closure;

abstract class AQueryBuilder implements IQueryBuilder {

    protected static string $LOGICAL_OPERATOR_OR = 'OR';
    protected static string $LOGICAL_OPERATOR_AND = 'AND';
    protected MyDB $db;
    protected array $params = [];
    protected string $sqlQuery = '';

    protected ?string $table = null;
    protected string $lastOperator = '';
    protected bool $isParenthesesOpened = false;
    /** @var array<string> */
    protected array $columns = ['*'];
    /** @var array<string|IQueryBuilder|array<string|int|float|bool|IQueryBuilder>> */
    protected array $where = [];
    protected ?IQueryBuilder $having = null;
    /** @var array<string, string> */
    protected array $groupBy = [];
    /** @var array<string, string> */
    protected array $orderBy = [];
    protected int $limitStart = 0;
    protected int $limitTake = 0;
    protected array $relations = [];
    protected array $castRules = [];
    protected array $normalizeRules = [];


    abstract protected function getWhereClause(): ?string;
    abstract protected function getGroupByClause(): ?string;
    abstract protected function getOrderByClause(): ?string;

    public function runNormalize(array $params): array {
        return DataNormalizer::normalize($params, $this->normalizeRules);
    }
    public function runCasts(array $rawData): array {
        return DataCaster::cast($rawData, $this->castRules);
    }

    public function wrapName(string|array|Raw $column): array|string {
        if (is_array($column)) {
            return array_map(fn($item) => $this->wrapName($item), $column);
        }

        if ($column instanceof Raw) {
            return $column->value(parentheses: false);
        }

        $column = trim($column);
        if ($column === '' || $column === '*') {
            return $column;
        }

        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            return implode('.', array_map(fn($p) => $this->wrapName($p), $parts));
        }

        if (str_starts_with($column, '`')) {
            return $column;
        }

        return "`$column`";
    }

    public function flush(): void {
        $this->columns = ['*'];
        $this->params = [];
        $this->where = [];
        $this->having = null;
        $this->groupBy = [];
        $this->orderBy = [];
        $this->limitStart = 0;
        $this->limitTake = 0;
        $this->castRules = [];
        $this->normalizeRules = [];
        $this->relations = [];
        $this->isParenthesesOpened = false;
        $this->lastOperator = '';
    }
    public function relations(array $relations = []): self {
        $this->relations = $relations;
        return $this;
    }

    public function casts(array $rules): self {
        $this->castRules = $rules;
        return $this;
    }
    public function normalize(array $rules): self {
        $this->normalizeRules = $rules;
        return $this;
    }

    public function useDb(MyDB $db): void {
        $this->db = $db;
    }

    protected function cleanParam(): void {
        $this->params = [];
    }
    protected function getParams(): array {
        return $this->params;
    }
    public function getSqlQuery(): string {
        return $this->sqlQuery;
    }
    protected function setParams(array $params): void {
        $this->params = $params;
    }
    protected function addParams(array $params): void {
        $this->params = array_merge($this->params, $params);
    }

    protected function terminateWhereClause(): void {
        if($this->isParenthesesOpened) {
            $this->where[] = ')';

        }
        $this->isParenthesesOpened = false;
    }
    protected function addWhereClause(string $operator, array $args): void {
        if(isset($this->where[0])) {
            if($this->lastOperator && $this->lastOperator !== $operator) { // auto nested query
                if($this->isParenthesesOpened) {
                    $this->where[] = ')';
                    $this->where[] = $operator;
                    $this->where[] = '(';
                }
                else {
                    $lastCondition = array_pop($this->where);
                    $this->where[] = '(';
                    $this->where[] = $lastCondition;
                    $this->where[] = $operator;
                }

                $this->isParenthesesOpened = true;
            } else {
                $this->where[] = $operator;
            }
        }
        $this->lastOperator = $operator;
        if(count($args) === 1) {
            $data = $args[0];
            if(is_callable($data)) {
                $builder = new static();
                call_user_func_array($data, [$builder]);
                $this->where[] = $builder;
            }
            if($data instanceof Raw) {
                $this->where[] = $data->value();
            }
            return;
        }

        $column = null; $operator = null; $value = null;

        if(count($args) === 2) {
            $column = $args[0];
            $operator = '=';
            $value = $args[1];
        } else if(count($args) === 3) {
            $column = $args[0];
            $operator = $args[1];
            $value = $args[2];
        }
        $column = $this->wrapName($column);

        if(is_callable($value)) {
            $builder = new static();
            call_user_func_array($value, [$builder]);
            $value = $builder;
        }
        $this->where[] = [$column, $operator, $value];
    }

    public function from(string $table): self {
        $this->table = $this->wrapName($table);
        return $this;
    }

    /**
     * @param array<string> $columns
     * @return IQueryBuilder
     */
    public function select(string|array|Raw ...$arguments): self {
        $formattedColumns = [];

        foreach ($arguments as $argument) {
            $this->appendColumn($argument, $formattedColumns);
        }

        $this->columns = $formattedColumns ?: ['*'];

        return $this;
    }
    private function appendColumn(mixed $argument, array &$formattedColumns, ?string $tablePrefix = null): void {
        if (is_array($argument)) {
            foreach ($argument as $column => $alias) {
                if (is_string($column) && is_array($alias)) {
                    foreach ($alias as $subKey => $subValue) {
                        $this->appendColumn(
                            is_string($subKey) ? [$subKey => $subValue] : $subValue,
                            $formattedColumns,
                            $column
                        );
                    }
                } elseif (is_string($column)) {
                    $fullColumn = $tablePrefix !== null
                        ? $this->wrapName($tablePrefix) . '.' . $this->wrapName($column)
                        : $this->wrapName($column);

                    $formattedColumns[] = $fullColumn . ' AS ' . $this->wrapName($alias);
                } else {
                    $this->appendColumn($alias, $formattedColumns, $tablePrefix);
                }
            }
            return;
        }

        if ($argument instanceof Raw) {
            $formattedColumns[] = $this->wrapName($argument);
            return;
        }

        $formattedColumns[] = $tablePrefix !== null
            ? $this->wrapName($tablePrefix) . '.' . $this->wrapName($argument)
            : $this->wrapName($argument);
    }

    public function where(mixed ...$args): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, $args);
        return $this;
    }
    public function orWhere(mixed ...$args): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, $args);
        return $this;
    }
    public function whereNull(string $column): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, [$column, 'IS', 'NULL']);
        return $this;
    }
    public function orWhereNull(string $column): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, [$column, 'IS', 'NULL']);
        return $this;
    }
    public function whereNotNull(string $column): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, [$column, 'IS NOT', 'NULL']);
        return $this;
    }
    public function orWhereNotNull(string $column): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, [$column, 'IS NOT', 'NULL']);
        return $this;
    }
    public function whereIn(string $column, array|Closure $subQuery): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, [$column, 'IN', $subQuery]);
        return $this;
    }
    public function orWhereIn(string $column, array|Closure $subQuery): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, [$column, 'IN', $subQuery]);
        return $this;
    }
    public function whereNotIn(string $column, array|Closure $subQuery): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, [$column, 'NOT IN', $subQuery]);
        return $this;
    }
    public function orWhereNotIn(string $column, array|Closure $subQuery): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, [$column, 'NOT IN', $subQuery]);
        return $this;
    }
    public function whereBetween(string $column, array|Closure $subQuery): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, [$column, 'BETWEEN', $subQuery]);
        return $this;
    }
    public function orWhereBetween(string $column, array|Closure $subQuery): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, [$column, 'BETWEEN', $subQuery]);
        return $this;
    }
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $column = $this->wrapName($column);
        $this->orderBy[$column] = $direction;
        return $this;
    }
    public function groupBy(string $column, ?string $direction = null): self {
        $column = $this->wrapName($column);
        $this->groupBy[] = $direction;
        if($direction !== null) {
            $this->orderBy($column, $direction);
        }
        return $this;
    }
    public function having(Closure $condition): IQueryBuilder {
        $this->having = new static();
        call_user_func_array($condition, [$this->having]);
        return $this;
    }

    public function limit(int $take): self {
        $this->skip(0);
        $this->take($take);
        return $this;
    }
    public function skip(int $skip): self {
        $this->limitStart = $skip;
        return $this;
    }
    public function take(int $take): self {
        $this->limitTake = $take;
        return $this;
    }



    public function first(): ?array {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }
}