<?php

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\EventTicket;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create demo users
        $organizer = new User();
        $organizer->setEmail('organizer@test.com');
        $organizer->setName('Organisateur Demo');
        $organizer->setRoles(['ROLE_USER', 'ROLE_ORGANIZER']);
        $organizer->setPassword($this->passwordHasher->hashPassword($organizer, 'password'));
        $manager->persist($organizer);

        $customer = new User();
        $customer->setEmail('client@test.com');
        $customer->setName('Client Demo');
        $customer->setRoles(['ROLE_USER']);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'password'));
        $manager->persist($customer);

        // Create demo events
        $events = [
            [
                'title' => 'Festival de Musique Dakar 2024',
                'description' => 'Le plus grand festival de musique du Sénégal réunit les meilleurs artistes locaux et internationaux.',
                'date' => '2024-12-15 19:00:00',
                'startTime' => '19:00:00',
                'endTime' => '02:00:00',
                'venue' => 'Stade Léopold Sédar Senghor',
                'address' => 'Dakar, Sénégal',
                'category' => 'Musique',
                'imageUrl' => 'https://images.pexels.com/photos/1190298/pexels-photo-1190298.jpeg?auto=compress&cs=tinysrgb&w=800',
                'capacity' => 15000,
                'soldTickets' => 8500,
                'tags' => ['musique', 'festival', 'culture'],
                'ticketTypes' => [
                    ['name' => 'Tarif Normal', 'price' => 2500000, 'quantity' => 10000, 'sold' => 6000, 'description' => 'Accès général au festival'],
                    ['name' => 'VIP', 'price' => 5000000, 'quantity' => 2000, 'sold' => 1200, 'description' => 'Accès VIP avec lounge privé'],
                    ['name' => 'Early Bird', 'price' => 1800000, 'quantity' => 3000, 'sold' => 1300, 'description' => 'Tarif réduit - Offre limitée', 'earlyBird' => true, 'endDate' => '2024-11-30']
                ]
            ],
            [
                'title' => 'Conférence Tech Sénégal',
                'description' => 'Conférence sur les nouvelles technologies et l\'innovation en Afrique.',
                'date' => '2024-11-28 09:00:00',
                'startTime' => '09:00:00',
                'endTime' => '18:00:00',
                'venue' => 'King Fahd Palace Hotel',
                'address' => 'Dakar, Sénégal',
                'category' => 'Technologie',
                'imageUrl' => 'https://images.pexels.com/photos/2774556/pexels-photo-2774556.jpeg?auto=compress&cs=tinysrgb&w=800',
                'capacity' => 500,
                'soldTickets' => 320,
                'tags' => ['technologie', 'innovation', 'networking'],
                'ticketTypes' => [
                    ['name' => 'Standard', 'price' => 1500000, 'quantity' => 400, 'sold' => 250, 'description' => 'Accès à toutes les conférences'],
                    ['name' => 'Premium', 'price' => 3500000, 'quantity' => 100, 'sold' => 70, 'description' => 'Accès premium + networking lunch']
                ]
            ],
            [
                'title' => 'Théâtre National - Pièce Classique',
                'description' => 'Représentation théâtrale exceptionnelle par la troupe nationale.',
                'date' => '2024-12-05 20:00:00',
                'startTime' => '20:00:00',
                'endTime' => '22:30:00',
                'venue' => 'Théâtre National Daniel Sorano',
                'address' => 'Dakar, Sénégal',
                'category' => 'Théâtre',
                'imageUrl' => 'https://images.pexels.com/photos/109669/pexels-photo-109669.jpeg?auto=compress&cs=tinysrgb&w=800',
                'capacity' => 800,
                'soldTickets' => 450,
                'tags' => ['théâtre', 'culture', 'art'],
                'ticketTypes' => [
                    ['name' => 'Orchestre', 'price' => 800000, 'quantity' => 200, 'sold' => 120, 'description' => 'Places orchestre'],
                    ['name' => 'Balcon', 'price' => 1200000, 'quantity' => 300, 'sold' => 180, 'description' => 'Places balcon'],
                    ['name' => 'Loge', 'price' => 2000000, 'quantity' => 100, 'sold' => 60, 'description' => 'Places loge premium']
                ]
            ]
        ];

        foreach ($events as $eventData) {
            $event = new Event();
            $event->setTitle($eventData['title']);
            $event->setDescription($eventData['description']);
            $event->setEventDate(new \DateTime($eventData['date']));
            $event->setStartTime(new \DateTime($eventData['startTime']));
            $event->setEndTime(new \DateTime($eventData['endTime']));
            $event->setVenue($eventData['venue']);
            $event->setAddress($eventData['address']);
            $event->setCategory($eventData['category']);
            $event->setImageUrl($eventData['imageUrl']);
            $event->setCapacity($eventData['capacity']);
            $event->setSoldTickets($eventData['soldTickets']);
            $event->setStatus('published');
            $event->setTags($eventData['tags']);
            $event->setOrganizer($organizer);

            foreach ($eventData['ticketTypes'] as $ticketTypeData) {
                $ticketType = new EventTicket();
                $ticketType->setName($ticketTypeData['name']);
                $ticketType->setPrice($ticketTypeData['price']);
                $ticketType->setQuantity($ticketTypeData['quantity']);
                $ticketType->setSold($ticketTypeData['sold']);
                $ticketType->setDescription($ticketTypeData['description']);
                $ticketType->setEarlyBird($ticketTypeData['earlyBird'] ?? false);
                
                if (isset($ticketTypeData['endDate'])) {
                    $ticketType->setEndDate(new \DateTime($ticketTypeData['endDate']));
                }
                
                $event->addTicketType($ticketType);
            }

            $manager->persist($event);
        }

        $manager->flush();
    }
}