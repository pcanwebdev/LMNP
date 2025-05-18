<?php
namespace Core\Modules\User\Controllers;

use Core\Controller;
use Core\Modules\User\Models\UserModel;

/**
 * User Controller
 * Gère toutes les actions liées à l'utilisateur : connexion, inscription, profil, etc.
 */
class UserController extends Controller
{
    /**
     * Page de connexion
     * 
     * @return void
     */
    public function login()
    {
        global $twig;
        
        // Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
        if (isset($_SESSION['user_id'])) {
            header('Location: /Dashboard');
            exit;
        }
        
        // Afficher le template de connexion
        echo $twig->render('Core/Modules/User/Views/login.twig', [
            'error' => isset($_SESSION['login_error']) ? $_SESSION['login_error'] : null
        ]);
        
        // Supprimer le message d'erreur après l'avoir affiché
        if (isset($_SESSION['login_error'])) {
            unset($_SESSION['login_error']);
        }
    }
    
    /**
     * Traitement de la connexion
     * 
     * @return void
     */
    public function doLogin()
    {
        // Vérifier si la méthode est POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /User/login');
            exit;
        }
        
        // Récupérer les données du formulaire
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Vérifier que les champs ne sont pas vides
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Veuillez remplir tous les champs.';
            header('Location: /User/login');
            exit;
        }
        
        // Vérifier les identifiants
        $userModel = new UserModel();
        $user = $userModel->authenticate($username, $password);
        
        if ($user) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            
            // Si "Se souvenir de moi" est coché, définir un cookie
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $userModel->saveRememberToken($user['id'], $token);
                
                setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
            }
            
            header('Location: /Dashboard');
            exit;
        } else {
            // Connexion échouée
            $_SESSION['login_error'] = 'Identifiants incorrects.';
            header('Location: /User/login');
            exit;
        }
    }
    
    /**
     * Déconnexion
     * 
     * @return void
     */
    public function logout()
    {
        // Supprimer le cookie "Se souvenir de moi"
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Détruire la session
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Rediriger vers la page de connexion
        header('Location: /User/login');
        exit;
    }
    
    /**
     * Page d'inscription
     * 
     * @return void
     */
    public function register()
    {
        global $twig;
        
        // Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
        if (isset($_SESSION['user_id'])) {
            header('Location: /Dashboard');
            exit;
        }
        
        // Afficher le template d'inscription
        echo $twig->render('Core/Modules/User/Views/register.twig', [
            'error' => isset($_SESSION['register_error']) ? $_SESSION['register_error'] : null
        ]);
        
        // Supprimer le message d'erreur après l'avoir affiché
        if (isset($_SESSION['register_error'])) {
            unset($_SESSION['register_error']);
        }
    }
    
    /**
     * Traitement de l'inscription
     * 
     * @return void
     */
    public function doRegister()
    {
        // Vérifier si la méthode est POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /User/register');
            exit;
        }
        
        // Récupérer les données du formulaire
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Vérifier que les champs ne sont pas vides
        if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
            $_SESSION['register_error'] = 'Veuillez remplir tous les champs.';
            header('Location: /User/register');
            exit;
        }
        
        // Vérifier que les mots de passe correspondent
        if ($password !== $passwordConfirm) {
            $_SESSION['register_error'] = 'Les mots de passe ne correspondent pas.';
            header('Location: /User/register');
            exit;
        }
        
        // Vérifier la force du mot de passe
        if (strlen($password) < 8) {
            $_SESSION['register_error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            header('Location: /User/register');
            exit;
        }
        
        // Vérifier que l'email est valide
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['register_error'] = 'L\'adresse email n\'est pas valide.';
            header('Location: /User/register');
            exit;
        }
        
        // Créer l'utilisateur
        $userModel = new UserModel();
        
        // Vérifier que le nom d'utilisateur n'est pas déjà utilisé
        if ($userModel->getUserByUsername($username)) {
            $_SESSION['register_error'] = 'Ce nom d\'utilisateur est déjà utilisé.';
            header('Location: /User/register');
            exit;
        }
        
        // Vérifier que l'email n'est pas déjà utilisé
        if ($userModel->getUserByEmail($email)) {
            $_SESSION['register_error'] = 'Cette adresse email est déjà utilisée.';
            header('Location: /User/register');
            exit;
        }
        
        // Tout est OK, créer l'utilisateur
        $userId = $userModel->createUser($username, $email, $password);
        
        if ($userId) {
            // Inscription réussie, connecter l'utilisateur
            $user = $userModel->getUserById($userId);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            
            header('Location: /Dashboard');
            exit;
        } else {
            // Erreur lors de la création de l'utilisateur
            $_SESSION['register_error'] = 'Une erreur est survenue lors de l\'inscription.';
            header('Location: /User/register');
            exit;
        }
    }
    
    /**
     * Page de profil utilisateur
     * 
     * @return void
     */
    public function profile()
    {
        global $twig;
        
        // Récupérer les données de l'utilisateur
        $userModel = new UserModel();
        $user = $userModel->getUserById($_SESSION['user_id']);
        
        // Afficher le template de profil
        echo $twig->render('Core/Modules/User/Views/profile.twig', [
            'user' => $user,
            'success' => isset($_SESSION['profile_success']) ? $_SESSION['profile_success'] : null,
            'error' => isset($_SESSION['profile_error']) ? $_SESSION['profile_error'] : null,
            'activeRoute' => 'User'
        ]);
        
        // Supprimer les messages après les avoir affichés
        if (isset($_SESSION['profile_success'])) {
            unset($_SESSION['profile_success']);
        }
        
        if (isset($_SESSION['profile_error'])) {
            unset($_SESSION['profile_error']);
        }
    }
    
    /**
     * Mise à jour du profil utilisateur
     * 
     * @return void
     */
    public function updateProfile()
    {
        // Vérifier si la méthode est POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /User/profile');
            exit;
        }
        
        // Récupérer les données du formulaire
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
        
        // Vérifier que les champs obligatoires ne sont pas vides
        if (empty($username) || empty($email)) {
            $_SESSION['profile_error'] = 'Le nom d\'utilisateur et l\'email sont obligatoires.';
            header('Location: /User/profile');
            exit;
        }
        
        // Vérifier que l'email est valide
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_error'] = 'L\'adresse email n\'est pas valide.';
            header('Location: /User/profile');
            exit;
        }
        
        // Récupérer l'utilisateur actuel
        $userModel = new UserModel();
        $user = $userModel->getUserById($_SESSION['user_id']);
        
        // Vérifier que le nom d'utilisateur n'est pas déjà utilisé par un autre utilisateur
        $existingUser = $userModel->getUserByUsername($username);
        if ($existingUser && $existingUser['id'] != $user['id']) {
            $_SESSION['profile_error'] = 'Ce nom d\'utilisateur est déjà utilisé.';
            header('Location: /User/profile');
            exit;
        }
        
        // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
        $existingUser = $userModel->getUserByEmail($email);
        if ($existingUser && $existingUser['id'] != $user['id']) {
            $_SESSION['profile_error'] = 'Cette adresse email est déjà utilisée.';
            header('Location: /User/profile');
            exit;
        }
        
        // Si un nouveau mot de passe est fourni, vérifier le mot de passe actuel
        if (!empty($newPassword)) {
            // Vérifier que le mot de passe actuel est correct
            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                $_SESSION['profile_error'] = 'Le mot de passe actuel est incorrect.';
                header('Location: /User/profile');
                exit;
            }
            
            // Vérifier que les nouveaux mots de passe correspondent
            if ($newPassword !== $newPasswordConfirm) {
                $_SESSION['profile_error'] = 'Les nouveaux mots de passe ne correspondent pas.';
                header('Location: /User/profile');
                exit;
            }
            
            // Vérifier la force du nouveau mot de passe
            if (strlen($newPassword) < 8) {
                $_SESSION['profile_error'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
                header('Location: /User/profile');
                exit;
            }
        }
        
        // Mettre à jour le profil
        $result = $userModel->updateUser(
            $user['id'],
            $username,
            $email,
            !empty($newPassword) ? $newPassword : null
        );
        
        if ($result) {
            // Mise à jour réussie
            $_SESSION['profile_success'] = 'Profil mis à jour avec succès.';
            
            // Mettre à jour la session
            $updatedUser = $userModel->getUserById($user['id']);
            $_SESSION['user'] = $updatedUser;
        } else {
            // Erreur lors de la mise à jour
            $_SESSION['profile_error'] = 'Une erreur est survenue lors de la mise à jour du profil.';
        }
        
        header('Location: /User/profile');
        exit;
    }
    
    /**
     * Page pour récupérer son mot de passe
     * 
     * @return void
     */
    public function forgotPassword()
    {
        global $twig;
        
        // Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
        if (isset($_SESSION['user_id'])) {
            header('Location: /Dashboard');
            exit;
        }
        
        // Afficher le template de récupération de mot de passe
        echo $twig->render('Core/Modules/User/Views/forgot-password.twig', [
            'success' => isset($_SESSION['forgot_success']) ? $_SESSION['forgot_success'] : null,
            'error' => isset($_SESSION['forgot_error']) ? $_SESSION['forgot_error'] : null
        ]);
        
        // Supprimer les messages après les avoir affichés
        if (isset($_SESSION['forgot_success'])) {
            unset($_SESSION['forgot_success']);
        }
        
        if (isset($_SESSION['forgot_error'])) {
            unset($_SESSION['forgot_error']);
        }
    }
    
    /**
     * Traitement de la récupération de mot de passe
     * 
     * @return void
     */
    public function doForgotPassword()
    {
        // Vérifier si la méthode est POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /User/forgot-password');
            exit;
        }
        
        // Récupérer l'email
        $email = $_POST['email'] ?? '';
        
        // Vérifier que l'email n'est pas vide
        if (empty($email)) {
            $_SESSION['forgot_error'] = 'Veuillez entrer votre adresse email.';
            header('Location: /User/forgot-password');
            exit;
        }
        
        // Vérifier que l'email est valide
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['forgot_error'] = 'L\'adresse email n\'est pas valide.';
            header('Location: /User/forgot-password');
            exit;
        }
        
        // Vérifier que l'utilisateur existe
        $userModel = new UserModel();
        $user = $userModel->getUserByEmail($email);
        
        if (!$user) {
            // Ne pas révéler que l'email n'existe pas (sécurité)
            $_SESSION['forgot_success'] = 'Si votre adresse email existe dans notre base de données, vous recevrez un email avec les instructions pour réinitialiser votre mot de passe.';
            header('Location: /User/forgot-password');
            exit;
        }
        
        // Générer un token de réinitialisation
        $token = bin2hex(random_bytes(32));
        $userModel->saveResetToken($user['id'], $token);
        
        // Envoyer l'email (dans un environnement de production)
        // Pour ce projet, nous simulons l'envoi d'email
        
        // Afficher un message de succès
        $_SESSION['forgot_success'] = 'Un email avec les instructions pour réinitialiser votre mot de passe a été envoyé à ' . $email;
        header('Location: /User/forgot-password');
        exit;
    }
}