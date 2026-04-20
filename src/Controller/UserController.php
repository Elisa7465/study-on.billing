<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;


#[Route('/api/v1/users')]
class UserController extends AbstractController
{

      #[OA\Get(
            path: '/api/v1/users/current',
            summary: 'Получить текущего пользователя',
            tags: ['User'],
            responses: [
                  new OA\Response(
                        response: 200,
                        description: 'Данные текущего пользователя',
                        content: new OA\JsonContent(
                        properties: [
                              new OA\Property(property: 'username', type: 'string', example: 'developer@intaro.ru'),
                              new OA\Property(
                                    property: 'roles',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['ROLE_USER']
                              ),
                              new OA\Property(property: 'balance', type: 'number', format: 'float', example: 4741.1),
                        ]
                        )
                  ),
                  new OA\Response(response: 401, description: 'Требуется авторизация'),
            ]
            )]
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