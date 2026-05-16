<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/courses')]
final class CourseController extends AbstractController
{
    #[Route('', name: 'api_v1_courses_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses',
        summary: 'Список курсов',
        tags: ['Courses'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список курсов',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', type: 'string', example: 'landshaftnoe-proektirovanie'),
                            new OA\Property(property: 'title', type: 'string', example: 'Ландшафтное проектирование'),
                            new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                            new OA\Property(
                                property: 'price',
                                type: 'string',
                                nullable: true,
                                example: '99.90',
                                description: 'Цена в формате строки с двумя знаками после точки. Для free-курса поле отсутствует.'
                            ),
                        ]
                    )
                )
            ),
        ]
    )]
    public function list(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();

        return $this->json(array_map(
            fn (Course $course): array => $this->formatCourse($course),
            $courses
        ));
    }

    #[Route('/{code}', name: 'api_v1_courses_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses/{code}',
        summary: 'Получение курса по коду',
        tags: ['Courses'],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Символьный код курса',
                schema: new OA\Schema(type: 'string'),
                example: 'landshaftnoe-proektirovanie'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Курс найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'landshaftnoe-proektirovanie'),
                        new OA\Property(property: 'title', type: 'string', example: 'Ландшафтное проектирование'),
                        new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                        new OA\Property(
                            property: 'price',
                            type: 'string',
                            nullable: true,
                            example: '99.90',
                            description: 'Цена в формате строки с двумя знаками после точки. Для free-курса поле отсутствует.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Курс не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Курс не найден'),
                    ]
                )
            ),
        ]
    )]
    public function show(string $code, CourseRepository $courseRepository): JsonResponse
    {
        $course = $courseRepository->findOneBy(['symbolCode' => $code]);

        if (null === $course) {
            return $this->json([
                'code' => 404,
                'message' => 'Курс не найден',
            ], 404);
        }

        return $this->json($this->formatCourse($course));
    }



    #[Route('', name: 'api_v1_courses_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/v1/courses',
        summary: 'Создание курса',
        security: [['Bearer' => []]],
        tags: ['Courses'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'title', 'code', 'price'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                    new OA\Property(property: 'title', type: 'string', example: 'Ландшафтное проектирование'),
                    new OA\Property(property: 'code', type: 'string', example: 'landshaftnoe-proektirovanie'),
                    new OA\Property(property: 'price', type: 'string', example: '99.90'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Курс создан',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Курс с таким символьным кодом уже существует'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Требуется авторизация'
            ),
            new OA\Response(
                response: 403,
                description: 'Доступ только для администратора'
            ),
        ]
    )]
    public function create(
        Request $request,
        CourseRepository $courseRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $code = $data['code'] ?? null;
        $title = $data['title'] ?? null;
        $type = $data['type'] ?? null;
        $price = $data['price'] ?? null;

        if (!$code || !$title || !$type) {
            return $this->json([
                'code' => 400,
                'message' => 'Не переданы обязательные поля: code, title, type',
            ], 400);
        }

        if (!in_array($type, ['free', 'rent', 'buy'], true)) {
            return $this->json([
                'code' => 400,
                'message' => 'Некорректный тип курса',
            ], 400);
        }

        if ($courseRepository->findOneBy(['symbolCode' => $code])) {
            return $this->json([
                'code' => 400,
                'message' => 'Курс с таким символьным кодом уже существует',
            ], 400);
        }

        if ('free' !== $type && (null === $price || $price <= 0)) {
            return $this->json([
                'code' => 400,
                'message' => 'Для платного курса необходимо указать стоимость',
            ], 400);
        }

        $course = new Course();
        $course
            ->setSymbolCode($code)
            ->setTitle($title)
            ->setType($this->resolveCourseType($type))
            ->setPrice('free' === $type ? null : $price);

        $entityManager->persist($course);
        $entityManager->flush();

        return $this->json([
            'success' => true,
        ], 201);
    }

    #[Route('/{code}', name: 'api_v1_courses_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/v1/courses/{code}',
        summary: 'Редактирование курса',
        security: [['Bearer' => []]],
        tags: ['Courses'],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Текущий символьный код курса',
                schema: new OA\Schema(type: 'string'),
                example: 'landshaftnoe-proektirovanie'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'title', 'code', 'price'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'buy'),
                    new OA\Property(property: 'title', type: 'string', example: 'Ландшафтное проектирование PRO'),
                    new OA\Property(property: 'code', type: 'string', example: 'landshaftnoe-proektirovanie-pro'),
                    new OA\Property(property: 'price', type: 'string', example: '199.90'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Курс обновлён',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Курс с таким символьным кодом уже существует'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Требуется авторизация'
            ),
            new OA\Response(
                response: 403,
                description: 'Доступ только для администратора'
            ),
            new OA\Response(
                response: 404,
                description: 'Курс не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Курс не найден'),
                    ]
                )
            ),
        ]
    )]
    public function update(
        string $code,
        Request $request,
        CourseRepository $courseRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $course = $courseRepository->findOneBy(['symbolCode' => $code]);

        if (null === $course) {
            return $this->json([
                'code' => 404,
                'message' => 'Курс не найден',
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        $newCode = $data['code'] ?? null;
        $title = $data['title'] ?? null;
        $type = $data['type'] ?? null;
        $price = $data['price'] ?? null;

        if (!$newCode || !$title || !$type) {
            return $this->json([
                'code' => 400,
                'message' => 'Не переданы обязательные поля: code, title, type',
            ], 400);
        }

        if (!in_array($type, ['free', 'rent', 'buy'], true)) {
            return $this->json([
                'code' => 400,
                'message' => 'Некорректный тип курса',
            ], 400);
        }

        $existingCourse = $courseRepository->findOneBy(['symbolCode' => $newCode]);

        if (
            null !== $existingCourse
            && $existingCourse->getId() !== $course->getId()
        ) {
            return $this->json([
                'code' => 400,
                'message' => 'Курс с таким символьным кодом уже существует',
            ], 400);
        }

        if ('free' !== $type && (null === $price || $price <= 0)) {
            return $this->json([
                'code' => 400,
                'message' => 'Для платного курса необходимо указать стоимость',
            ], 400);
        }

        $course
            ->setSymbolCode($newCode)
            ->setTitle($title)
            ->setType($this->resolveCourseType($type))
            ->setPrice('free' === $type ? null : $price);

        $entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }


    #[Route('/{code}/pay', name: 'api_v1_courses_pay', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/courses/{code}/pay',
        summary: 'Оплата курса',
        security: [['Bearer' => []]],
        tags: ['Courses'],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                description: 'Символьный код курса',
                schema: new OA\Schema(type: 'string'),
                example: 'landshaftnoe-proektirovanie'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Курс успешно оплачен',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'course_type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                        new OA\Property(
                            property: 'expires_at',
                            type: 'string',
                            format: 'date-time',
                            nullable: true,
                            example: '2026-05-11T10:20:30+00:00',
                            description: 'Дата окончания доступа. Для rent заполняется, для free/buy обычно null.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Требуется авторизация',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Требуется авторизация'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Курс не найден',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Курс не найден'),
                    ]
                )
            ),
            new OA\Response(
                response: 406,
                description: 'Недостаточно средств',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 406),
                        new OA\Property(property: 'message', type: 'string', example: 'На вашем счету недостаточно средств'),
                    ]
                )
            ),
        ]
    )]
    public function pay(
        string $code,
        CourseRepository $courseRepository,
        PaymentService $paymentService,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if (null === $user) {
            return $this->json([
                'code' => 401,
                'message' => 'Требуется авторизация',
            ], 401);
        }

        $course = $courseRepository->findOneBy(['symbolCode' => $code]);

        if (null === $course) {
            return $this->json([
                'code' => 404,
                'message' => 'Курс не найден',
            ], 404);
        }

        try {
            $transaction = $paymentService->payCourse($user, $course);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'code' => $exception->getCode() ?: 406,
                'message' => $exception->getMessage(),
            ], $exception->getCode() ?: 406);
        }

        return $this->json([
            'success' => true,
            'course_type' => $course->getTypeName(),
            'expires_at' => $transaction->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function formatCourse(Course $course): array
    {
        $data = [
            'code' => $course->getSymbolCode(),
            'title' => $course->getTitle(),
            'type' => $course->getTypeName(),
        ];

        if (!$course->isFree() && null !== $course->getPrice()) {
            $data['price'] = number_format($course->getPrice(), 2, '.', '');
        }

        return $data;
    }

    private function resolveCourseType(string $type): int|string
    {
        return match ($type) {
            'free' => Course::TYPE_FREE,
            'rent' => Course::TYPE_RENT,
            'buy' => Course::TYPE_BUY,
            default => throw new \InvalidArgumentException('Некорректный тип курса'),
        };
    }
}
