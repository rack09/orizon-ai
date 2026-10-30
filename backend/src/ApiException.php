<?php
declare(strict_types=1);
namespace Orizon;

final class ApiException extends \RuntimeException
{
    public function __construct(public int $httpStatus, public string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
