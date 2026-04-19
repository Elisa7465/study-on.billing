<?php

namespace App\Controller;

use App\Dto\RegisterUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController extends AbstractController
{
    #[Route('/api/v1/register', name: 'api_v1_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager
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
                    'email' => ['User with this email already exists.'],
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

        return $this->json([
            'token' => $jwtManager->create($user),
            'roles' => $user->getRoles(),
        ], 201);
    }
}