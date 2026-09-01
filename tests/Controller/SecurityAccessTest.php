<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SecurityAccessTest extends WebTestCase
{
    public function testAnonymousUserIsRedirectedFromBookings(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/bookings',
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_FOUND,
        );

        $location = $client
            ->getResponse()
            ->headers
            ->get('Location');

        self::assertNotNull($location);
        self::assertStringEndsWith(
            '/login',
            $location,
        );
    }

    public function testAnonymousUserIsRedirectedFromMyRides(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/rides/mine',
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_FOUND,
        );

        $location = $client
            ->getResponse()
            ->headers
            ->get('Location');

        self::assertNotNull($location);
        self::assertStringEndsWith(
            '/login',
            $location,
        );
    }
}
