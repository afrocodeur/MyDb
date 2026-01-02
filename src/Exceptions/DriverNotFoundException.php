<?php declare(strict_types = 1);

namespace MyDB\Exceptions;
use Exception;

class DriverNotFoundException extends Exception {

    public function __construct(string $driverName = "", int $code = 0, ?Throwable $previous = null) {
        parent::__construct("$driverName Driver not found", $code, $previous);
    }

}