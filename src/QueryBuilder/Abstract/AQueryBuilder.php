<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract;

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
    /** @var array<string> */
    protected array $columns = ['*'];
    /** @var array<string|IQueryBuilder|array<string|int|float|bool|IQueryBuilder>> */
    protected array $where = [];
    protected ?IQueryBuilder $having = null;
    /** @var array<string> */
    protected array $groupBy = [];
    /** @var array<string, string> */
    protected array $orderBy = [];
    protected int $limitStart = 0;
    protected int $limitTake = 0;


    abstract protected function getWhereClause(): ?string;
    abstract protected function getGroupByClause(): ?string;
    abstract protected function getOrderByClause(): ?string;
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
    protected function addParams(array $params): void {
        $this->params = array_merge($this->params, $params);
    }

    protected function addWhereClause(string $operator, array $args): void {
        if(isset($this->where[0])) {
            $this->where[] = $operator;
        }
        if(count($args) === 1) {
            $data = $args[0];
            if(is_callable($data)) {
                $builder = new static();
                call_user_func_array($data, [$builder]);
                $this->where[] = $builder;
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

        if(is_callable($value)) {
            $builder = new static();
            call_user_func_array($value, [$builder]);
            $value = $builder;
        }
        $this->where[] = [$column, $operator, $value];
    }

    public function from(string $table): self {
        $this->table = $table;
        return $this;
    }

    /**
     * @param array<string> $columns
     * @return IQueryBuilder
     */
    public function select(array $columns): self {
        $this->columns = $columns;
        return $this;
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
        $this->addWhereClause(self::$LOGICAL_OPERATOR_AND, [$column, 'IS', 'NOT NULL']);
        return $this;
    }
    public function orWhereNotNull(string $column): self {
        $this->addWhereClause(self::$LOGICAL_OPERATOR_OR, [$column, 'IS', 'NOT NULL']);
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
    public function orderBy(string $column, string $direction): self {
        $this->orderBy[$column] = $direction;
        return $this;
    }
    public function groupBy(string $colum, string $direction = 'ASC'): self {
        $this->groupBy[$colum] = $direction;
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
        $this->limitStart = 0;
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