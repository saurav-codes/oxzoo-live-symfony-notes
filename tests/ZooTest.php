<?php

namespace App\Tests;

use App\Zoo\Zoo;
use PHPUnit\Framework\TestCase;

final class ZooTest extends TestCase
{
    public function testFingerprintMatchesDesignVector(): void
    {
        self::assertSame('915a', Zoo::fp('zoo-test-key-0123456789abcdef'));
    }

    public function testServerLabelFromPublicHost(): void
    {
        self::assertSame('s3', Zoo::server('symfony-notes.s3.zoo.sorv.dev'));
        self::assertSame('local', Zoo::server(''));
        self::assertSame('local', Zoo::server('notes.example.com'));
        self::assertSame('local', Zoo::server('s3x.zoo.sorv.dev'));
    }

    public function testPanelOriginsList(): void
    {
        self::assertSame(['https://a.example', 'http://localhost:5173'], Zoo::panelOrigins(' https://a.example, ,http://localhost:5173 '));
        self::assertSame([], Zoo::panelOrigins(''));
    }
}
