<?php declare(strict_types=1);

namespace MyDB\Migration;
use Closure;

interface IMigrationBuilder {


    public function createTable(string $name, Closure $definition): void;
    public function createTableIfNotExists(string $name, Closure $definition): void;
    public function dropTableIfExists(string $name): void;
    public function dropTable(string $name): void;
    public function alterTable(string $name, Closure $definition): void;


    public function rename(string $name, string $newName): void;
    public function removeTablePrimaryKeyConstraint(string $table): void;
    public function removeTableForeignKeyConstraint(string $table, string $constraintName): void;

}