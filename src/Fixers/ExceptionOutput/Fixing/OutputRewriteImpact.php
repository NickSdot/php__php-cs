<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\TextEdit;
use PhpParser\Node\Scalar\String_;

use function array_all;
use function is_string;
use function mb_substr;
use function token_get_all;

final readonly class OutputRewriteImpact
{
    public function isNormalizationOnly(string $code, OutputRewritePlan $plan): bool
    {
        if ([] === $plan->all()) {
            return false;
        }

        return array_all($plan->outputEdits, fn($edit) => $this->isNormalization($code, $edit));
    }

    private function isNormalization(string $code, TextEdit $edit): bool
    {
        $before = mb_substr($code, $edit->startOffset, $edit->endOffset - $edit->startOffset, '8bit');

        return $this->normalizedTokens($before) === $this->normalizedTokens($edit->replacement);
    }

    /** @return list<string|array{int|string, string}> */
    private function normalizedTokens(string $code): array
    {
        $normalized = [];

        foreach (token_get_all("<?php\n" . $code) as $token) {

            if (is_string($token)) {
                $normalized[] = $token;
                continue;
            }

            [$id, $text] = $token;

            if (T_CONSTANT_ENCAPSED_STRING === $id) {
                $normalized[] = ['string', String_::fromString($text)->value];
                continue;
            }

            $normalized[] = T_STRING === $id && 'PHP_EOL' === $text
                ? ['string', "\n"]
                : [$id, $text];
        }

        return $normalized;
    }
}
