<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Ride;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingFlowTest extends WebTestCase
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

    public function testPassengerCanBookRideAndSeatCountDecreases(): void
    {
        $driver = $this->createUser(
            'driver@example.com',
            'Driver',
        );

        $passenger = $this->createUser(
            'passenger@example.com',
            'Passenger',
        );

        $ride = $this->createRide($driver);

        $rideId = $ride->getId();
        $passengerId = $passenger->getId();

        self::assertNotNull($rideId);
        self::assertNotNull($passengerId);

        $this->client->loginUser($passenger);

        $crawler = $this->client->request(
            'GET',
            '/rides',
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Réserver')
            ->form();

        $this->client->submit($form);

        self::assertResponseRedirects('/rides');

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->clear();

        $bookings = $entityManager
            ->getRepository(Booking::class)
            ->findAll();

        self::assertCount(1, $bookings);

        $booking = $bookings[0];

        self::assertSame(
            $rideId,
            $booking->getRide()?->getId(),
        );

        self::assertSame(
            $passengerId,
            $booking->getPassenger()?->getId(),
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

    public function testPassengerCannotBookSameRideTwice(): void
    {
        $driver = $this->createUser(
            'duplicate-driver@example.com',
            'Driver',
        );

        $passenger = $this->createUser(
            'duplicate-passenger@example.com',
            'Passenger',
        );

        $ride = $this->createRide($driver);

        $rideId = $ride->getId();

        self::assertNotNull($rideId);

        $this->client->loginUser($passenger);

        $crawler = $this->client->request(
            'GET',
            '/rides',
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Réserver')
            ->form();

        $this->client->submit($form);

        self::assertResponseRedirects('/rides');

        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();

        $secondForm = $crawler
            ->selectButton('Réserver')
            ->form();

        $this->client->submit($secondForm);

        self::assertResponseRedirects('/rides');

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

    public function testDriverCannotBookOwnRide(): void
    {
        $temporaryDriver = $this->createUser(
            'temporary-driver@example.com',
            'Temporary',
        );

        $futureDriver = $this->createUser(
            'future-driver@example.com',
            'Owner',
        );

        $ride = $this->createRide($temporaryDriver);

        $rideId = $ride->getId();
        $futureDriverId = $futureDriver->getId();

        self::assertNotNull($rideId);
        self::assertNotNull($futureDriverId);

        $this->client->loginUser($futureDriver);

        $crawler = $this->client->request(
            'GET',
            '/rides',
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Réserver')
            ->form();

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $currentRide = $entityManager->find(
            Ride::class,
            $rideId,
        );

        $currentDriver = $entityManager->find(
            User::class,
            $futureDriverId,
        );

        self::assertInstanceOf(
            Ride::class,
            $currentRide,
        );

        self::assertInstanceOf(
            User::class,
            $currentDriver,
        );

        $currentRide->setDriver($currentDriver);

        $entityManager->flush();

        $this->client->submit($form);

        self::assertResponseRedirects('/rides');

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
            ->setAvailableSeats(3)
            ->setPrice('12.50');

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->persist($ride);
        $entityManager->flush();

        return $ride;
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
