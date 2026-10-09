<?php

namespace App;

use App\Zoo\Zoo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // A production web process without a secret must not serve; the
        // build and the migrate step (CLI) run before the operator sets it.
        if ('prod' === $this->environment && 'cli' !== \PHP_SAPI && '' === Zoo::env('APP_SECRET')) {
            throw new \RuntimeException('APP_SECRET is not set');
        }
        parent::boot();
    }
}
