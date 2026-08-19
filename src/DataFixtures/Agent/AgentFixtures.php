<?php

declare(strict_types=1);

namespace App\DataFixtures\Agent;

use App\DataFixtures\Node\NodeFixtures;
use App\Entity\Agent\Agent;
use App\Entity\Node\Node;
use App\Factory\Enrollment\AgentFactory;
use App\Repository\Node\NodeRepository;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Attaches a descriptive dev {@see Agent} record to every Linux node, so the frontend can
 * display an installed agent version. Windows nodes are left agent-less on purpose, to give
 * the frontend both states to handle. No {@see \App\Entity\Agent\AgentCredential} is
 * created: it would only exist to hold a secret, and fixtures must never carry a usable one.
 *
 * Nodes are re-fetched by hostname through {@see NodeRepository} rather than reused from
 * {@see NodeFixtures}'s in-memory fixture references. This is required, not a workaround:
 * `Doctrine\Common\DataFixtures\Executor\AbstractExecutor::load()` calls `$manager->clear()`
 * after every fixture, so any object handed out by `getReference()` from an earlier fixture is
 * already detached by the time this one runs. `Agent::__construct()` calls `Node::addAgent()`,
 * which reads the (lazy) `$agents` collection — touching that collection on a detached `Node`
 * forces Doctrine to re-hydrate it, and re-hydrating an object that already has its readonly
 * `$id` set throws. Re-fetching through the repository always returns a Node attached to the
 * *current* manager session, so its collection initializes cleanly. Any fixture that needs an
 * entity from an earlier fixture AND calls a method touching that entity's own collections must
 * follow the same rule; entities only ever placed *into* another entity's fresh collection (see
 * {@see NodeFixtures}'s use of `getReference()` for `NodeGroup`) are unaffected.
 */
final class AgentFixtures extends Fixture implements DependentFixtureInterface
{
    private const string AGENT_VERSION = '0.1.0-dev';

    /** @var list<string> */
    private const array LINUX_NODE_HOSTNAMES = [
        'prod-web-01', 'prod-web-02', 'prod-api-01', 'prod-db-01', 'prod-cache-01',
        'staging-web-01', 'staging-api-01', 'staging-db-01',
        'homelab-01', 'homelab-nas-01',
    ];

    public function __construct(
        private readonly NodeRepository $nodes,
        private readonly Auditor $auditor,
        private readonly AgentFactory $agentFactory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        foreach (self::LINUX_NODE_HOSTNAMES as $hostname) {
            $node = $this->nodes->findOneBy(['hostname' => $hostname]);
            \assert($node instanceof Node);
            $manager->persist($this->agentFactory->create($node, self::AGENT_VERSION, $now, $now));
        }
        $manager->flush();

        $this->auditor->getConfiguration()->enable();
    }

    /** @return array<class-string<\Doctrine\Common\DataFixtures\FixtureInterface>> */
    public function getDependencies(): array
    {
        return [NodeFixtures::class];
    }
}
