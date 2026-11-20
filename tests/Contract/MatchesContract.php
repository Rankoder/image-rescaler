<?php

declare(strict_types=1);

namespace Tests\Contract;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\HttpTestHelpers;

trait MatchesContract
{
    use HttpTestHelpers;

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

    /**
     * @param list<array{?string, string}> $expected field and code of every error
     */
    private function assertErrors(int $status, array $expected, string $method, string $path, ResponseInterface $response): void
    {
        $this->assertMatchesContract($method, $path, $response);
        $this->assertErrorEnvelope($status, $expected, $response);
    }
}
