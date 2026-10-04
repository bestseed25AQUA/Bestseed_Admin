<?php

namespace App\Support;

/**
 * What this server will actually accept as an upload.
 *
 * Three numbers decide it and the smallest wins:
 *
 *   upload_max_filesize  the file itself
 *   post_max_size        the whole request, file plus every other field
 *   our own cap          a product decision, not a server one
 *
 * They are read at runtime rather than written down, because the answer
 * changes with php.ini and a hardcoded figure would start lying the moment
 * somebody edited it — which is exactly what the admin is told to do when an
 * upload is refused.
 */
class UploadLimit
{
    /** Bytes allowed for one uploaded file, after every limit is applied. */
    public static function bytes(?int $ourCapMb = null): int
    {
        $limits = array_filter([
            self::iniBytes('upload_max_filesize'),
            // Headroom for the other form fields, which also count towards
            // post_max_size. A file exactly at the limit still fails without
            // it, which reads as the limit being wrong.
            self::iniBytes('post_max_size') - 512 * 1024,
            $ourCapMb ? $ourCapMb * 1024 * 1024 : null,
        ], fn ($v) => $v !== null && $v > 0);

        return $limits ? (int) min($limits) : 2 * 1024 * 1024;
    }

    /** The same figure in whole megabytes, for a validation rule. */
    public static function megabytes(?int $ourCapMb = null): int
    {
        return max(1, (int) floor(self::bytes($ourCapMb) / (1024 * 1024)));
    }

    /** For a message meant to be read, e.g. "7 MB". */
    public static function label(?int $ourCapMb = null): string
    {
        return self::megabytes($ourCapMb) . ' MB';
    }

    /** Whether php.ini, rather than our own cap, is the binding constraint. */
    public static function cappedByServer(int $ourCapMb): bool
    {
        return self::megabytes($ourCapMb) < $ourCapMb;
    }

    /**
     * An ini size ("8M", "512K", "1G") in bytes. Null when unset or unlimited.
     */
    private static function iniBytes(string $key): ?int
    {
        $raw = trim((string) ini_get($key));

        if ($raw === '' || $raw === '-1' || $raw === '0') {
            return null;
        }

        $unit  = strtolower(substr($raw, -1));
        $value = (int) $raw;

        return match ($unit) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }
}
