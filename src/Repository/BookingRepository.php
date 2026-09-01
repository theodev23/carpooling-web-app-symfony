<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findForPassenger(User $passenger): array
    {
        return $this->createQueryBuilder('booking')
            ->innerJoin('booking.ride', 'ride')
            ->addSelect('ride')
            ->andWhere('booking.passenger = :passenger')
            ->setParameter('passenger', $passenger)
            ->orderBy('ride.departureAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
