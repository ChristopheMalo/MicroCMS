<?php

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * MicroCMS
 * =========================================================================================================
 *
 * Fichier contenant le paramétrage de l'application Silex - Controller app
 * 
 * @author      Christophe Malo
 * @date        29/02/2016
 * @update      06/03/2016
 * @version     1.0.6
 * @copyright   OpenClassrooms - Baptiste Pesquet
 * 
 * @commentaire v1.0.1 du 01/03/2016 : intégrer moteur template Twig au projet
 *              v1.0.2 du 02/03/2016 : enregistrer le service d'accès aux commentaires
 *              v1.0.3 du 04/03/2016 : enregistrer fournisseurs et service de sécurité
 *              v1.0.4 du 05/03/2016 : enregistrer fournisseurs formulaire
 *              v1.0.5 du 05/03/2016 : back office
 *              v1.0.6 du 06/02/2016 : gestionnaire d'erreur
 */

/**
 * Configuration de Silex pour gérer les potentielles erreurs
 * pendant l'execution de l'application
 */
ErrorHandler::register();
ExceptionHandler::register();


/** Enregistrement des fournisseurs de services **/

// Enregistre le fournisseur de services associé à DBAL -> DoctrineServiceProvider
$app->register(new Silex\Provider\DoctrineServiceProvider());

// Enregistre Twig vers le chemin du dossier de templates (views)
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
));

// Enregistre l'extension Text de Twig (pour utilisation de truncate
$app['twig'] = $app->share($app->extend('twig', function(Twig_Environment $twig, $app) {
    $twig->addExtension(new Twig_Extensions_Extension_Text());
    return $twig;
}));

// Enregistre le fournisseur de service Url associé au composant twig-bridge
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Enregistre les fournisseurs de services liés à la sécurité
$app->register(new Silex\Provider\SessionServiceProvider()); // Démarre automatiquement la gestion des sessions PHP
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'secured' => array(
            'pattern' => '^/', // Définit la partie sécurisée du site - ici intégralité du site
            'anonymous' => true, // Un user non authentifié peut accéder aux parties sécurisées - ici lire les articles
            'logout' => true, // Possibilité de déconnexion pour les user authentifiés
            'form' => array('login_path' => '/login', 'check_path' => '/login_check'), // Utilisation d'un formulaire pour l'authentification
            'users' => $app->share(function () use ($app) {
                return new MicroCMS\DAO\UserDAO($app['db']); // Définit le fournisseur qui permet d'accéder aux utilisateurs
            }),
        ),
    ),              
    // Soumettre l'accès au back office                
    'security.role_hierarchy' => array(
        'ROLE_ADMIN' => array('ROLE_USER'), // Définir une hierarchie
    ),
    'security.access_rules' => array(
        array('^/admin', 'ROLE_ADMIN'), // Protéger la zone /admin
    ),
));
            
// Enregistre les fournisseurs de services liés au formulaire
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());

// Enregistre fournisseurs de services pour les logs
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../var/logs/microcms.log',
    'monolog.name' => 'MicroCMS',
    'monolog.level' => $app['monolog.level']
));
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
if (isset($app['debug']) && $app['debug']) {
    $app->register(new Silex\Provider\HttpFragmentServiceProvider());
    $app->register(new Silex\Provider\WebProfilerServiceProvider(), array(
        'profiler.cache_dir' => __DIR__.'/../var/cache/profiler'
    ));
}




/** Enregistrement des services **/

// Enregistre un service (dao.article) sous forme
// d'une instance partagée de la classe ArticleDAO

$app['dao.article'] = $app->share(function ($app) {
    return new MicroCMS\DAO\ArticleDAO($app['db']);
});

// Enregistre le service user
$app['dao.user'] = $app->share(function ($app) {
    return new MicroCMS\DAO\UserDAO($app['db']);
});

// Enregistre le service d'accès aux commentaires
$app['dao.comment'] = $app->share(function ($app) {
    $commentDAO = new MicroCMS\DAO\CommentDAO($app['db']);
    $commentDAO->setArticleDAO($app['dao.article']);
    $commentDAO->setUserDAO($app['dao.user']);
    return $commentDAO;
});

// Enregistre le service de gestion personnalisé d'erreurs
$app->error(function (\Exception $e, $code) use ($app) {
    // Construction d'un message d'erreur
    switch ($code) {
        case 403:
            $message = 'Access denied.';
            break;
        case 404:
            $message = 'The requested resource could not be found.';
            break;
        default:
            $message = "Something went wrong.";
    }
    
    // En fonction du message d'erreur, la vue est générée
    return $app['twig']->render('error.html.twig', array('message' => $message));
});

// API : enregistre le décodeur de données JSON pour les demandes JSON
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});
