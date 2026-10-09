<?php

namespace App\Zoo;

/** Identity and helpers shared by the /_zoo endpoints (DESIGN.md). */
final class Zoo
{
    public const NAME = 'symfony-notes';
    public const STACK = 'Symfony 7 + Doctrine ORM + MariaDB';

    public static function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) ? $value : '';
    }

    /** Last 4 hex characters of sha256(value). */
    public static function fp(string $value): string
    {
        return substr(hash('sha256', $value), -4);
    }

    /** The sN label of PUBLIC_HOST, else "local". */
    public static function server(string $publicHost): string
    {
        foreach (explode('.', strtolower($publicHost)) as $label) {
            if (preg_match('/^s[0-9]+$/', $label)) {
                return $label;
            }
        }

        return 'local';
    }

    public static function release(): string
    {
        $release = self::env('OX_RELEASE');

        return '' === $release ? 'unknown' : substr($release, 0, 12);
    }

    public static function oxEnv(): string
    {
        $env = self::env('OX_ENV');

        return '' === $env ? 'local' : $env;
    }

    /** @return array{name: string, stack: string, server: string, release: string, env: string} */
    public static function identity(): array
    {
        return [
            'name' => self::NAME,
            'stack' => self::STACK,
            'server' => self::server(self::env('PUBLIC_HOST')),
            'release' => self::release(),
            'env' => self::oxEnv(),
        ];
    }

    /** @return list<string> */
    public static function panelOrigins(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $o) => '' !== $o));
    }

    /**
     * When this PHP process started (FrankenPHP serves every request from
     * one long-lived process). Linux: /proc start time; elsewhere the
     * first request this process saw.
     */
    public static function startedAt(): int
    {
        $stat = @file_get_contents('/proc/self/stat');
        $proc = @file_get_contents('/proc/stat');
        if (false !== $stat && false !== $proc && preg_match('/^btime (\d+)$/m', $proc, $m)) {
            $fields = explode(' ', substr($stat, strrpos($stat, ')') + 2));
            // Field 22 (starttime) in clock ticks; Linux uses 100 per second.
            return (int) $m[1] + intdiv((int) $fields[19], 100);
        }
        $file = sys_get_temp_dir().'/'.self::NAME.'-started-'.getmypid();
        if (!is_file($file)) {
            @file_put_contents($file, (string) time());
        }

        return (int) (@file_get_contents($file) ?: time());
    }
}
