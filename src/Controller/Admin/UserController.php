<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class Admin/UserController extends AbstractController
{
    #[Route('/admin/user', name: 'app_admin_user')]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'controller_name' => 'Admin/UserController',
        ]);
    }
}
