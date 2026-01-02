<?php declare(strict_types=1);

namespace MyDB\QueryBuilder;

interface IUtils {

    public function tableExists(string $table): bool;

}