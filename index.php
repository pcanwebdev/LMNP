<?php
/**
 * Main entry point for the LMNP Accounting Application
 * This file bootstraps the application and directs requests to the appropriate modules
 * 
 * @author LMNP Accounting System
 * @version 1.0
 */

// Define application root path
define('APP_ROOT', __DIR__);

// Load bootstrap file
require_once 'bootstrap.php';

// Get request URI
$uri = $_SERVER['REQUEST_URI'];

// Initialize the router
$router = new \Core\Router();

// Route the request to the appropriate controller
$router->dispatch($uri);