<?php declare(strict_types=1);

namespace MyDB\Migration\Abstract;
use MyDB\Migration\EType;
use MyDB\Migration\IColumnBuilder;
use MyDB\Migration\ITableBuilder;

abstract class ATableBuilder implements ITableBuilder {

    protected string $name;
    protected ?string $newName = null;

    /** @var array<AColumnBuilder> */
    protected array $columns = [];


    abstract public function getColumn(string $name): IColumnBuilder;

    public function uuid(string $name): void {

    }
    public function id(string $name, int $length = 11): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::INT)->length($length)->unsigned()->primary()->autoIncrement();
        $this->columns[] = $column;
        return $column;
    }
    public function int(string $name, int $length = 11): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::INT)->length($length);
        $this->columns[] = $column;
        return $column;
    }

    public function float(string $name, int $length, int $precision): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::FLOAT)->precision($precision);
        $this->columns[] = $column;
        return $column;
    }

    public function double(string $name, int $length, int $precision): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::DOUBLE)->precision($precision);
        $this->columns[] = $column;
        return $column;
    }

    public function string(string $name, int $length = 255): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::STRING)->length($length);
        $this->columns[] = $column;
        return $column;
    }

    public function text(string $name): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::TEXT);
        $this->columns[] = $column;
        return $column;
    }

    public function enum(string $name, array $values = []): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::ENUM)->values($values);
        $this->columns[] = $column;
        return $column;
    }

    public function dateTime(string $name): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::DATETIME);
        $this->columns[] = $column;
        return $column;
    }

    public function timestamp(string $name): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::TIMESTAMP);
        $this->columns[] = $column;
        return $column;
    }

    public function time(string $name): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::TIME);
        $this->columns[] = $column;
        return $column;
    }

    public function date(string $name): IColumnBuilder {
        $column = $this->getColumn($name)->type(EType::DATE);
        $this->columns[] = $column;
        return $column;
    }

    public function timestamps(): void {
        $this->timestamp('created_at')->nullable()->useCurrent();
        $this->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
    }

    public function dropColumn(string $name): void {
        $column = $this->getColumn($name);
        $this->columns[] = $column;
        $column->drop();
    }

    public function rename(string $newName): void {
        $this->newName = $newName;
    }

    public function removeConstraint(string $name): void {
        $column = $this->getColumn('')->constrained($name);
        $this->columns[] = $column;
        $column->dropConstraint();
    }
    public function removeForeignConstraint(string $name): void {
        $column = $this->getColumn('')->foreign($name);
        $this->columns[] = $column;
        $column->dropConstraint();
    }
    public function removePrimary(): void {
        $column = $this->getColumn('')->primary();
        $this->columns[] = $column;
        $column->dropConstraint();
    }

}