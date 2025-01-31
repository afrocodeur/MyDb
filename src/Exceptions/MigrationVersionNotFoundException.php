<?php declare(strict_types=1);

namespace Exceptions;

use Exception;

class MigrationVersionNotFoundException extends Exception {

    public function __construct(string $name, int $code = 0, ?Throwable $previous = null) {
        parent::__construct("AMigrationBuilder version { $name } not found", $code, $previous);
    }


}