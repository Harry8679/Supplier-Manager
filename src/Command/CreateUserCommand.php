<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Crée un utilisateur de l’application',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Adresse email de l’utilisateur'
            )
            ->addArgument(
                'firstName',
                InputArgument::REQUIRED,
                'Prénom de l’utilisateur'
            )
            ->addArgument(
                'lastName',
                InputArgument::REQUIRED,
                'Nom de l’utilisateur'
            )
            ->addOption(
                'admin',
                null,
                InputOption::VALUE_NONE,
                'Créer le compte avec le rôle administrateur'
            )
        ;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower(
            trim((string) $input->getArgument('email'))
        );

        $firstName = trim(
            (string) $input->getArgument('firstName')
        );

        $lastName = trim(
            (string) $input->getArgument('lastName')
        );

        /*
         * Vérification de l'adresse email
         */
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('L’adresse email renseignée est invalide.');

            return Command::FAILURE;
        }

        /*
         * Vérification qu'un compte n'existe pas déjà.
         */
        $existingUser = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if ($existingUser !== null) {
            $io->error(sprintf(
                'Un compte existe déjà avec l’adresse "%s".',
                $email
            ));

            return Command::FAILURE;
        }

        /*
         * Demande du mot de passe.
         *
         * askHidden() permet de ne pas afficher le mot de passe
         * pendant sa saisie dans le terminal.
         */
        $password = $io->askHidden(
            'Mot de passe',
            function (?string $password): string {
                if ($password === null || trim($password) === '') {
                    throw new \RuntimeException(
                        'Le mot de passe est obligatoire.'
                    );
                }

                if (mb_strlen($password) < 8) {
                    throw new \RuntimeException(
                        'Le mot de passe doit contenir au moins 8 caractères.'
                    );
                }

                return $password;
            }
        );

        /*
         * Confirmation du mot de passe.
         */
        $passwordConfirmation = $io->askHidden(
            'Confirmez le mot de passe'
        );

        if ($password !== $passwordConfirmation) {
            $io->error(
                'Les deux mots de passe ne correspondent pas.'
            );

            return Command::FAILURE;
        }

        /*
         * Création de l'utilisateur.
         */
        $user = new User();

        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setIsActive(true);

        /*
         * Attribution du rôle.
         */
        if ($input->getOption('admin')) {
            $user->setRoles(['ROLE_ADMIN']);
        } else {
            $user->setRoles(['ROLE_USER']);
        }

        /*
         * Hashage du mot de passe.
         *
         * On ne stocke JAMAIS le mot de passe en clair.
         */
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $password
        );

        $user->setPassword($hashedPassword);

        /*
         * Sauvegarde en base de données.
         */
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        /*
         * Affichage du résultat.
         */
        $io->success(
            $input->getOption('admin')
                ? 'Le compte administrateur a été créé avec succès.'
                : 'Le compte utilisateur a été créé avec succès.'
        );

        $io->table(
            ['Information', 'Valeur'],
            [
                ['ID', (string) $user->getId()],
                ['Prénom', $user->getFirstName()],
                ['Nom', $user->getLastName()],
                ['Email', $user->getEmail()],
                [
                    'Type',
                    $input->getOption('admin')
                        ? 'Administrateur'
                        : 'Utilisateur',
                ],
                [
                    'Statut',
                    $user->isActive()
                        ? 'Actif'
                        : 'Inactif',
                ],
            ]
        );

        return Command::SUCCESS;
    }
}