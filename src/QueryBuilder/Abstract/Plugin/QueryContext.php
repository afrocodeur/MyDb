<?php declare(strict_types=1);

namespace MyDB\QueryBuilder\Abstract\Plugin;

use MyDB\QueryBuilder\IQueryBuilder;

class QueryContext {
    private bool $shortCircuited = false;

    public function __construct(
        public readonly string $type,
        public IQueryBuilder $builder,
        public string $sql = '',
        public array $params = [],
        public mixed $result = null,
        public array $meta = [],
    ) {}

    public function respondWith(mixed $result): void {
        $this->result = $result;
        $this->shortCircuited = true;
    }
    public function isShortCircuited(): bool { return $this->shortCircuited; }
}