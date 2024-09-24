<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private QueryBuilder $queryBuilder;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->queryBuilder = new QueryBuilder();
    }

    public function buildQuery(string $query, array $args = []): string
    {
        return $this->queryBuilder->buildQuery($query,$args);
    }

    public static function skip()
    {
        return '%_';
    }
}
