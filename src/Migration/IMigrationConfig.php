<?php declare(strict_types=1);

namespace MyDB\Migration;

interface IMigrationConfig {

    /**
     * @return array<class-string>
     */
    public function migrations(): array;

    /**
     * @return array<string>
     */
    public function versions(): array;

    public function rollbackName(string $version): string;

}