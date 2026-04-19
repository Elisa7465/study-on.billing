<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/api/v1/auth', name: 'api_v1_auth', methods: ['POST'])]
    public function auth(): Response
    {
        throw new \LogicException('This code should never be reached.');
    }
}
