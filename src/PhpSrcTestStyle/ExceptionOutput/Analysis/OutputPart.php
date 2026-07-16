<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

final readonly class OutputPart
{
    private function __construct(
        public OutputPartKind $kind,
        public string $value = '',
        public ?string $variable = null,
        public ?string $source = null,
    ) {}

    public static function literal(string $value): self
    {
        if ("\n" === $value || "\r\n" === $value || "\r" === $value) {
            return self::newline();
        }

        return new self(OutputPartKind::Literal, $value);
    }

    public static function newline(): self
    {
        return new self(OutputPartKind::Newline, '\n');
    }

    public static function exceptionClass(string $variable, string $source): self
    {
        return new self(OutputPartKind::ExceptionClass, variable: $variable, source: $source);
    }

    public static function exceptionMessage(string $variable): self
    {
        return new self(OutputPartKind::ExceptionMessage, variable: $variable);
    }

    public static function exceptionFile(string $variable): self
    {
        return new self(OutputPartKind::ExceptionFile, variable: $variable);
    }

    public static function exceptionLine(string $variable): self
    {
        return new self(OutputPartKind::ExceptionLine, variable: $variable);
    }

    public static function unknown(string $source): self
    {
        return new self(OutputPartKind::Unknown, source: $source);
    }
}
