<?php

declare(strict_types=1);

namespace App\State\Processor\Enrollment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Enrollment\AgentEnrollmentInput;
use App\Dto\Enrollment\AgentEnrollmentOutput;
use App\Service\Enrollment\AgentEnrollmentService;
use App\Service\Enrollment\ExpiredEnrollmentToken;
use App\Service\Enrollment\InvalidEnrollmentToken;
use App\Service\Enrollment\UsedEnrollmentToken;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<AgentEnrollmentInput, AgentEnrollmentOutput> */
final readonly class AgentEnrollmentProcessor implements ProcessorInterface
{
    public function __construct(private AgentEnrollmentService $enrollmentService)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgentEnrollmentOutput
    {
        try {
            $result = $this->enrollmentService->enroll(
                $data->enrollmentToken,
                $data->hostname,
                $data->os,
                $data->architecture,
                $data->agentVersion,
            );
        } catch (InvalidEnrollmentToken|ExpiredEnrollmentToken|UsedEnrollmentToken $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new AgentEnrollmentOutput(
            (string) $result->node->id(),
            (string) $result->agent->id(),
            $result->rawAgentToken,
        );
    }
}
