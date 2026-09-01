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
