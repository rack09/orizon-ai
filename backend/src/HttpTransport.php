<?php
declare(strict_types=1);
namespace Orizon;

interface HttpTransport
{
    /** @return array{status:int,body:array} */
    public function post(string $url, array $headers, array $payload): array;
}
