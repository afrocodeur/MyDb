<?php declare(strict_types=1);

namespace MyDB;

interface ILogger {

    public function info(string $message): void;
    public function error(string $message): void;
    public function note(string $message): void;
    public function warning(string $message): void;
    public function success(string $message): void;
    public function debug(string $message, array $data = []): void;
    public function critical(string $message, array $data = []): void;

}