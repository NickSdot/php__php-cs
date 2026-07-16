<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function gmdate;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;

final readonly class PhpBuildMetadata
{
    public function __construct(
        public string $phpSrcDir,
        public string $head,
        public string $statusHash,
        public string $profileSignature,
    ) {}

    public static function read(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new \RuntimeException('Cannot read PHP build metadata: ' . $path);
        }

        $data = json_decode($contents, associative: true);

        if (!is_array($data)) {
            return null;
        }

        foreach (['php_src_dir', 'head', 'status_hash', 'profile_signature'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                return null;
            }
        }

        return new self(
            phpSrcDir: $data['php_src_dir'],
            head: $data['head'],
            statusHash: $data['status_hash'],
            profileSignature: $data['profile_signature'],
        );
    }

    public function matches(self $other): bool
    {
        return $this->phpSrcDir === $other->phpSrcDir
            && $this->head === $other->head
            && $this->statusHash === $other->statusHash
            && $this->profileSignature === $other->profileSignature;
    }

    public function write(string $path): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0o777, true)) {
            throw new \RuntimeException('Cannot create PHP build metadata directory: ' . $dir);
        }

        $json = json_encode([
            'php_src_dir' => $this->phpSrcDir,
            'head' => $this->head,
            'status_hash' => $this->statusHash,
            'profile_signature' => $this->profileSignature,
            'built_at' => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (false === $json || false === file_put_contents($path, $json . "\n")) {
            throw new \RuntimeException('Cannot write PHP build metadata: ' . $path);
        }
    }
}
