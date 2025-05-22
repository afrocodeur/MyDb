<?php

namespace MyDB\Migration;

interface IColumnBuilder {

    public function name(string $name): IColumnBuilder;
    public function columns(string ...$names): IColumnBuilder;
    public function type(EType $type): IColumnBuilder;
    public function length(int $length): IColumnBuilder;
    public function precision(int $precision): IColumnBuilder;
    public function unique(): IColumnBuilder;
    public function nullable(): IColumnBuilder;
    public function unsigned(): IColumnBuilder;
    public function autoIncrement(int $from = 0): IColumnBuilder;
    public function primary(): IColumnBuilder;
    public function values(array $values): IColumnBuilder;
    public function default(int|float|string $default): IColumnBuilder;
    public function useCurrent(): IColumnBuilder;
    public function useCurrentOnUpdate(): IColumnBuilder;
    public function comment(string $comment): IColumnBuilder;
    public function foreign(string $constraintName): IColumnBuilder;
    public function references(string $table, string $column): IColumnBuilder;
    public function constrained(?string $name = null): IColumnBuilder;
    public function onUpdate(string $onUpdate): IColumnBuilder;
    public function onDelete(string $onDelete): IColumnBuilder;
    public function cascadeOnUpdate(): IColumnBuilder;
    public function restrictOnUpdate(): IColumnBuilder;
    public function nullOnUpdate(): IColumnBuilder;
    public function cascadeOnDelete(): IColumnBuilder;
    public function restrictOnDelete(): IColumnBuilder;
    public function nullOnDelete(): IColumnBuilder;

    public function after(string $columnName): void;
    public function rename(string $newName): void;
    public function change(): void;
    public function drop(): void;
    public function dropConstraint(): void;


    public function createSql(): string;
    public function constraintCode(): ?string;
    public function alter(): string;
    public function hasConstraintCode(): bool;


}