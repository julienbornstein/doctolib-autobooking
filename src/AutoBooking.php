<?php

declare(strict_types=1);

namespace App;

use Doctolib\Client;
use Doctolib\Exception\UnavailableSlotException;
use Doctolib\Model\Appointment;
use Doctolib\Model\Availability;
use Doctolib\Model\Booking;
use Doctolib\Model\Slot;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AutoBooking
{
    private const VISIT_MOTIVE_IDS = [
        6970, // "1re injection vaccin COVID-19 (Pfizer-BioNTech)"
        7005, // "1re injection vaccin COVID-19 (Moderna)"in
    ];

    private Client $client;
    private LoggerInterface $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param string[] $profilesSlugs
     *
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function run(string $sessionId, array $profilesSlugs = []): ?Appointment
    {
        if (0 === \count($profilesSlugs)) {
            throw new \InvalidArgumentException('You need to pass to command at least one place slug.');
        }

        $this->client->setSessionId($sessionId);

        foreach ($profilesSlugs as $slug) {
            // create an Appointment
            $appointment = $this->createAppointmentFromProfileSlug($slug);
            if (!$appointment instanceof Appointment) {
                continue;
            }

            // confirm the Appointment
            $masterPatient = $this->client->getMasterPatient();
            $appointment = $this->client->confirmAppointment($appointment, $masterPatient);
            if (true !== $appointment->isFinalStep()) {
                throw new \LogicException('Appointment not confirmed. An error occurred');
            }

            return $appointment;
        }

        return null;
    }

    private function createAppointmentFromProfileSlug(string $slug): ?Appointment
    {
        $booking = $this->client->getBooking($slug);
        $availability = $this->getAvailability($booking);
        if (!$availability instanceof Availability) {
            $profile = $booking->getProfile();
            $name = $profile->getNameWithTitle() ?? trim(sprintf('%s %s', $profile->getFirstName(), $profile->getLastName()));
            $this->logger->info(sprintf('%s No availabilities for %s', time(), $name));

            return null;
        }

        $slots = $availability->getSlots();
        foreach ($slots as $slot) {
            $steps = $slot->getSteps();
            if (0 === \count($steps)) {
                throw new \LogicException('Slot must be multi step.');
            }

            $step0 = $steps[0];
            $step1 = $steps[1];

            try {
                $this->logger->notice(sprintf(
                    'Creating appointment: %s, %s.',
                    $step0->getStartDate()->format(\DateTimeInterface::ATOM),
                    $step1->getStartDate()->format(\DateTimeInterface::ATOM),
                ));

                $appointment = $this->client->createMultiStepAppointment($booking, $slot);

                $this->logger->notice(sprintf('Appointment 1 created: %s', $appointment->getId()));

                return $appointment;
            } catch (UnavailableSlotException $e) {
                $this->logger->notice('Appointment not created, slot unavailable.');
            }
        }

        return null;
    }

    private function getAvailability(Booking $booking): ?Availability
    {
        foreach (self::VISIT_MOTIVE_IDS as $visitMotiveId) {
            $availabilities = $this->client->getAvailabilities($booking->getAgendas(), null, $visitMotiveId);
            $availability = self::getFirstValidAvailability($availabilities); // TODO : loop instead getting only first
            if ($availability instanceof Availability) {
                return $availability;
            }
        }

        return null;
    }

    /**
     * @param Availability[] $availabilities
     */
    private static function getFirstValidAvailability(array $availabilities): ?Availability
    {
        if (0 === \count($availabilities)) {
            return null;
        }

        foreach ($availabilities as $availability) {
            $slots = array_filter($availability->getSlots(), [__CLASS__, 'filterSlot']);

            if (0 === \count($slots)) {
                return null;
            }

            $slot = $availability->getSlots()[0];

            if (0 === \count($slot->getSteps())) {
                return null;
            }

            return $availability;
        }
    }

    private static function filterSlot(Slot $slot): bool
    {
        // return true; // for debug

        $secDiff = $slot->getStartDate()->getTimestamp() - (new \DateTime())->getTimestamp();
        $maxHours = 31; // find an appointment within $maxHours hours

        return ($maxHours * 3600) > $secDiff;
    }
}
