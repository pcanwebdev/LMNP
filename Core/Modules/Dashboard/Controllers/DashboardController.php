<?php
namespace Core\Modules\Dashboard\Controllers;

use Core\Controller;

/**
 * Dashboard Controller
 * Gère l'affichage du tableau de bord avec les informations principales
 */
class DashboardController extends Controller
{
    /**
     * Page d'accueil du tableau de bord
     * 
     * @return void
     */
    public function index()
    {
        global $twig;
        
        // Afficher le template du tableau de bord
        echo $twig->render('Core/Modules/Dashboard/Views/index.twig', [
            'activeRoute' => 'Dashboard',
            'pageTitle' => 'Tableau de bord',
            'user' => $_SESSION['user'] ?? null
        ]);
    }
}