<?php declare(strict_types=1);

namespace Exceptions;

use Exception;

class ConfigNotFoundException extends Exception {

    public function __construct(?string $name = "") {
        parent::__construct("Database config $name not found");
    }

}