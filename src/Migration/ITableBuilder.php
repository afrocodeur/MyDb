<?php

namespace MyDB\Migration;

interface ITableBuilder {

    public function uuid(string $name): void;
    public function id(string $name, int $length = 11): IColumnBuilder;
    public function int(string $name, int $length = 11): IColumnBuilder;
    public function float(string $name, int $length, int $precision): IColumnBuilder;
    public function double(string $name, int $length, int $precision): IColumnBuilder;
    public function string(string $name, int $length = 255): IColumnBuilder;
    public function text(string $name): IColumnBuilder;
    public function enum(string $name, array $values = []): IColumnBuilder;
    public function dateTime(string $name): IColumnBuilder;
    public function timestamp(string $name): IColumnBuilder;
    public function time(string $name): IColumnBuilder;
    public function date(string $name): IColumnBuilder;
    public function timestamps(): void;
    public function dropColumn(string $name): void;


    public function rename(string $newName): void;
    public function removeConstraint(string $name): void;
    public function removeForeignConstraint(string $name): void;
    public function removePrimary(): void;



    public function getCreateSql(bool $ifNotExists): string;
    public function getAlterSql(): string;
    public function getDropSql(bool $ifExists = false): string;

}