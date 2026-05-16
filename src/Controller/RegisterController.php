<?php

namespace App\Controller;

use App\Dto\RegisterUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController extends AbstractController
{
    #[OA\Post(
        path: '/api/v1/register',
        summary: 'Регистрация пользователя',
        description: 'Создает пользователя и возвращает access token и refresh token. Refresh token используется в POST /api/v1/token/refresh (параметр refresh_token).',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        example: 'user@example.com'
                    ),
                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        example: 'password123'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Пользователь создан',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                        new OA\Property(property: 'refresh_token', type: 'string', example: 'f3b6c5a9...'),
                        new OA\Property(
                            property: 'roles',
                            type: 'array',
                            items: new OA\Items(type: 'string')
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: [
                                'email' => ['This value is not a valid email address.'],
                                'password' => ['This value is too short. It should have 6 characters or more.'],
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route('/api/v1/register', name: 'api_v1_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        PaymentService $paymentService,
        #[Autowire('%initial_user_balance%')]
        float $initialUserBalance,
    ): JsonResponse {
        $userDto = $serializer->deserialize(
            $request->getContent(),
            RegisterUserDto::class,
            'json'
        );

        $errors = $validator->validate($userDto);

        if (count($errors) > 0) {
            $formattedErrors = [];

            foreach ($errors as $error) {
                $formattedErrors[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->json(['errors' => $formattedErrors], 400);
        }

        if ($userRepository->findOneBy(['email' => $userDto->email]) !== null) {
            return $this->json([
                'errors' => [
                    'email' => ['The user with this email already exists.'],
                ],
            ], 400);
        }

        $user = new User();
        $user->setEmail($userDto->email);
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(0.0);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $userDto->password)
        );

        $entityManager->persist($user);
        $entityManager->flush();

        $paymentService->deposit($user, $initialUserBalance);

        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            30 * 24 * 60 * 60
        );

        $entityManager->persist($refreshToken);
        $entityManager->flush();

        return $this->json([
            'token' => $jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'roles' => $user->getRoles(),
        ], 201);
    }
}
