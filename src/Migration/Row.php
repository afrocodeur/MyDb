<?php declare(strict_types=1);

namespace MyDB\Migration;

final readonly class Row {

    public function __construct(private string $code) {}

    public function value(): string {
        return '('.$this->code.')';
    }

    public static function wrap(string $code): Row {
        return new self($code);
    }

}