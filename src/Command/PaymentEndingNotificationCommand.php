<?php

namespace App\Command;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Sends notifications about ending course rent'
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //через день закончиться срок аренды-завтра. ('now') и ('+10 days') для проверки
        $from = new \DateTimeImmutable('tomorrow 00:00:00');
        $to = new \DateTimeImmutable('tomorrow 23:59:59');

        $transactions = $this->transactionRepository->findEndingRentTransactions($from, $to);

        $byUser = [];

        foreach ($transactions as $transaction) {
            $user = $transaction->getUser();

            $byUser[$user->getEmail()][] = $transaction;
        }

        foreach ($byUser as $email => $userTransactions) {
            $text = "Уважаемый клиент! У вас есть курсы, срок аренды которых подходит к концу:\n";

            foreach ($userTransactions as $transaction) {
                $course = $transaction->getCourse();

                $courseName = $course->getSymbolCode();

                $text .= sprintf(
                    "%s действует до %s.\n",
                    $courseName,
                    $transaction->getExpiresAt()->format('d.m.Y H:i')
                );
            }

            $message = (new Email())
                ->from('billing@example.com')
                ->to($email)
                ->subject('Срок аренды курса подходит к концу')
                ->text($text);

            $this->mailer->send($message);
        }

        $io->success(sprintf('Sent %d notification(s)', count($byUser)));

        return Command::SUCCESS;
    }
}