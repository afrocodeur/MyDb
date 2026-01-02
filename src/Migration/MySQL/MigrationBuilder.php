<?php declare(strict_types=1);

namespace MyDB\Migration\MySQL;

use MyDB\Migration\Abstract\AMigrationBuilder;
use MyDB\Migration\IMigrationBuilder;
use MyDB\Migration\ITableBuilder;

class MigrationBuilder extends AMigrationBuilder {

    public function getTable(string $name): ITableBuilder {
        return new TableBuilder($name);
    }

}