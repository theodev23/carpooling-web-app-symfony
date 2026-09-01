<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Ride;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RideFlowTest extends WebTestCase
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

    public function testAuthenticatedUserCanCreateRide(): void
    {
        $driver = $this->createUser(
            'ride-driver@example.com',
            'Driver',
            'Test',
        );

        $driverId = $driver->getId();

        self::assertNotNull($driverId);

        $this->client->loginUser($driver);

        $crawler = $this->client->request(
            'GET',
            '/rides/new',
        );

        self::assertResponseIsSuccessful();

        $departureAt = (new \DateTimeImmutable('+10 days'))
            ->setTime(14, 30);

        $form = $crawler
            ->selectButton('Publier le trajet')
            ->form([
                'ride_form[departureCity]' => 'Montpellier',
                'ride_form[arrivalCity]' => 'Nîmes',
                'ride_form[departureAt]' => $departureAt->format(
                    'Y-m-d\TH:i'
                ),
                'ride_form[availableSeats]' => '4',
                'ride_form[price]' => '14.50',
            ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/');

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->clear();

        $rides = $entityManager
            ->getRepository(Ride::class)
            ->findAll();

        self::assertCount(
            1,
            $rides,
        );

        $createdRide = $rides[0];

        self::assertSame(
            'Montpellier',
            $createdRide->getDepartureCity(),
        );

        self::assertSame(
            'Nîmes',
            $createdRide->getArrivalCity(),
        );

        self::assertSame(
            4,
            $createdRide->getAvailableSeats(),
        );

        self::assertSame(
            '14.50',
            $createdRide->getPrice(),
        );

        self::assertSame(
            $departureAt->format('Y-m-d H:i'),
            $createdRide
                ->getDepartureAt()
                ?->format('Y-m-d H:i'),
        );

        self::assertSame(
            $driverId,
            $createdRide
                ->getDriver()
                ?->getId(),
        );
    }

    public function testRideSearchFiltersByDepartureAndArrival(): void
    {
        $driver = $this->createUser(
            'search-driver@example.com',
            'Driver',
            'Search',
        );

        $this->createRide(
            $driver,
            'Montpellier',
            'Nîmes',
            new \DateTimeImmutable('+5 days'),
        );

        $this->createRide(
            $driver,
            'Montpellier',
            'Béziers',
            new \DateTimeImmutable('+6 days'),
        );

        $this->createRide(
            $driver,
            'Sète',
            'Nîmes',
            new \DateTimeImmutable('+7 days'),
        );

        $this->client->request(
            'GET',
            '/rides?departureCity=Montpellier&arrivalCity=N%C3%AEmes',
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorCount(
            1,
            'article',
        );

        self::assertSelectorTextContains(
            'article h2',
            'Montpellier',
        );

        self::assertSelectorTextContains(
            'article h2',
            'Nîmes',
        );
    }

    public function testDriverCanSeeRidePassengers(): void
    {
        $driver = $this->createUser(
            'passenger-list-driver@example.com',
            'Claire',
            'Martin',
        );

        $firstPassenger = $this->createUser(
            'alice@example.com',
            'Alice',
            'Durand',
            '0601020304',
        );

        $secondPassenger = $this->createUser(
            'bruno@example.com',
            'Bruno',
            'Bernard',
        );

        $ride = $this->createRide(
            $driver,
            'Montpellier',
            'Nîmes',
            new \DateTimeImmutable('+8 days'),
            1,
        );

        $this->createBooking(
            $ride,
            $firstPassenger,
        );

        $this->createBooking(
            $ride,
            $secondPassenger,
        );

        $rideId = $ride->getId();

        self::assertNotNull($rideId);

        $this->client->loginUser($driver);

        $this->client->request(
            'GET',
            '/rides/' . $rideId . '/passengers',
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Passagers du trajet',
        );

        self::assertSelectorCount(
            2,
            'article',
        );

        self::assertSelectorTextContains(
            'body',
            'Alice Durand',
        );

        self::assertSelectorTextContains(
            'body',
            'alice@example.com',
        );

        self::assertSelectorTextContains(
            'body',
            '0601020304',
        );

        self::assertSelectorTextContains(
            'body',
            'Bruno Bernard',
        );

        self::assertSelectorTextContains(
            'body',
            'bruno@example.com',
        );
    }

    public function testAnotherUserCannotSeeRidePassengers(): void
    {
        $driver = $this->createUser(
            'private-driver@example.com',
            'Driver',
            'Owner',
        );

        $otherUser = $this->createUser(
            'other-user@example.com',
            'Other',
            'User',
        );

        $ride = $this->createRide(
            $driver,
            'Montpellier',
            'Nîmes',
            new \DateTimeImmutable('+9 days'),
        );

        $rideId = $ride->getId();

        self::assertNotNull($rideId);

        $this->client->loginUser($otherUser);

        $this->client->request(
            'GET',
            '/rides/' . $rideId . '/passengers',
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_FORBIDDEN,
        );
    }

    private function createUser(
        string $email,
        string $firstName,
        string $lastName,
        ?string $phone = null,
    ): User {
        $user = new User();

        $user
            ->setEmail($email)
            ->setPassword('test-password')
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhone($phone);

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRide(
        User $driver,
        string $departureCity,
        string $arrivalCity,
        \DateTimeImmutable $departureAt,
        int $availableSeats = 3,
        string $price = '12.50',
    ): Ride {
        $ride = new Ride();

        $ride
            ->setDriver($driver)
            ->setDepartureCity($departureCity)
            ->setArrivalCity($arrivalCity)
            ->setDepartureAt($departureAt)
            ->setAvailableSeats($availableSeats)
            ->setPrice($price);

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
