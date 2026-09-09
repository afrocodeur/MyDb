<?php declare(strict_types=1);

namespace MyDB\QueryBuilder;

use Closure;

class Relation {

    private ?Closure $callback = null;

    public function __construct(private readonly array $relations){}

    public function callback(Closure $callback): self {
        $this->callback = $callback;
        return $this;
    }

    public function getRelationDescription(): array {
        $relations = [];
        if($this->relations){
            $relations['with'] = $this->relations;
        }
        if($this->callback){
            $relations['callback'] = $this->callback;
        }
        return $relations;
    }
}