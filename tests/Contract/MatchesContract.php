<?php

declare(strict_types=1);

namespace Tests\Contract;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;

trait MatchesContract
{
    private static ?ResponseValidator $contract = null;

    private function assertMatchesContract(string $method, string $path, ResponseInterface $response): void
    {
        self::$contract ??= (new ValidatorBuilder())
            ->fromYamlFile(__DIR__ . '/../../openapi.yaml')
            ->getResponseValidator();

        $response->getBody()->rewind();
        self::$contract->validate(new OperationAddress($path, $method), $response);
        $response->getBody()->rewind();
        $this->addToAssertionCount(1);
    }
}
