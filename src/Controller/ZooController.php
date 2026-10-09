<?php

namespace App\Controller;

use App\Zoo\Prober;
use App\Zoo\Zoo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ZooController
{
    private const BUSY_WAIT_S = 5;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    #[Route('/_zoo/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $started = Zoo::startedAt();
        $build = ['runtime' => 'php '.\PHP_VERSION];
        $info = @file_get_contents($this->projectDir.'/var/build.json');
        if (false !== $info && \is_array($data = json_decode($info, true)) && \is_string($data['built_at'] ?? null)) {
            $build = ['built_at' => $data['built_at']] + $build;
        }

        return new JsonResponse(Zoo::identity() + [
            'uptime_s' => max(0, time() - $started),
            'started_at' => gmdate('Y-m-d\TH:i:s\Z', $started),
            'build' => $build,
        ]);
    }

    #[Route('/_zoo/probe', methods: ['GET'])]
    public function probe(Prober $prober): JsonResponse
    {
        // One probe at a time in this process: FrankenPHP threads share
        // the process, so a lock file serializes them.
        $lock = fopen(sys_get_temp_dir().'/'.Zoo::NAME.'-probe-'.getmypid().'.lock', 'c');
        if (false === $lock) {
            return new JsonResponse(['error' => 'probe lock unavailable'], 500);
        }
        $until = microtime(true) + self::BUSY_WAIT_S;
        while (!flock($lock, \LOCK_EX | \LOCK_NB)) {
            if (microtime(true) >= $until) {
                fclose($lock);

                return new JsonResponse(['error' => 'probe busy'], 429);
            }
            usleep(50_000);
        }
        try {
            return new JsonResponse($prober->run());
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }
}
