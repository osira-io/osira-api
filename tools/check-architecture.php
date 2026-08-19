#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Enforces the project's Symfony-standard architecture convention: every class under
 * a technical-type root (Entity/, Repository/, Service/, Dto/, Security/, Command/,
 * State/Processor/, State/Provider/, ...) must live in a per-domain subfolder, and each
 * file's declared namespace must match its PSR-4 path exactly.
 *
 * Uses nikic/php-parser (already required by phpstan) instead of regex to read each
 * file's namespace declaration, so multi-line namespaces, comments, and string literals
 * containing namespace-like text can never produce a false positive or false negative.
 */

require dirname(__DIR__).'/vendor/autoload.php';

use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

$projectDir = dirname(__DIR__);

$composerJson = json_decode((string) file_get_contents($projectDir.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
$psr4 = $composerJson['autoload']['psr-4'] ?? [];
if (!isset($psr4['App\\'])) {
    fwrite(\STDERR, "check-architecture: composer.json autoload.psr-4 has no \"App\\\\\" mapping.\n");
    exit(1);
}

$namespacePrefix = 'App';
$srcDir = rtrim($projectDir.'/'.rtrim($psr4['App\\'], '/'), '/');

/**
 * Files exempt from the "must live under a <Domain> subfolder" rule, each with a
 * written justification. Namespace/path consistency (rule 10) is still enforced.
 *
 * @var array<string, string>
 */
$allowlist = [
    'Kernel.php' => 'Symfony framework entry point; convention requires it at src/ root.',
    'DependencyInjection/Compiler/DisableAuditorViewerCompilerPass.php' => 'Genuinely global/Kernel-level compiler pass, not tied to a single business domain.',
];

/**
 * Technical-type roots that require an immediate <Domain> subfolder, i.e.
 * src/<Root>/<Domain>/<Class>.php (at least 3 path segments below src/).
 *
 * @var list<string>
 */
$enforcedRoots = [
    'Command',
    'Controller',
    'DataFixtures',
    'Dto',
    'Entity',
    'EventSubscriber',
    'Factory',
    'Repository',
    'Security',
    'Service',
    'Validator',
];

/** State/ requires a Processor or Provider sub-root before the domain: State/<Sub>/<Domain>/<Class>.php. */
$stateSubRoots = ['Processor', 'Provider'];

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$nodeFinder = new NodeFinder();

/** @var list<string> $violations */
$violations = [];
$filesChecked = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS));
/** @var list<string> $phpFiles */
$phpFiles = [];
foreach ($rii as $fileInfo) {
    if ($fileInfo->isFile() && 'php' === $fileInfo->getExtension()) {
        $phpFiles[] = $fileInfo->getPathname();
    }
}
sort($phpFiles);

foreach ($phpFiles as $absolutePath) {
    ++$filesChecked;
    $relativePath = ltrim(substr($absolutePath, strlen($srcDir)), '/');
    $parts = explode('/', $relativePath);
    $root = $parts[0];

    $isAllowlisted = isset($allowlist[$relativePath]);

    if (!$isAllowlisted) {
        if (1 === count($parts)) {
            $violations[] = sprintf(
                '%s: business class lives directly in src/ root; move it under a technical-type/<Domain> folder.',
                $relativePath
            );
        } elseif (in_array($root, $enforcedRoots, true)) {
            if (count($parts) < 3) {
                $violations[] = sprintf(
                    '%s: %s classes must live under %s/<Domain>/, found flat directly under %s/.',
                    $relativePath,
                    $root,
                    $root,
                    $root
                );
            }
        } elseif ('State' === $root) {
            $subRoot = $parts[1] ?? null;
            if (!in_array($subRoot, $stateSubRoots, true) || count($parts) < 4) {
                $violations[] = sprintf(
                    '%s: State classes must live under State/Processor/<Domain>/ or State/Provider/<Domain>/.',
                    $relativePath
                );
            }
        } elseif (count($parts) < 2) {
            $violations[] = sprintf(
                '%s: business class lives directly under %s/ with no <Domain> subfolder.',
                $relativePath,
                $root
            );
        }
    }

    $expectedNamespace = 1 === count($parts)
        ? $namespacePrefix
        : $namespacePrefix.'\\'.implode('\\', array_slice($parts, 0, -1));

    $code = file_get_contents($absolutePath);
    if (false === $code) {
        $violations[] = sprintf('%s: unable to read file.', $relativePath);
        continue;
    }

    try {
        $ast = $parser->parse($code);
    } catch (PhpParser\Error $e) {
        $violations[] = sprintf('%s: parse error (%s).', $relativePath, $e->getMessage());
        continue;
    }

    $namespaceNode = null !== $ast ? $nodeFinder->findFirstInstanceOf($ast, Namespace_::class) : null;
    $declaredNamespace = $namespaceNode?->name?->toString();

    if (null === $declaredNamespace) {
        $violations[] = sprintf(
            '%s: no namespace declaration found; expected "namespace %s;".',
            $relativePath,
            $expectedNamespace
        );
    } elseif ($declaredNamespace !== $expectedNamespace) {
        $violations[] = sprintf(
            '%s: namespace/path mismatch — declares "namespace %s;" but its path requires "namespace %s;".',
            $relativePath,
            $declaredNamespace,
            $expectedNamespace
        );
    }
}

if ([] !== $violations) {
    fwrite(\STDERR, sprintf("Architecture check FAILED: %d violation(s) found in %d file(s).\n\n", count($violations), $filesChecked));
    foreach ($violations as $violation) {
        fwrite(\STDERR, '  - '.$violation."\n");
    }
    exit(1);
}

fwrite(\STDOUT, sprintf("Architecture check OK: %d file(s) checked, 0 violations.\n", $filesChecked));
exit(0);
