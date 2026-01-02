<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\MySQL;

use MyDB\MyDB;
use MyDB\QueryBuilder\IUtils;

class Utils implements IUtils {

    public function tableExists(string $table): bool {
        $response = MyDB::instance()->get("SHOW TABLES LIKE :table", [":table" => $table]);
        return isset($response[0]);
    }

}