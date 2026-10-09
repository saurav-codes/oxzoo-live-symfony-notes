<?php

namespace App\Zoo;

use App\Entity\ProbeRow;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Runs the /_zoo/probe checks: real round trips against MariaDB. */
final class Prober
{
    private const CHECK_TIMEOUT_S = 5;
    private const PROBE_TIMEOUT_S = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private readonly DependencyFactory $migrations,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $start = hrtime(true);
        $deadline = microtime(true) + self::PROBE_TIMEOUT_S;
        $checks = [
            $this->check('mysql', 'Doctrine ORM write, read, delete on MariaDB', $deadline, fn () => $this->roundTrip()),
            $this->check('migrations', 'Doctrine migrations up to date', $deadline, fn () => $this->migrationStatus()),
        ];

        return Zoo::identity() + [
            'ok' => [] === array_filter($checks, static fn (array $c) => !$c['ok']),
            'ms' => intdiv(hrtime(true) - $start, 1_000_000),
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'checks' => $checks,
            'vars' => self::vars(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function vars(): array
    {
        // APP_SECRET is Symfony's own, shared with no peer: the kernel
        // refuses to serve without it, so it is not listed here.
        $mysql = Zoo::env('MYSQL_URL');
        $vars = ['' === $mysql ? ['name' => 'MYSQL_URL', 'missing' => true, 'role' => 'service'] : ['name' => 'MYSQL_URL', 'fp' => Zoo::fp($mysql), 'role' => 'service']];
        $panel = Zoo::env('ZOO_PANEL_ORIGIN');
        $vars[] = '' === $panel ? ['name' => 'ZOO_PANEL_ORIGIN', 'missing' => true, 'role' => 'plain'] : ['name' => 'ZOO_PANEL_ORIGIN', 'value' => $panel, 'role' => 'plain'];

        return $vars;
    }

    /** @return array<string, mixed> */
    private function check(string $id, string $label, float $deadline, callable $fn): array
    {
        $result = ['id' => $id, 'label' => $label, 'ok' => false, 'ms' => 0, 'env' => ['MYSQL_URL'], 'hops' => [Zoo::NAME.'@'.Zoo::server(Zoo::env('PUBLIC_HOST'))]];
        $start = hrtime(true);
        try {
            if ('' === Zoo::env('MYSQL_URL')) {
                throw new \RuntimeException('MYSQL_URL is not set');
            }
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('probe timeout after '.self::PROBE_TIMEOUT_S.' s');
            }
            $result['detail'] = $fn();
            $result['ok'] = true;
        } catch (\Throwable $e) {
            // Driver messages can carry the DSN; keep only the first line, without credentials.
            $result['error'] = preg_replace('#//[^@/\s]*@#', '//***@', strtok($e->getMessage(), "\n") ?: $e::class);
        }
        $result['ms'] = intdiv(hrtime(true) - $start, 1_000_000);
        if ($result['ok'] && $result['ms'] > self::CHECK_TIMEOUT_S * 1000) {
            $result['ok'] = false;
            $result['error'] = 'timeout after '.(self::CHECK_TIMEOUT_S * 1000).' ms';
        }

        return $result;
    }

    private function roundTrip(): string
    {
        $conn = $this->em->getConnection();
        // MariaDB caps each statement of this session at the check timeout.
        $conn->executeStatement('SET SESSION max_statement_time = '.self::CHECK_TIMEOUT_S);
        $token = bin2hex(random_bytes(16));
        $row = new ProbeRow($token);
        $this->em->persist($row);
        $this->em->flush();
        $id = $row->id;
        $this->em->clear();
        try {
            $read = $this->em->find(ProbeRow::class, $id);
            if (null === $read || $read->token !== $token) {
                throw new \RuntimeException('read back a different row');
            }
            $this->em->remove($read);
            $this->em->flush();
        } finally {
            // Cleanup even when the read failed (no-op after the remove).
            $conn->executeStatement('DELETE FROM zoo_probe WHERE id = ?', [$id]);
        }
        if (false !== $conn->fetchOne('SELECT id FROM zoo_probe WHERE id = ?', [$id])) {
            throw new \RuntimeException('row still present after delete');
        }

        return 'zoo_probe row round trip, server '.$conn->fetchOne('SELECT VERSION()');
    }

    private function migrationStatus(): string
    {
        $calc = $this->migrations->getMigrationStatusCalculator();
        $pending = \count($calc->getNewMigrations());
        $unknown = \count($calc->getExecutedUnavailableMigrations());
        $total = \count($this->migrations->getMigrationRepository()->getMigrations());
        if ($pending > 0 || $unknown > 0) {
            throw new \RuntimeException(\sprintf('%d pending, %d applied but unknown to this release', $pending, $unknown));
        }

        return \sprintf('%d of %d applied', $total, $total);
    }
}
