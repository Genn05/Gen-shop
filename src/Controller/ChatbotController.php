<?php
namespace App\Controller;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    // Route pour afficher la page du chatbot
    #[Route('/dashboard/chatbot', name: 'app_dashboard_chatbot')]
    public function chat(Request $request, ChatbotService $chatbotService): Response
    {
        // Vérification de l'accès
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Récupération du message utilisateur
        $userMessage = $request->get('message', '');
        $responseMessage = '';

        // Si un message est envoyé
        if ($userMessage) {
            // Nettoyage et validation du message utilisateur
            $userMessage = trim($userMessage);
            if (empty($userMessage)) {
                $responseMessage = 'Veuillez entrer un message valide.';
            } else {
                try {
                    // Obtenir la réponse du chatbot
                    $responseMessage = $chatbotService->getResponse($userMessage);
                } catch (\Exception $e) {
                    // Gestion d'éventuelles erreurs lors de l'appel au service
                    $responseMessage = 'Désolé, une erreur est survenue lors de la communication avec le chatbot.';
                }
            }
        }

        // Retourner la vue avec les messages de l'utilisateur et du chatbot
        return $this->render('chatbot/index.html.twig', [
            'response' => $responseMessage,
            'userMessage' => $userMessage,
        ]);
    }
}
