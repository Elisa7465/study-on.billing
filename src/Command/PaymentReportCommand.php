<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use App\Entity\Course;

#[AsCommand(
    name: 'payment:report',
    description: 'Sends monthly paid courses report'
)]
class PaymentReportCommand extends Command
{
      private const COURSE_TYPE_LABELS = [
        Course::TYPE_RENT => 'Аренда',
        Course::TYPE_BUY => 'Покупка',
      ];
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        #[Autowire('%payment_report_email%')]
        private readonly string $paymentReportEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // отчет за прошлый - previous  календарный месяц сейчас за этот для теста 
        $from = new \DateTimeImmutable('first day of this month 00:00:00');
        $to = new \DateTimeImmutable('last day of this month 23:59:59');

        $rows = $this->transactionRepository->getMonthlyPaidCoursesReport($from, $to);

        $rows = array_map(static function (array $row): array {
        $row['courseType'] = self::COURSE_TYPE_LABELS[(int) $row['courseType']] ?? 'Неизвестно';

        return $row;
        }, $rows);

        $total = 0;

        foreach ($rows as $row) {
            $total += (float) $row['totalAmount'];
        }

        $email = (new TemplatedEmail())
            ->from('billing@example.com')
            ->to($this->paymentReportEmail)
            ->subject('Отчет об оплаченных курсах')
            ->htmlTemplate('email/payment_report.html.twig')
            ->context([
                'from' => $from,
                'to' => $to,
                'rows' => $rows,
                'total' => $total,
            ]);

        $this->mailer->send($email);

        $io->success('Payment report sent');

        return Command::SUCCESS;
    }
}