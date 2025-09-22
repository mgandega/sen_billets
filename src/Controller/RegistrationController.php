<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encodage du mot de passe
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Définition des rôles
            $roles = ['ROLE_USER'];
            if ($form->get('isOrganizer')->getData()) {
                $roles[] = 'ROLE_ORGANIZER';
            }
            $user->setRoles($roles);

            if(in_array('ROLE_ORGANIZER', $user->getRoles())){
                $user->setIsVerified(false);
            }else{
                $user->setIsVerified(true);
            }
            $entityManager->persist($user);
            $entityManager->flush();

            // Envoi email de confirmation
            if(in_array('ROLE_ORGANIZER', $user->getRoles())){
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('mgandega@gmail.com', 'Sen Billets'))
                    ->to($user->getEmail())
                    ->subject('Veuillez confirmer votre adresse email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
        }
        if(in_array('ROLE_ORGANIZER', $user->getRoles())){
            $this->addFlash('success', 'Votre compte a été créé ! Un email de confirmation a été envoyé.');
        }else{
            $this->addFlash('success', 'Votre compte a été créé !');
        }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    // #[Route('/verify/email', name: 'app_verify_email')]
    // public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    // {
    //     $id = $request->get('id');
    //     $email = $request->get('email');

    //     if (!$id || !$email) {
    //         $this->addFlash('error', 'Lien de confirmation invalide.');
    //         return $this->redirectToRoute('app_register');
    //     }

    //     $user = $entityManager->getRepository(User::class)->find($id);

    //     if (!$user || $user->getEmail() !== $email) {
    //         $this->addFlash('error', 'Lien de confirmation invalide.');
    //         return $this->redirectToRoute('app_register');
    //     }

    //     try {
    //         $this->emailVerifier->handleEmailConfirmation($request, $user);
    //         $user->setIsVerified(true);
    //         $entityManager->persist($user);
    //         $entityManager->flush();

    //         $this->addFlash('success', 'Votre adresse email a été vérifiée.');
    //     } catch (VerifyEmailExceptionInterface $exception) {
    //         $this->addFlash(
    //             'verify_email_error',
    //             $translator->trans($exception->getReason(), [], 'VerifyEmailBundle')
    //         );
    //         return $this->redirectToRoute('app_register');
    //     }

    //     return $this->redirectToRoute('app_login');
    // }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            $userId = $request->query->get('id');
            $userEmail = $request->query->get('email');

            if (!$userId || !$userEmail) {
                $this->addFlash('error', 'Lien de confirmation invalide.');
                return $this->redirectToRoute('app_register');
            }

            $user = $entityManager->getRepository(User::class)->find($userId);

            if (!$user || $user->getEmail() !== $userEmail) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('app_register');
            }

            $this->emailVerifier->handleEmailConfirmation($request, $user);

            $this->addFlash('success', 'Votre adresse email a été vérifiée !');
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'verify_email_error',
                $translator->trans($exception->getReason(), [], 'VerifyEmailBundle')
            );

            return $this->redirectToRoute('app_register');
        }

        return $this->redirectToRoute('app_home');
    }


}
