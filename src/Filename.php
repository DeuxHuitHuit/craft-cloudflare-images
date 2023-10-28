<?php

namespace deuxhuithuit\cfimages;

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

    public static function tryToFilename(string $filename): string
    {
        try {
            $parts = static::toParts($filename);
            return $parts['filename'];
        } catch (\Throwable $e) {
            return $filename;
        }
    }

    public static function fromParts($account, $id, $filename): string
    {
        $sep = static::SEPARATOR;
        return "{$account}{$sep}{$id}{$sep}{$filename}";
    }
}
