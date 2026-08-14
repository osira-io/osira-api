<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\EnrollmentTokenOutput;
use App\Application\Enrollment\EnrollmentTokenIssuer;
use App\Dto\CreateEnrollmentTokenInput;

/** @implements ProcessorInterface<CreateEnrollmentTokenInput, EnrollmentTokenOutput> */
final readonly class CreateEnrollmentTokenProcessor implements ProcessorInterface
{
    public function __construct(private EnrollmentTokenIssuer $issuer)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrollmentTokenOutput
    {
        $issuedToken = $this->issuer->issue();

        return new EnrollmentTokenOutput(
            (string) $issuedToken->enrollmentToken->id(),
            $issuedToken->rawToken,
            $issuedToken->enrollmentToken->expiresAt(),
        );
    }
}
