<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A row the probe writes, reads back, and deletes in one run. */
#[ORM\Entity]
#[ORM\Table(name: 'zoo_probe')]
class ProbeRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 64)]
    public string $token;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->createdAt = new \DateTimeImmutable();
    }
}
