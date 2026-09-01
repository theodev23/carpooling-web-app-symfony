<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Ride;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BookingCancellationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();

        parent::tearDown();
    }

    public function testPassengerCanCancelBookingAndSeatCountIncreases(): void
    {
        $driver = $this->createUser(
            'cancel-driver@example.com',
            'Driver',
        );

        $passenger = $this->createUser(
            'cancel-passenger@example.com',
            'Passenger',
        );

        $ride = $this->createRide($driver);
        $booking = $this->createBooking(
            $ride,
            $passenger,
        );

        $rideId = $ride->getId();
        $bookingId = $booking->getId();

        self::assertNotNull($rideId);
        self::assertNotNull($bookingId);

        $this->client->loginUser($passenger);

        $crawler = $this->client->request(
            'GET',
            '/bookings',
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Annuler la réservation')
            ->form();

        $this->client->submit($form);

        self::assertResponseRedirects('/bookings');

        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body',
            'Votre réservation a été annulée.',
        );

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->clear();

        $bookingCount = $entityManager
            ->getRepository(Booking::class)
            ->count([]);

        self::assertSame(
            0,
            $bookingCount,
        );

        $updatedRide = $entityManager->find(
            Ride::class,
            $rideId,
        );

        self::assertInstanceOf(
            Ride::class,
            $updatedRide,
        );

        self::assertSame(
            3,
            $updatedRide->getAvailableSeats(),
        );
    }

    public function testPassengerCannotCancelAnotherUsersBooking(): void
    {
        $driver = $this->createUser(
            'ownership-driver@example.com',
            'Driver',
        );

        $passenger = $this->createUser(
            'ownership-passenger@example.com',
            'Passenger',
        );

        $otherPassenger = $this->createUser(
            'other-passenger@example.com',
            'Other',
        );

        $ride = $this->createRide($driver);
        $booking = $this->createBooking(
            $ride,
            $passenger,
        );

        $rideId = $ride->getId();
        $bookingId = $booking->getId();
        $otherPassengerId = $otherPassenger->getId();

        self::assertNotNull($rideId);
        self::assertNotNull($bookingId);
        self::assertNotNull($otherPassengerId);

        $this->client->loginUser($passenger);

        $crawler = $this->client->request(
            'GET',
            '/bookings',
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Annuler la réservation')
            ->form();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $currentBooking = $entityManager->find(
            Booking::class,
            $bookingId,
        );

        $newPassenger = $entityManager->find(
            User::class,
            $otherPassengerId,
        );

        self::assertInstanceOf(
            Booking::class,
            $currentBooking,
        );

        self::assertInstanceOf(
            User::class,
            $newPassenger,
        );

        $currentBooking->setPassenger(
            $newPassenger,
        );

        $entityManager->flush();

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(
            Response::HTTP_FORBIDDEN,
        );

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->clear();

        $bookingCount = $entityManager
            ->getRepository(Booking::class)
            ->count([]);

        self::assertSame(
            1,
            $bookingCount,
        );

        $updatedRide = $entityManager->find(
            Ride::class,
            $rideId,
        );

        self::assertInstanceOf(
            Ride::class,
            $updatedRide,
        );

        self::assertSame(
            2,
            $updatedRide->getAvailableSeats(),
        );
    }

    public function testPassengerCannotCancelPastRideBooking(): void
    {
        $driver = $this->createUser(
            'past-driver@example.com',
            'Driver',
        );

        $passenger = $this->createUser(
            'past-passenger@example.com',
            'Passenger',
        );

        $ride = $this->createRide($driver);
        $booking = $this->createBooking(
            $ride,
            $passenger,
        );

        $rideId = $ride->getId();
        $bookingId = $booking->getId();

        self::assertNotNull($rideId);
        self::assertNotNull($bookingId);

        $this->client->loginUser($passenger);

        $crawler = $this->client->request(
            'GET',
            '/bookings',
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Annuler la réservation')
            ->form();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $currentRide = $entityManager->find(
            Ride::class,
            $rideId,
        );

        self::assertInstanceOf(
            Ride::class,
            $currentRide,
        );

        $currentRide->setDepartureAt(
            new \DateTimeImmutable('-1 day')
        );

        $entityManager->flush();

        $this->client->submit($form);

        self::assertResponseRedirects('/bookings');

        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body',
            'Un trajet déjà passé ne peut plus être annulé.',
        );

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->clear();

        $bookingCount = $entityManager
            ->getRepository(Booking::class)
            ->count([]);

        self::assertSame(
            1,
            $bookingCount,
        );

        $updatedRide = $entityManager->find(
            Ride::class,
            $rideId,
        );

        self::assertInstanceOf(
            Ride::class,
            $updatedRide,
        );

        self::assertSame(
            2,
            $updatedRide->getAvailableSeats(),
        );
    }

    private function createUser(
        string $email,
        string $firstName,
    ): User {
        $user = new User();

        $user
            ->setEmail($email)
            ->setPassword('test-password')
            ->setFirstName($firstName)
            ->setLastName('Test');

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRide(User $driver): Ride
    {
        $ride = new Ride();

        $ride
            ->setDriver($driver)
            ->setDepartureCity('Montpellier')
            ->setArrivalCity('Nîmes')
            ->setDepartureAt(
                new \DateTimeImmutable('+7 days')
            )
            ->setAvailableSeats(2)
            ->setPrice('12.50');

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->persist($ride);
        $entityManager->flush();

        return $ride;
    }

    private function createBooking(
        Ride $ride,
        User $passenger,
    ): Booking {
        $booking = new Booking();

        $booking
            ->setRide($ride)
            ->setPassenger($passenger);

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->persist($booking);
        $entityManager->flush();

        return $booking;
    }

    private function cleanDatabase(): void
    {
        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $connection = $entityManager->getConnection();

        $connection->executeStatement(
            'DELETE FROM bookings'
        );

        $connection->executeStatement(
            'DELETE FROM rides'
        );

        $connection->executeStatement(
            'DELETE FROM users'
        );

        $entityManager->clear();
    }
}
