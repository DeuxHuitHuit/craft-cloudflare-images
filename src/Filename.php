<?php

namespace deuxhuithuit\cfimages;

/**
 * @internal
 */
class Filename
{
    public const SEPARATOR = '.';

    public static function toParts(string $filename, string $accountHash = null): array
    {
        $parts = \explode(static::SEPARATOR, $filename, 3);
        if (!isset($parts[2])) {
            throw new \Exception('Invalid filename: ' . $filename);
        }
        if (!isset($parts[0]) || !isset($parts[1])) {
            throw new \Exception('Empty filename: ' . $filename);
        }
        if ($accountHash && $parts[0] !== $accountHash) {
            throw new \Exception('Invalid account hash: ' . $parts[0]);
        }
        return [
            'account' => $parts[0],
            'id' => $parts[1],
            'filename' => $parts[2],
        ];
    }

    public static function toId(string $filename, string $accountHash = null): string
    {
        $parts = static::toParts($filename, $accountHash);
        return $parts['id'];
    }

    public static function cleanParts(string $filename): string
    {
        $sep = static::SEPARATOR;
        return preg_replace("/(^|\/)[A-z0-9\\+]+\\{$sep}[a-f0-9-]+\\{$sep}/", '', $filename, 1);
    }

    public static function fromParts(string $account, string $id, string $filename): string
    {
        $sep = static::SEPARATOR;
        return "{$account}{$sep}{$id}{$sep}{$filename}";
    }
}
