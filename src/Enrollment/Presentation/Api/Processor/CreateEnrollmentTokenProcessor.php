<?php

declare(strict_types=1);

namespace App\Enrollment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enrollment\Application\Service\EnrollmentTokenIssuer;
use App\Enrollment\Presentation\Api\Dto\CreateEnrollmentTokenInput;
use App\Enrollment\Presentation\Api\Resource\EnrollmentTokenOutput;

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
