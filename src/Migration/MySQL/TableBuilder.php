<?php declare(strict_types=1);

namespace MyDB\Migration\MySQL;

use MyDB\Migration\Abstract\AColumnBuilder;
use MyDB\Migration\Abstract\ATableBuilder;
use MyDB\Migration\IColumnBuilder;
use MyDB\Migration\ITableBuilder;

class TableBuilder extends ATableBuilder {

    public function __construct(string $name) {
        $this->name = "`$name`";
    }

    public function getColumn(string $name): IColumnBuilder {
        return new ColumnBuilder($name);
    }


    public function getCreateSql(bool $ifNotExists): string {
        $definitionSqlCodes = [];
        $constraintCodes = [];
        foreach ($this->columns as $column) {
            $definitionSqlCodes[] = $column->createSql();
            if($column->hasConstraintCode()) {
                $constraintCodes[] = $column->constraintCode();;
            }
        }
        $definition = implode(",\n", array_merge($definitionSqlCodes, $constraintCodes));

        return 'CREATE TABLE '.($ifNotExists ? 'IF NOT EXISTS ': ''). $this->name."(\n$definition\n);";
    }
    public function getAlterSql(): string {
        $sqlCodes = [];

        foreach ($this->columns as $column) {
            $sqlCodes[] = 'ALTER TABLE '.$this->name.' '.$column->alter();
            if($column->hasConstraintCode()) {
                $sqlCodes[] = 'ALTER TABLE '.$this->name.' '.$column->constraintCode();
            }
        }
        if($this->newName) {
            $sqlCodes[] = "ALTER TABLE {$this->name} RENAME {$this->newName}";
        }

        return implode(";\n", $sqlCodes).';';
    }
    public function getDropSql(bool $ifExists = false): string {
        return 'DROP TABLE '.$this->name;
    }
}