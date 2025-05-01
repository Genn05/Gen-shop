<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\Security\Core\Security; // Correcte
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\EmailService; 

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(SecurityBundleSecurity $security): Response
    {
        // Si l'utilisateur n'est pas connecté, redirection vers la page de connexion
        $user = $security->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }
    #[Route('/dashboard/users', name: 'app_dashboard_users')]
    public function manageUsers(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('dashboard/users.html.twig', [
            'users' => $users,
        ]);
    }
    private $passwordHasher;

    // Injection du service UserPasswordHasherInterface via le constructeur
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    #[Route('/dashboard/users/{id}/edit', name: 'app_dashboard_edit_user')]
public function editUser($id, EntityManagerInterface $entityManager, EmailService $emailService,Request $request,UserPasswordHasherInterface $passwordHasher): Response
{
    // Protège l'accès à cette fonctionnalité uniquement pour les administrateurs
$this->denyAccessUnlessGranted('ROLE_ADMIN');

    // Récupérer l'utilisateur par son ID
    $user = $entityManager->getRepository(User::class)->find($id);

    // Si l'utilisateur n'existe pas
    if (!$user) {
        throw $this->createNotFoundException('L\'utilisateur n\'existe pas.');
    }

    // Créer un formulaire pour modifier l'email et d'autres champs
    $form = $this->createFormBuilder($user)
        ->add('email', EmailType::class, [
            'label' => 'Adresse email',
            'attr' => ['placeholder' => 'Adresse email de l\'utilisateur']
        ])
        ->add('plainPassword', PasswordType::class, [
            'label' => 'Mot de passe',
            'mapped' => false, // Ce champ ne sera pas directement mappé à l'utilisateur
            'required' => false,
            'attr' => ['placeholder' => 'Laissez vide pour garder l\'ancien mot de passe']
        ])
        ->add('roles', ChoiceType::class, [
            'label' => 'Rôles',
            'choices' => [
                'Utilisateur' => 'ROLE_USER',
                'Administrateur' => 'ROLE_ADMIN',
            ],
            'expanded' => true,
            'multiple' => true,
            'data' => $user->getRoles(),
        ])
        ->add('save', SubmitType::class, ['label' => 'Sauvegarder les modifications'])
        ->getForm();

    // Gérer l'envoi du formulaire
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
        // Vérifier si un nouveau mot de passe a été entré
        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword) {
            // Encoder le mot de passe si un nouveau mot de passe est fourni
            $encodedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($encodedPassword);
        }

        // Récupérer les rôles sélectionnés et les assigner
        $roles = $form->get('roles')->getData();
        $user->setRoles($roles);

        // Sauvegarder les modifications
        $entityManager->flush();
        $emailService->sendConfirmationEmail($user->getEmail());
        $this->addFlash('success', 'Les informations de l\'utilisateur ont été mises à jour avec succès.');

        return $this->redirectToRoute('app_dashboard_users');
    }

    return $this->render('dashboard/edit_user.html.twig', [
        'form' => $form->createView(),
        'user' => $user,
    ]);
}

#[Route('/dashboard/users/{id}/delete', name: 'app_dashboard_delete_user')]
public function deleteUser($id, EntityManagerInterface $entityManager): Response
{
    // Récupérer l'utilisateur par son ID
    $user = $entityManager->getRepository(User::class)->find($id);

    // Si l'utilisateur n'existe pas
    if (!$user) {
        throw $this->createNotFoundException('L\'utilisateur n\'existe pas.');
    }

    // Supprimer l'utilisateur
    $entityManager->remove($user);
    $entityManager->flush();

    // Redirection vers la page des utilisateurs après la suppression
    return $this->redirectToRoute('app_dashboard_users');
}
#[Route('/dashboard/users/{id}/remove-role/{role}', name: 'app_dashboard_remove_role')]
public function removeRole($id, $role, EntityManagerInterface $entityManager): Response
{
    // Récupérer l'utilisateur par son ID
    $user = $entityManager->getRepository(User::class)->find($id);

    // Si l'utilisateur n'existe pas
    if (!$user) {
        throw $this->createNotFoundException('L\'utilisateur n\'existe pas.');
    }

    // Si l'utilisateur a le rôle à supprimer
    $roles = $user->getRoles();
    if (in_array($role, $roles)) {
        $roles = array_diff($roles, [$role]); // Retirer le rôle
        $user->setRoles($roles);
        $entityManager->flush();
    }

    $this->addFlash('success', 'Le rôle a été supprimé avec succès.');

    return $this->redirectToRoute('app_dashboard_users');
}

}

