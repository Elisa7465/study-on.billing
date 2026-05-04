<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const USER_REFERENCE = 'user';
    public const ADMIN_REFERENCE = 'admin';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PaymentService $paymentService,
        #[Autowire('%initial_user_balance%')]
        private readonly float $initialUserBalance,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setBalance(0.0);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'user123')
        );

        $manager->persist($user);
        $manager->flush();

        $this->paymentService->deposit($user, $this->initialUserBalance);

        $this->addReference(self::USER_REFERENCE, $user);

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setBalance(0.0);
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin123')
        );

        $manager->persist($admin);
        $manager->flush();

        $this->paymentService->deposit($admin, $this->initialUserBalance);

        $this->addReference(self::ADMIN_REFERENCE, $admin);
    }
}
