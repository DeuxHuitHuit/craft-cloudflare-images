<?php

namespace deuxhuithuit\cfimages;

/**
 * @internal
 */
class Filename
{
    public const SEPARATOR = '.';

    public static function toParts(string $filename): array
    {
        $parts = \explode(static::SEPARATOR, $filename, 3);
        if (!isset($parts[2])) {
            throw new \Exception('Invalid filename: ' . $filename);
        }
        return [
            'account' => $parts[0],
            'id' => $parts[1],
            'filename' => $parts[2],
        ];
    }

    public static function toId(string $filename): string
    {
        $parts = static::toParts($filename);
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
