<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

final readonly class OutputPart
{
    private const string NEWLINE_MARKER = '\n';
    public const string SOURCE_INTERPOLATED_STRING = 'interpolated_string';

    private function __construct(
        public OutputPartKind $kind,
        public string $value = '',
        public ?string $variable = null,
        public ?string $source = null,
        public ?string $newlineSource = null,
    ) {}

    public static function literal(string $value, ?string $source = null): self
    {
        $newlineSource = match ($value) {
            "\r\n" => '"\r\n"',
            "\n" => '"\n"',
            "\r" => '"\r"',
            default => null,
        };

        if (null !== $newlineSource) {
            return new self(OutputPartKind::Newline, self::NEWLINE_MARKER, newlineSource: $newlineSource);
        }

        return new self(OutputPartKind::Literal, $value, source: $source);
    }

    public static function phpEol(): self
    {
        return new self(OutputPartKind::Newline, self::NEWLINE_MARKER, newlineSource: 'PHP_EOL');
    }

    public static function otherVariable(string $variable, ?string $source = null): self
    {
        return new self(OutputPartKind::OtherVariable, variable: $variable, source: $source);
    }

    public static function otherExpression(string $source): self
    {
        return new self(OutputPartKind::OtherExpression, source: $source);
    }

    public static function exceptionClass(string $variable, string $source): self
    {
        return new self(OutputPartKind::ExceptionClass, variable: $variable, source: $source);
    }

    public static function exceptionMessage(string $variable): self
    {
        return new self(OutputPartKind::ExceptionMessage, variable: $variable);
    }

    public static function exceptionCode(string $variable): self
    {
        return new self(OutputPartKind::ExceptionCode, variable: $variable);
    }

    public static function exceptionFile(string $variable): self
    {
        return new self(OutputPartKind::ExceptionFile, variable: $variable);
    }

    public static function exceptionLine(string $variable): self
    {
        return new self(OutputPartKind::ExceptionLine, variable: $variable);
    }

    public static function exceptionTrace(string $variable): self
    {
        return new self(OutputPartKind::ExceptionTrace, variable: $variable);
    }

    public static function unknown(string $source): self
    {
        return new self(OutputPartKind::Unknown, source: $source);
    }
}
