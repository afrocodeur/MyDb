<?php declare(strict_types=1);

namespace MyDB;

interface IConnectionConfig {

    public function getDsn(): string;
    public function getOptions(): array;
    public function getUsername(): string;
    public function getPassword(): string;
    public function driver(): EDriver;

}