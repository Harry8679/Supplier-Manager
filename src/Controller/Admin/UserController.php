<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', name: 'admin_user_')]
class UserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository
    ): Response {
        return $this->render(
            'admin/user/index.html.twig',
            [
                'users' => $userRepository->findBy(
                    [],
                    ['createdAt' => 'DESC']
                ),
            ]
        );
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();

        $form = $this->createForm(
            UserType::class,
            $user,
            [
                'is_creation' => true,
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form
                ->get('plainPassword')
                ->getData();

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $plainPassword
            );

            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash(
                'success',
                sprintf(
                    'Le compte de %s a été créé.',
                    $user->getFullName()
                )
            );

            return $this->redirectToRoute(
                'admin_user_index'
            );
        }

        return $this->render(
            'admin/user/new.html.twig',
            [
                'form' => $form,
            ]
        );
    }

    #[Route(
        '/{id}/edit',
        name: 'edit',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(
            UserType::class,
            $user
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /*
             * Empêche un administrateur de désactiver
             * son propre compte par erreur.
             */
            if (
                $this->getUser() === $user
                && !$user->isActive()
            ) {
                $user->setIsActive(true);

                $this->addFlash(
                    'error',
                    'Vous ne pouvez pas désactiver votre propre compte.'
                );

                return $this->redirectToRoute(
                    'admin_user_edit',
                    ['id' => $user->getId()]
                );
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Les informations ont été mises à jour.'
            );

            return $this->redirectToRoute(
                'admin_user_index'
            );
        }

        return $this->render(
            'admin/user/edit.html.twig',
            [
                'form' => $form,
                'user' => $user,
            ]
        );
    }

    #[Route(
        '/{id}/toggle',
        name: 'toggle',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    public function toggle(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid(
            'toggle-user-' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        if ($this->getUser() === $user) {
            $this->addFlash(
                'error',
                'Vous ne pouvez pas désactiver votre propre compte.'
            );

            return $this->redirectToRoute(
                'admin_user_index'
            );
        }

        $user->setIsActive(
            !$user->isActive()
        );

        $entityManager->flush();

        $this->addFlash(
            'success',
            $user->isActive()
                ? 'Le compte a été activé.'
                : 'Le compte a été désactivé.'
        );

        return $this->redirectToRoute(
            'admin_user_index'
        );
    }
}