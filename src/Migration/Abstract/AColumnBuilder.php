<?php declare(strict_types=1);

namespace MyDB\Migration\Abstract;

use MyDB\Migration\EType;
use MyDB\Migration\IColumnBuilder;
use MyDB\Migration\Row;

abstract class AColumnBuilder implements IColumnBuilder {

    protected array $names = [];
    protected string $name;
    protected EType $type = EType::INT;
    protected int $length = 0;
    protected int $precision = 0;
    protected bool $nullable = false;
    protected bool $unsigned =  false;
    protected bool $unique =  false;
    protected bool $primary = false;
    protected array $values = [];
    protected bool $useCurrent = false;
    protected bool $useCurrentOnUpdate = false;
    protected bool $autoIncrement = false;
    protected int $from = 0;
    protected null|int|float|string|Row $default = null;
    protected bool $foreign = false;
    protected ?string $foreignTable = null;
    protected ?string $foreignColumn = null;
    protected ?string $onUpdate = null;
    protected ?string $onDelete = null;
    protected ?string $constraintName = null;
    protected ?string $newName = null;
    protected ?string $afterColumnName = null;
    protected bool $change = false;
    protected bool $drop = false;
    protected bool $dropConstraint = false;
    protected ?string $comment = null;
    protected ?string $constraintCode = null;


    public function name(string $name): self {
        $this->name = $name;
        return $this;
    }
    public function type(EType $type): self {
        $this->type = $type;
        return $this;
    }

    public function length(int $length): self {
        $this->length = $length;
        return $this;
    }

    public function precision(int $precision): self {
        $this->precision = $precision;
        return $this;
    }

    public function unique(): self {
        $this->unique = true;
        return $this;
    }
    public function nullable(): self {
        $this->nullable = true;
        return $this;
    }
    public function unsigned(): self  {
        $this->unsigned = true;
        return $this;
    }

    public function autoIncrement(int $from = 0): self {
        $this->from = 0;
        $this->autoIncrement = true;
        return $this;
    }

    public function primary(): self {
        $this->primary = true;
        return $this;
    }

    public function values(array $values): self {
        $this->values = $values;
        return $this;
    }

    public function default(null|int|float|string|Row $default): self {
        $this->default = $default;
        return $this;
    }
    public function useCurrent(): self {
        $this->useCurrent = true;
        return $this;
    }

    public function useCurrentOnUpdate(): self {
        $this->useCurrentOnUpdate = true;
        return $this;
    }

    public function comment(string $comment): self {
        $this->comment = $comment;
        return $this;
    }

    public function foreign(string $constraintName): self {
        $this->foreign = true;
        $this->constraintName = $constraintName;
        return $this;
    }

    public function references(string $table, string $column): self {
        $this->foreignTable = $table;
        $this->foreignColumn = $column;
        return $this;
    }

    public function constrained(?string $name = null): self {
        $this->constraintName = $name;
        return $this;
    }

    public function onUpdate(string $onUpdate): self {
        $this->onUpdate = $onUpdate;
        return $this;
    }

    public function onDelete(string $onDelete): self {
        $this->onDelete = $onDelete;
        return $this;
    }

    public function cascadeOnUpdate(): self {
        $this->onUpdate('CASCADE');
        return $this;
    }

    public function restrictOnUpdate(): self {
        $this->onUpdate('RESTRICT');
        return $this;
    }

    public function nullOnUpdate(): self {
        $this->onUpdate('SET NULL');
        return $this;
    }

    public function cascadeOnDelete(): self {
        $this->onDelete('CASCADE');
        return $this;
    }

    public function restrictOnDelete(): self {
        $this->onDelete('RESTRICT');
        return $this;
    }

    public function nullOnDelete(): self {
        $this->onDelete('SET NULL');
        return $this;
    }


    public function after(string $columnName): void {
        $this->afterColumnName = $columnName;
    }

    public function rename(string $newName): void {
        $this->newName = $newName;
    }
    public function change(): void {
        $this->change = true;
    }
    public function drop(): void {
        $this->drop = true;
    }
    public function dropConstraint(): void {
        $this->dropConstraint = true;
    }
    public function hasConstraintCode(): bool {
        return !is_null($this->constraintCode);
    }


}