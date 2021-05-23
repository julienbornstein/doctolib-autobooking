<?php

declare(strict_types=1);

namespace App\Command;

use App\AutoBooking;
use Doctolib\Model\Appointment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DoctolibAutoBookingCommand extends Command
{
    private const PROFILES_SLUGS = [
        'centre-de-vaccination-covid-mairie-du-9eme-arrondissement',
        'centre-de-vaccination-covid-19-paris-17eme',
        'centre-de-vaccination-covid-19-paris-8e',

        'centre-de-vaccination-cpam-de-paris',
        'centre-covid19-paris-5',
        'centre-de-vaccination-mairie-du-7eme-paris',
        'centre-de-vaccination-covid-19-mairie-du-16eme-arrondissement',
        'centre-de-vaccination-covid-19-du-theatre-des-sablons',

        'ars-idf-centre-covisan-cpts-paris-18',

        'vaccinodrome-covid-19-porte-de-versailles',
        'centre-de-vaccination-covid-19-stade-de-france',
    ];

    protected static $defaultName = 'doctolib:create-appointment';
    protected static $defaultDescription = 'Créer un rendez-vous.';

    private AutoBooking $autoBooking;
    private string $sessionId;
    private string $profilesSlugs;

    public function __construct(AutoBooking $autoBooking, string $sessionId = '', string $profilesSlugs = '', string $name = null)
    {
        parent::__construct($name);

        $this->autoBooking = $autoBooking;
        $this->sessionId = $sessionId;
        $this->profilesSlugs = $profilesSlugs;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === $this->sessionId) {
            $io->error('Erreur de configuration: Session absente.');
            exit();
        }

        if ('' === $this->profilesSlugs) {
            $io->error('Erreur de configuration: Profil(s) absent(s).');
            exit();
        }

        $profilesSlugs = explode(',', $this->profilesSlugs);

        $appointment = null;
        while (!$appointment instanceof Appointment) {
            try {
                $appointment = $this->autoBooking->run($this->sessionId, $profilesSlugs);
                if (!$appointment instanceof Appointment) {
                    $io->write('<comment>.</comment>');
                    sleep(1);
                    continue;
                }

                $io->newLine();
            } catch (\UnexpectedValueException $e) {
                // can happened when server is "in maintenance"
                $io->writeln(sprintf('<error>%s</error>', $e->getMessage()));
                sleep(5);
            }
        }

        $io->writeln(sprintf('<info>RDV confirmé: https://www.doctolib.fr%s</info>', $appointment->getRedirection()));

        return Command::SUCCESS;
    }
}
