<?php

declare(strict_types=1);

$target = $argv[\array_key_last($argv)] ?? '';
$contents = \file_get_contents($target);
$old = \file_get_contents((string) \getenv('FIXTURE_OLD_PHPT'));
$new = \file_get_contents((string) \getenv('FIXTURE_NEW_PHPT'));

if ($contents === $old || expectedSection($contents) === expectedSection($new)) {
    echo "PASS $target\n";
    exit(0);
}

\file_put_contents(artifactPath($target, 'out'), actualOutput($new));
echo "FAIL $target\n";
exit(1);

function artifactPath(string $target, string $extension): string
{
    $info = \pathinfo($target);

    return $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.' . $extension;
}

function expectedSection(string $phpt): string
{
    if (1 === \preg_match('/^--EXPECT--[ \t]*(?:\r\n|\n|\r)(.*?)(?=^--[A-Z_]+--|\z)/ms', $phpt, $matches)) {
        return $matches[1];
    }

    if (1 === \preg_match('/^--EXPECTF--[ \t]*(?:\r\n|\n|\r)(.*?)(?=^--[A-Z_]+--|\z)/ms', $phpt, $matches)) {
        return $matches[1];
    }

    \fwrite(STDERR, "No EXPECT or EXPECTF section in fixture target\n");
    exit(1);
}

function actualOutput(string $phpt): string
{
    if (1 === \preg_match('/^--EXPECT--[ \t]*(?:\r\n|\n|\r)(.*?)(?=^--[A-Z_]+--|\z)/ms', $phpt, $matches)) {
        return $matches[1];
    }

    if (1 === \preg_match('/^--EXPECTF--[ \t]*(?:\r\n|\n|\r)(.*?)(?=^--[A-Z_]+--|\z)/ms', $phpt, $matches)) {
        return concreteExpectf($matches[1]);
    }

    \fwrite(STDERR, "No EXPECT or EXPECTF section in fixture target\n");
    exit(1);
}

function concreteExpectf(string $expected): string
{
    $output = '';
    $length = \mb_strlen($expected);

    for ($i = 0; $i < $length; $i++) {
        if ('%' !== $expected[$i] || $i + 1 >= $length) {
            $output .= $expected[$i];
            continue;
        }

        $placeholder = $expected[++$i];

        if ('r' === $placeholder) {
            $end = \mb_strpos($expected, '%r', $i + 1);

            if (false !== $end) {
                $output .= \mb_substr($expected, $i + 1, $end - $i - 1);
                $i = $end + 1;
                continue;
            }
        }

        $output .= match ($placeholder) {
            '%' => '%',
            '0' => "\0",
            'e' => DIRECTORY_SEPARATOR,
            's', 'S' => __FILE__,
            'a' => 'anything',
            'A' => "anything\nmultiple lines",
            'w' => '',
            'i', 'd' => '123',
            'x' => '7b',
            'f' => '1.5',
            'c' => 'x',
            default => '%' . $placeholder,
        };
    }

    return $output;
}
