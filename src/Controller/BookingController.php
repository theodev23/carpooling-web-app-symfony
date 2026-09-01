<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Ride;
use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    #[Route(
        '/rides/{id}/book',
        name: 'app_ride_book',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function book(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        BookingRepository $bookingRepository,
    ): Response {
        $passenger = $this->getUser();

        if (!$passenger instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('book_ride_' . $id, $token)) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        try {
            $result = $entityManager
                ->getConnection()
                ->transactional(
                    function () use (
                        $id,
                        $passenger,
                        $entityManager,
                        $bookingRepository,
                    ): string {
                        $ride = $entityManager->find(
                            Ride::class,
                            $id,
                            LockMode::PESSIMISTIC_WRITE,
                        );

                        if (!$ride instanceof Ride) {
                            throw $this->createNotFoundException(
                                'Trajet introuvable.'
                            );
                        }

                        $departureAt = $ride->getDepartureAt();

                        if (
                            !$departureAt
                            || $departureAt <= new \DateTimeImmutable()
                        ) {
                            return 'past';
                        }

                        if (
                            $ride->getDriver()?->getId()
                            === $passenger->getId()
                        ) {
                            return 'own_ride';
                        }

                        $existingBooking = $bookingRepository->findOneBy([
                            'ride' => $ride,
                            'passenger' => $passenger,
                        ]);

                        if ($existingBooking) {
                            return 'already_booked';
                        }

                        $availableSeats = $ride->getAvailableSeats();

                        if (
                            $availableSeats === null
                            || $availableSeats <= 0
                        ) {
                            return 'full';
                        }

                        $booking = new Booking();

                        $booking
                            ->setRide($ride)
                            ->setPassenger($passenger);

                        $ride->setAvailableSeats(
                            $availableSeats - 1
                        );

                        $entityManager->persist($booking);
                        $entityManager->flush();

                        return 'booked';
                    },
                );
        } catch (UniqueConstraintViolationException) {
            $result = 'already_booked';
        }

        if ($result === 'booked') {
            $this->addFlash(
                'success',
                'Votre réservation est confirmée.'
            );
        }

        if ($result === 'past') {
            $this->addFlash(
                'error',
                'Ce trajet n\'est plus réservable.'
            );
        }

        if ($result === 'own_ride') {
            $this->addFlash(
                'error',
                'Vous ne pouvez pas réserver votre propre trajet.'
            );
        }

        if ($result === 'already_booked') {
            $this->addFlash(
                'error',
                'Vous avez déjà réservé ce trajet.'
            );
        }

        if ($result === 'full') {
            $this->addFlash(
                'error',
                'Ce trajet n\'a plus de place disponible.'
            );
        }

        return $this->redirectToRoute('app_ride_index');
    }
}
