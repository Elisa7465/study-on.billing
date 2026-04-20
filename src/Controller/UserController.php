<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/users')]
class UserController extends AbstractController
{
    #[Route('/current', name: 'api_v1_users_current', methods: ['GET'])]
    public function current(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'username' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}