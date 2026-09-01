<?php

namespace App\Repository;

use App\Entity\Ride;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

    public function findForDriver(User $driver): array
    {
        return $this->createQueryBuilder('ride')
            ->andWhere('ride.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('ride.departureAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAvailableRides(
        ?string $departureCity = null,
        ?string $arrivalCity = null,
        ?\DateTimeImmutable $date = null,
    ): array {
        $queryBuilder = $this->createQueryBuilder('ride')
            ->andWhere('ride.departureAt > :now')
            ->andWhere('ride.availableSeats > 0')
            ->setParameter('now', new \DateTimeImmutable());

        if ($departureCity) {
            $queryBuilder
                ->andWhere('LOWER(ride.departureCity) LIKE :departureCity')
                ->setParameter(
                    'departureCity',
                    '%' . mb_strtolower($departureCity) . '%',
                );
        }

        if ($arrivalCity) {
            $queryBuilder
                ->andWhere('LOWER(ride.arrivalCity) LIKE :arrivalCity')
                ->setParameter(
                    'arrivalCity',
                    '%' . mb_strtolower($arrivalCity) . '%',
                );
        }

        if ($date) {
            $startOfDay = $date->setTime(0, 0);
            $endOfDay = $startOfDay->modify('+1 day');

            $queryBuilder
                ->andWhere('ride.departureAt >= :dateStart')
                ->andWhere('ride.departureAt < :dateEnd')
                ->setParameter('dateStart', $startOfDay)
                ->setParameter('dateEnd', $endOfDay);
        }

        return $queryBuilder
            ->orderBy('ride.departureAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
