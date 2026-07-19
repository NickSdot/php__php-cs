<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureSourceRunVerifier implements FixtureSourceVerifier
{
    public function __construct(
        private FixtureSourceContext $sourceContext = new FixtureSourceContext(),
    ) {}

    public function canSelect(
        FixtureSource $source,
        FixtureSourceVerification $verification,
    ): bool {
        if (null === $verification->rewriteRoot) {
            return true;
        }

        if (!$verification->runner instanceof FixtureOriginalRunner) {
            return true;
        }

        $rewritePath = $verification->rewriteRoot . DIRECTORY_SEPARATOR . $source->relativePath;
        $result = $this->sourceContext->run(
            sourcePath: $source->sourcePath,
            rewritePath: $rewritePath,
            run: fn(string $path): array => $verification->runner->runOriginalFile($path),
        );

        return $result['passed'];
    }
}
