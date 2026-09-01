<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthenticatedAccessTest extends WebTestCase
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

    public function testAuthenticatedUserCanAccessBookings(): void
    {
        $user = $this->createUser(
            'bookings@example.com',
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/bookings',
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Mes réservations',
        );
    }

    public function testAuthenticatedUserCanAccessMyRides(): void
    {
        $user = $this->createUser(
            'rides@example.com',
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/rides/mine',
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Mes trajets proposés',
        );
    }

    private function createUser(string $email): User
    {
        $user = new User();

        $user
            ->setEmail($email)
            ->setPassword('test-password')
            ->setFirstName('Test')
            ->setLastName('User');

        $entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
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
