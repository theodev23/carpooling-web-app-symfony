<?php

namespace App\Repository;

use App\Entity\Ride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

    public function findAvailableRides(): array
    {
        return $this->createQueryBuilder('ride')
            ->andWhere('ride.departureAt > :now')
            ->andWhere('ride.availableSeats > 0')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('ride.departureAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
