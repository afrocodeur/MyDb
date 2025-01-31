<?php declare(strict_types=1);

namespace MyDB\Migration\Abstract;

use Closure;
use MyDB\Migration\IMigrationBuilder;
use MyDB\Migration\ITableBuilder;
use MyDB\MyDB;

abstract class AMigrationBuilder implements IMigrationBuilder {

    abstract public function getTable(string $name): ITableBuilder;

    protected function runSqlCode(string $sqlCode): void {
        MyDB::instance()->execute($sqlCode);
    }
    public function createTable(string $name, Closure $definition, bool $ifNotExists = false): void {
        $table = $this->getTable($name);
        call_user_func_array($definition, [$table]);
        $this->runSqlCode($table->getCreateSql($ifNotExists));
    }

    public function createTableIfNotExists(string $name, Closure $definition): void {
        $this->createTable($name, $definition, true);
    }

    public function alterTable(string $name, Closure $definition): void {
        $table = $this->getTable($name);
        call_user_func_array($definition, [$table]);
        $this->runSqlCode($table->getAlterSql());
    }
    public function removeTableForeignKeyConstraint(string $table, string $constraintName): void {
        $this->alterTable($table, function(ITableBuilder $table) use ($constraintName) {
            $table->removeForeignConstraint($constraintName);
        });
    }

    public function rename(string $name, string $newName): void {
        $this->alterTable($name, function(ITableBuilder $table) use ($newName) {
            $table->rename($newName);
        });
    }

    public function removeTablePrimaryKeyConstraint(string $table): void {
        $this->alterTable($table, function(ITableBuilder $table) {
            $table->removePrimary();
        });
    }


    public function dropTableIfExists(string $name): void {
        $this->dropTable($name, true);
    }

    public function dropTable(string $name, bool $ifExists = false): void {
        $this->runSqlCode($this->getTable($name)->getDropSql());
    }

}