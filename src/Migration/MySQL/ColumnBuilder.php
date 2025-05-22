<?php declare(strict_types=1);

namespace MyDB\Migration\MySQL;

use MyDB\Migration\Abstract\AColumnBuilder;
use MyDB\Migration\EType;
use MyDB\Migration\IColumnBuilder;
use MyDB\Migration\Row;

final class ColumnBuilder extends AColumnBuilder {


    public function __construct(string $name) {
        $this->name = "`$name`";
    }

    public function after(string $columnName): void {
        parent::after("`$columnName`");
    }

    public function getType(): string {
        if($this->type === EType::STRING) {
            return 'VARCHAR';
        }
        return $this->type->value;
    }

    private function getDataDescription(): string {
        $columnDefinition = [];
        $type = $this->getType();
        if($this->length) {
            $columnDefinition[] = $type.'('.$this->length.')';
        }
        else if($this->type === EType::ENUM) {
            $values = array_map(fn($value) => (is_string($value) ? '"'.$value.'"' : $value), $this->values);
            $columnDefinition[] = $type.'('.implode(', ', $values).')';
        } else {
            $columnDefinition[] = $type;
        }
        if($this->unsigned) {
            $columnDefinition[] = 'UNSIGNED';
        }
        if($this->unique) {
            $columnDefinition[] = 'UNIQUE';
        }
        if($this->autoIncrement) {
            $columnDefinition[] = 'AUTO_INCREMENT';
        }
        if($this->nullable) {
            $columnDefinition[] = 'NULL';
        }
        else {
            $columnDefinition[] = 'NOT NULL';
        }
        if($this->primary) {
            $columnDefinition[] = 'PRIMARY KEY';
        }
        if(!is_null($this->default)) {
            $default = '';
            if(is_string($this->default)) {
                $default = '"'.$this->default.'"';
            } else if(is_numeric($this->default)) {
                $default = $this->default;
            } else if($this->default instanceof Row) {
                $default = $this->default->value();
            }
            $columnDefinition[] = 'DEFAULT '.$default;
        }
        else if($this->useCurrent) {
            $columnDefinition[] = 'DEFAULT CURRENT_TIMESTAMP';
        }

        if($this->useCurrentOnUpdate) {
            $columnDefinition[] = 'ON UPDATE CURRENT_TIMESTAMP';
        }
        if($this->afterColumnName) {
            $columnDefinition[] = 'AFTER '.$this->afterColumnName;
        }

        return implode(' ', $columnDefinition);
    }

    public function getDataForeignConstraint(): string {
        $foreignCode = 'FOREIGN KEY ('.$this->name.') REFERENCES '.$this->foreignTable.'('.$this->foreignColumn.')';
        if($this->onUpdate) {
            $foreignCode .= ' ON UPDATE '.$this->onUpdate;
        }
        if($this->onDelete) {
            $foreignCode .= ' ON DELETE '.$this->onDelete;
        }
        return $foreignCode;
    }

    public function createSql(): string {
        if(count($this->names)) {
            $sqlCode = '';
            if($this->constraintName) {
                $sqlCode = 'CONSTRAINT '.$this->constraintName;
            }
            if($this->primary) {
                $sqlCode .= ' PRIMARY KEY ('.implode(', ', $this->names).')';
            }
            if($this->unique) {
                $sqlCode .= ' UNIQUE ('.implode(', ', $this->names).')';
            }
            return $sqlCode;
        }
        $sqlCode = "{$this->name} {$this->getDataDescription()}";
        if($this->foreign) {
            $this->constraintCode = "CONSTRAINT {$this->constraintName} ".$this->getDataForeignConstraint();
        }
        return $sqlCode;
    }

    public function constraintCode(): ?string {
        return $this->constraintCode;
    }

    public function alter(): string {

        if($this->dropConstraint) {
            return $this->dropConstraintSql();
        }
        if($this->drop) {
            return $this->dropSql();
        }
        if($this->newName) {
            return $this->renameSql();
        }
        if($this->foreign) {
            $this->constraintCode = "ADD CONSTRAINT {$this->name} ".$this->getDataForeignConstraint();
        }
        if($this->change) {
            return $this->changeSql();
        }

        return $this->addSql();
    }

    private function dropConstraintSql(): string {
        if($this->primary) {
            return 'DROP INDEX `PRIMARY`';
        }
        if($this->foreign) {
            return 'DROP FOREIGN KEY `'.$this->constraintName.'`';
        }
        return 'DROP INDEX `'.$this->constraintName.'`';
    }

    private function dropSql(): string {
        return 'DROP COLUMN '.$this->name;
    }
    public function renameSql(): string {
        return "RENAME COLUMN {$this->name} TO {$this->newName}";
    }
    public function changeSql(): string {
        $newName = $this->newName ?? $this->name;
        return "CHANGE {$this->name} {$newName} ".$this->getDataDescription();
    }
    public function addSql(): string {
        return "ADD COLUMN {$this->name} {$this->getDataDescription()}";
    }

    public function rename(string $newName): void {
        parent::rename("`$newName`");
    }

    public function foreign(string $constraintName): self {
        return parent::foreign("`$constraintName`"); // TODO: Change the autogenerated stub
    }
    public function references(string $table, string $column): AColumnBuilder {
        return parent::references("`$table`", "`$column`"); // TODO: Change the autogenerated stub
    }
    public function columns(string ...$columns): AColumnBuilder {
        $names = array_map(fn($name) => "`$name`", $columns);
        return parent::columns(...$names);
    }
}