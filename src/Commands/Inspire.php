<?php

namespace TailPress\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Inspire extends Command
{
    protected function configure()
    {
        $this
            ->setName('inspire')
            ->setDescription('Give me a inspirational quote.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quotes = [
            [
                'author' => 'Jackie Chan',
                'quote' => 'Do not let circumstances control you. You change your circumstances.',
            ],
            [
                'author' => 'Jackie Chan',
                'quote' => 'I never wanted to be the next Bruce Lee. I just wanted to be the first Jackie Chan.',
            ],
            [
                'author' => 'Kelsey Grammer',
                'quote' => 'Life is supposed to get tough. It’s how you respond that defines you.',
            ],
            [
                'author' => 'Kelsey Grammer',
                'quote' => 'The only way to achieve the impossible is to believe it is possible.',
            ],
            [
                'author' => 'Roger Moore',
                'quote' => 'Life is too short to hold grudges. Live with an open heart and an adventurous spirit.',
            ],
            [
                'author' => 'Roger Moore',
                'quote' => 'You only live once, so make the most of it. Take risks, embrace challenges, and never stop growing.',
            ],
            [
                'author' => 'Kate Winslet',
                'quote' => 'I think it’s important to fail sometimes. It’s how you learn, grow, and become stronger.',
            ],
            [
                'author' => 'Kate Winslet',
                'quote' => 'Believe in yourself, take risks, and don’t be afraid to stand out. That’s how you achieve greatness.',
            ],
            [
                'author' => 'Steve Jobs',
                'quote' => 'Your work is going to fill a large part of your life, and the only way to be truly satisfied is to do what you believe is great work.',
            ],
            [
                'author' => 'Steve Jobs',
                'quote' => 'Stay hungry, stay foolish. Never settle for mediocrity when you can achieve greatness.',
            ],
        ];

        $random_quote = $quotes[array_rand($quotes)];

        $output->writeln('<info>"'.$random_quote['quote'].'" - '.$random_quote['author'].'</info>');

        return Command::SUCCESS;
    }
}
