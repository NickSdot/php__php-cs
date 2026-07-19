<?php

declare(strict_types=1);

namespace InternalsCS\Fixture;

final readonly class FixtureSourceRunVerifier implements FixtureSourceVerifier
{
    public function __construct(
        private FixtureSourceContext $sourceContext = new FixtureSourceContext(),
    ) {}

    public function canSelect(FixtureSource $source, FixtureGenerationOptions $options): bool
    {
        if (null === $options->rewriteRoot) {
            return true;
        }

        if (!$options->runner instanceof FixtureOriginalRunner) {
            return true;
        }

        $rewritePath = $options->rewriteRoot . DIRECTORY_SEPARATOR . $source->relativePath;
        $result = $this->sourceContext->run(
            sourcePath: $source->sourcePath,
            rewritePath: $rewritePath,
            run: fn(string $path): array => $options->runner->runOriginalFile($path),
        );

        return $result['passed'];
    }
}
