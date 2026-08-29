<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Form\RideFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RideController extends AbstractController
{
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
