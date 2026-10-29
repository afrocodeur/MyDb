<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract\Plugin;

use Throwable;

interface IPlugin {
    public function name(): string;
    public function priority(): int;
    public function before(QueryContext $ctx): void;
    public function after(QueryContext $ctx): void;
    public function onError(QueryContext $ctx, Throwable $e): void;
}