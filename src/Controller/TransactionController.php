<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class TransactionController extends AbstractController
{
    #[Route('/api/v1/transactions', name: 'api_v1_transactions_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/transactions',
        summary: 'История операций текущего пользователя',
        security: [['Bearer' => []]],
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'filter[type]',
                in: 'query',
                required: false,
                description: 'Фильтр по типу операции',
                schema: new OA\Schema(type: 'string', enum: ['payment', 'deposit']),
                example: 'payment'
            ),
            new OA\Parameter(
                name: 'filter[course_code]',
                in: 'query',
                required: false,
                description: 'Символьный код курса для фильтрации списаний по конкретному курсу',
                schema: new OA\Schema(type: 'string'),
                example: 'landshaftnoe-proektirovanie'
            ),
            new OA\Parameter(
                name: 'filter[skip_expired]',
                in: 'query',
                required: false,
                description: 'Если true, исключает операции по аренде с истекшим сроком доступа',
                schema: new OA\Schema(type: 'boolean'),
                example: true
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список транзакций',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 31),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-04T10:20:30+00:00'),
                            new OA\Property(property: 'type', type: 'string', enum: ['payment', 'deposit'], example: 'payment'),
                            new OA\Property(property: 'amount', type: 'string', example: '99.90'),
                            new OA\Property(property: 'course_code', type: 'string', nullable: true, example: 'landshaftnoe-proektirovanie'),
                            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true, example: '2026-05-11T10:20:30+00:00'),
                        ]
                    )
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
        ]
    )]
      public function list(
      Request $request,
      TransactionRepository $transactionRepository,
      CourseRepository $courseRepository,
      #[CurrentUser] ?User $user,
      ): JsonResponse {
      if (null === $user) {
            return $this->json([
                  'code' => 401,
                  'message' => 'Требуется авторизация',
            ], 401);
      }

      $filter = $request->query->all('filter');

      $type = null;

      if (($filter['type'] ?? null) === 'payment') {
            $type = Transaction::TYPE_PAYMENT;
      }

      if (($filter['type'] ?? null) === 'deposit') {
            $type = Transaction::TYPE_DEPOSIT;
      }

      $course = null;

      if (isset($filter['course_code']) && '' !== $filter['course_code']) {
            $course = $courseRepository->findOneBy([
                  'symbolCode' => $filter['course_code'],
            ]);

            if (null === $course) {
                  return $this->json([]);
            }
      }

      $skipExpired = filter_var(
            $filter['skip_expired'] ?? false,
            FILTER_VALIDATE_BOOL
      );

      $transactions = $transactionRepository->findByUserWithFilters(
            $user,
            $type,
            $course,
            $skipExpired
      );

      return $this->json(array_map(
            fn (Transaction $transaction): array => $this->formatTransaction($transaction),
            $transactions
      ));
      }

    private function formatTransaction(Transaction $transaction): array
    {
        $data = [
            'id' => $transaction->getId(),
            'created_at' => $transaction->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'type' => $transaction->getTypeName(),
            'amount' => number_format($transaction->getAmount(), 2, '.', ''),
        ];

        if (null !== $transaction->getCourse()) {
            $data['course_code'] = $transaction->getCourse()->getSymbolCode();
        }

        if (null !== $transaction->getExpiresAt()) {
            $data['expires_at'] = $transaction->getExpiresAt()->format(\DateTimeInterface::ATOM);
        }

        return $data;
    }
}
