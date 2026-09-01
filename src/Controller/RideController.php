<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Form\RideFormType;
use App\Repository\RideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RideController extends AbstractController
{
    #[Route('/rides/mine', name: 'app_ride_mine', methods: ['GET'])]
    public function mine(
        RideRepository $rideRepository,
    ): Response {
        $driver = $this->getUser();

        if (!$driver instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render(
            'ride/mine.html.twig',
            [
                'rides' => $rideRepository->findForDriver($driver),
            ],
        );
    }

    #[Route('/rides', name: 'app_ride_index', methods: ['GET'])]
    public function index(
        Request $request,
        RideRepository $rideRepository,
    ): Response {
        $departureCity = trim(
            (string) $request->query->get('departureCity', '')
        );

        $arrivalCity = trim(
            (string) $request->query->get('arrivalCity', '')
        );

        $dateInput = trim(
            (string) $request->query->get('date', '')
        );

        $date = null;
        $dateError = null;

        if ($dateInput !== '') {
            $date = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateInput,
            );

            if (
                !$date
                || $date->format('Y-m-d') !== $dateInput
            ) {
                $date = null;
                $dateError = 'Veuillez saisir une date valide.';
            }
        }

        $rides = [];

        if (!$dateError) {
            $rides = $rideRepository->findAvailableRides(
                $departureCity ?: null,
                $arrivalCity ?: null,
                $date,
            );
        }

        return $this->render(
            'ride/index.html.twig',
            [
                'rides' => $rides,
                'departureCity' => $departureCity,
                'arrivalCity' => $arrivalCity,
                'date' => $dateInput,
                'dateError' => $dateError,
            ],
        );
    }

    #[Route('/rides/new', name: 'app_ride_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $driver = $this->getUser();

        if (!$driver instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $ride = new Ride();

        $form = $this->createForm(
            RideFormType::class,
            $ride,
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ride->setDriver($driver);

            $entityManager->persist($ride);
            $entityManager->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render(
            'ride/new.html.twig',
            [
                'rideForm' => $form,
            ],
        );
    }
}
