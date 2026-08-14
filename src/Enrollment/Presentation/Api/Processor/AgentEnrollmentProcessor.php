<?php

declare(strict_types=1);

namespace App\Enrollment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enrollment\Application\Service\AgentEnrollmentService;
use App\Enrollment\Domain\Exception\ExpiredEnrollmentToken;
use App\Enrollment\Domain\Exception\InvalidEnrollmentToken;
use App\Enrollment\Domain\Exception\UsedEnrollmentToken;
use App\Enrollment\Presentation\Api\Dto\AgentEnrollmentInput;
use App\Enrollment\Presentation\Api\Resource\AgentEnrollmentOutput;
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
