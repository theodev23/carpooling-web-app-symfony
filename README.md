# Car-naval

[![CI](https://github.com/theodev23/carpooling-web-app-symfony/actions/workflows/ci.yml/badge.svg)](https://github.com/theodev23/carpooling-web-app-symfony/actions/workflows/ci.yml)

Car-naval is a carpooling web application built with Symfony, Doctrine ORM and MariaDB.

This repository is a modernization of an earlier academic application originally developed with procedural PHP, PDO and MySQL. The objective was to preserve the original carpooling use case while redesigning the application with a structured Symfony architecture, explicit business rules, stronger data integrity, automated functional tests and continuous integration.

## Features

| Area | Features |
| --- | --- |
| Authentication | Registration, login and logout |
| Rides | Create a ride, list available rides and view personal rides |
| Search | Filter available rides by departure city, arrival city and date |
| Booking | Book an available ride and view personal bookings |
| Cancellation | Cancel a future booking and automatically restore a seat |
| Driver view | View the passengers registered for one of your rides |
| Authorization | Protected personal pages and ownership checks |

## Screenshots

### Ride discovery

Search upcoming rides, view availability and booking status, and distinguish between available, already booked, and personally created rides.

![Ride discovery](docs/screenshots/rides.png)

### Driver passenger view

Drivers can review the passengers registered for one of their rides, including their contact information.

![Ride passengers](docs/screenshots/passengers.png)

### Passenger reservations

Passengers can review their upcoming bookings and cancel eligible reservations.

![Passenger reservations](docs/screenshots/bookings.png)

## Tech stack

| Category | Technologies |
| --- | --- |
| Backend | PHP, Symfony 7.4 |
| Persistence | Doctrine ORM, Doctrine Migrations |
| Database | MariaDB / MySQL |
| Frontend | Twig, HTML, CSS |
| Forms & validation | Symfony Form, Symfony Validator |
| Security | Symfony Security, password hashing, CSRF protection |
| Testing | PHPUnit, Symfony BrowserKit, DomCrawler |
| CI | GitHub Actions |
| Dependency management | Composer |

The project requires PHP `>= 8.2`. The GitHub Actions pipeline currently runs with PHP 8.4 and MariaDB 10.11.

## Business rules and data integrity

The booking workflow contains several server-side safeguards:

- a driver cannot book their own ride;
- the same passenger cannot book the same ride twice;
- a full ride cannot receive another booking;
- booking and cancellation requests are protected by CSRF tokens;
- only the passenger who owns a booking can cancel it;
- a booking for a past ride cannot be cancelled;
- cancelling a valid booking restores one available seat;
- a database unique constraint protects the `(ride, passenger)` pair;
- booking and cancellation operations run inside database transactions;
- pessimistic write locks protect seat updates from concurrent modifications.

Authorization rules are checked again on the server when an action is submitted. The application therefore does not rely only on what was previously displayed in the user interface.

## Data model

```mermaid
erDiagram
    USER ||--o{ RIDE : drives
    USER ||--o{ BOOKING : makes
    RIDE ||--o{ BOOKING : receives

    USER {
        int id
        string email
        string password
        string firstName
        string lastName
        string phone
    }

    RIDE {
        int id
        string departureCity
        string arrivalCity
        datetime departureAt
        int availableSeats
        decimal price
    }

    BOOKING {
        int id
        int ride_id
        int passenger_id
    }
```

The application uses three main Doctrine entities:

`User` represents registered users, `Ride` represents rides offered by drivers, and `Booking` links a passenger to a ride.

## Project structure

```text
.
├── .github/
│   └── workflows/
│       └── ci.yml
├── migrations/
├── public/
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Form/
│   └── Repository/
├── templates/
├── tests/
│   └── Controller/
├── composer.json
├── phpunit.dist.xml
└── symfony.lock
```

The application follows standard Symfony responsibilities: controllers handle HTTP flows, entities represent persisted data, repositories contain database queries, forms handle user input and Twig templates render the interface.

## Automated tests

The current functional test suite contains **16 tests and 125 assertions**.

| Test area | Scenarios |
| --- | ---: |
| Anonymous access control | 2 |
| Authenticated access | 2 |
| Booking workflow and safeguards | 5 |
| Booking cancellation | 3 |
| Ride workflow and passenger access | 4 |
| **Total** | **16** |

The tests exercise real HTTP requests through Symfony `WebTestCase` and BrowserKit. Business scenarios also verify the resulting Doctrine state, including booking creation/deletion and seat-count consistency.

Examples of covered scenarios include a valid booking, duplicate-booking rejection, full-ride rejection, invalid CSRF rejection, valid cancellation, cancellation ownership, ride creation, ride search and passenger-list authorization.

## Continuous integration

GitHub Actions runs automatically on pushes and pull requests targeting `main`.

The CI pipeline:

1. starts an Ubuntu runner and a MariaDB service;
2. configures PHP 8.4 and Composer;
3. validates `composer.json`;
4. installs the project dependencies;
5. runs Doctrine migrations;
6. validates the Doctrine schema;
7. executes the complete PHPUnit suite.

The CI workflow is available in [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

## Getting started

### Requirements

You need PHP `>= 8.2`, Composer 2 and a MariaDB/MySQL server.

Clone the repository and install the dependencies:

```bash
git clone https://github.com/theodev23/carpooling-web-app-symfony.git
cd carpooling-web-app-symfony
composer install
```

Create a local environment file such as `.env.local` and configure your database connection:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/carpooling_symfony?serverVersion=10.11.0-MariaDB&charset=utf8mb4"
```

Adjust `serverVersion` to match your local MariaDB version.

Create the database and apply the migrations:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

If Symfony CLI is installed, start the application with:

```bash
symfony server:start
```

Then open `http://127.0.0.1:8000`.

## Running the tests locally

The test environment uses Doctrine's `_test` database suffix.

Create `.env.test.local` with your local database credentials while keeping `carpooling_symfony` as the base database name:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/carpooling_symfony?serverVersion=10.11.0-MariaDB&charset=utf8mb4"
```

Doctrine resolves the physical test database as `carpooling_symfony_test`.

Prepare it with:

```bash
APP_ENV=test php bin/console doctrine:database:create --if-not-exists
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
```

Run the complete suite:

```bash
php bin/phpunit
```

## What this project demonstrates

Car-naval focuses on the modernization of an existing application rather than on adding unnecessary architectural complexity.

It demonstrates how a procedural PHP/MySQL project can evolve toward a structured Symfony application with Doctrine persistence, explicit authorization and business rules, transactional consistency, automated functional testing and continuous integration.
