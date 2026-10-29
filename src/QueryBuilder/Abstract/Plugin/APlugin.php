<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract\Plugin;

use Throwable;

abstract class APlugin implements IPlugin {
    public function priority(): int { return 100; }
    public function before(QueryContext $ctx): void {}
    public function after(QueryContext $ctx): void {}
    public function onError(QueryContext $ctx, Throwable $e): void {}
}