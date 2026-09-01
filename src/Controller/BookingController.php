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
        '/bookings',
        name: 'app_booking_index',
        methods: ['GET'],
    )]
    public function index(
        BookingRepository $bookingRepository,
    ): Response {
        $passenger = $this->getUser();

        if (!$passenger instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render(
            'booking/index.html.twig',
            [
                'bookings' => $bookingRepository
                    ->findForPassenger($passenger),
            ],
        );
    }

    #[Route(
        '/bookings/{id}/cancel',
        name: 'app_booking_cancel',
        requirements: ['id' => '\\d+'],
        methods: ['POST'],
    )]
    public function cancel(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $passenger = $this->getUser();

        if (!$passenger instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $token = (string) $request->request->get('_token');

        if (
            !$this->isCsrfTokenValid(
                'cancel_booking_' . $id,
                $token,
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        $result = $entityManager
            ->getConnection()
            ->transactional(
                function () use (
                    $id,
                    $passenger,
                    $entityManager,
                ): string {
                    $booking = $entityManager->find(
                        Booking::class,
                        $id,
                        LockMode::PESSIMISTIC_WRITE,
                    );

                    if (!$booking instanceof Booking) {
                        throw $this->createNotFoundException(
                            'Réservation introuvable.'
                        );
                    }

                    if (
                        $booking->getPassenger()?->getId()
                        !== $passenger->getId()
                    ) {
                        throw $this->createAccessDeniedException(
                            'Cette réservation ne vous appartient pas.'
                        );
                    }

                    $rideId = $booking->getRide()?->getId();

                    if ($rideId === null) {
                        throw $this->createNotFoundException(
                            'Trajet introuvable.'
                        );
                    }

                    $ride = $entityManager->find(
                        Ride::class,
                        $rideId,
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

                    $availableSeats = $ride->getAvailableSeats();

                    if ($availableSeats === null) {
                        return 'invalid';
                    }

                    $ride->setAvailableSeats(
                        $availableSeats + 1
                    );

                    $entityManager->remove($booking);
                    $entityManager->flush();

                    return 'cancelled';
                },
            );

        if ($result === 'cancelled') {
            $this->addFlash(
                'success',
                'Votre réservation a été annulée.'
            );
        }

        if ($result === 'past') {
            $this->addFlash(
                'error',
                'Un trajet déjà passé ne peut plus être annulé.'
            );
        }

        if ($result === 'invalid') {
            $this->addFlash(
                'error',
                'La réservation ne peut pas être annulée.'
            );
        }

        return $this->redirectToRoute(
            'app_booking_index'
        );
    }

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
