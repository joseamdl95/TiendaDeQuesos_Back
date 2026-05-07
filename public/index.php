<?php

date_default_timezone_set('Europe/Madrid');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/Auth.php';
require_once __DIR__ . '/../src/utils/TwoFactor.php';
require_once __DIR__ . '/../src/utils/JWT.php';
require_once __DIR__ . '/../src/utils/Key.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/UserController.php';
require_once __DIR__ . '/../src/controllers/ProductController.php';
require_once __DIR__ . '/../src/controllers/CarritoController.php';
require_once __DIR__ . '/../src/controllers/CheckoutController.php';
require_once __DIR__ . '/../src/controllers/OrderController.php';
require_once __DIR__ . '/../src/controllers/AdressController.php';


use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Limpieza de ruta
$uri = str_replace('/TiendaDeQuesos_Back', '', $uri);
$uri = str_replace('/index.php', '', $uri);
$uri = rtrim($uri, '/');


if ($uri === '/ping' && $method === 'GET') {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Backend Tienda de Quesos funcionando (Apache)'
    ]);
    exit;
}



// AUTH ROUTES
if ($uri === '/auth/register' && $method === 'POST') {
    AuthController::register($pdo);
    exit;
}

if ($uri === '/auth/login' && $method === 'POST') {
    AuthController::login($pdo);
    exit;
}

if ($uri === '/auth/verify-2fa' && $method === 'POST') {
    AuthController::verify2FALogin($pdo);
    exit;
}

if ($uri === '/auth/forgot-password' && $method === 'POST') {
    AuthController::forgotPassword($pdo);
    exit;
}

if ($uri === '/auth/reset-password' && $method === 'POST') {
    AuthController::resetPassword($pdo);
    exit;
}

if ($uri === '/auth/me' && $method === 'GET') {

    require_once __DIR__ . '/../src/utils/Auth.php';

    $userId = Auth::check();

    $stmt = $pdo->prepare("
        SELECT id_usuario, email, nombre, apellidos, telefono, 2fa_activo, rol
        FROM usuario
        WHERE id_usuario = :id
    ");

    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'user' => $user
    ]);

    exit;
}

//Usuario
if ($uri === '/user/datos' && $method === 'PUT') {
    UserController::updateDatos($pdo);
    exit;
}

if ($uri === '/user/email' && $method === 'PUT') {
    UserController::updateEmail($pdo);
    exit;
}

if ($uri === '/user/password' && $method === 'PUT') {
    UserController::updatePassword($pdo);
    exit;
}

if ($uri === '/user/2fa/enable' && $method === 'POST') {
    UserController::enable2FA($pdo);
    exit;
}


if ($uri === '/user/2fa/verify' && $method === 'POST') {
    UserController::verify2FA($pdo);
    exit;
}

if ($uri === '/user/2fa/disable' && $method === 'POST'){
    UserController::disable2FA($pdo);
    exit;
}

//Direcciones
if ($uri === '/addresses' && $method === 'GET') {
    AddressController::getAll($pdo);
    exit;
}

if (preg_match('#^/addresses/([^/]+)$#', $uri, $matches) && $method === 'GET') {
    AddressController::getOne($pdo, $matches[1]);
    exit;
}

if ($uri === '/addresses' && $method === 'POST') {
    AddressController::create($pdo);
    exit;
}

if (preg_match('#^/addresses/([^/]+)$#', $uri, $matches) && $method === 'PUT') {
    AddressController::update($pdo, $matches[1]);
    exit;
}

if (preg_match('#^/addresses/([^/]+)$#', $uri, $matches) && $method === 'DELETE') {
    AddressController::delete($pdo, $matches[1]);
    exit;
}


//productos
// Listar productos usuario
if ($uri === '/products' && $method === 'GET') {
    ProductController::getAll($pdo);
    exit;
}

//listar productos admin
if ($uri === '/admin/products' && $method === 'GET') {
    ProductController::getAllAdmin($pdo);
    exit;
}

// DETALLE PRODUCTO
if (preg_match('#^/products/([a-zA-Z0-9\-]+)$#', $uri, $matches) && $method === 'GET') {
    ProductController::getOne($pdo, $matches[1]);
    exit;
}

//Añadir Producto
if ($uri === '/admin/products' && $method === 'POST') {
    ProductController::create($pdo);
    exit;
}

//Modificar producto
if (preg_match('#^/admin/products/([a-zA-Z0-9\-]+)$#', $uri, $matches) && $method === 'PUT') {
    ProductController::update($pdo, $matches[1]);
    exit;
}


//Carrito
if ($uri === '/cart' && $method === 'GET') {
    CarritoController::getCart($pdo);
    exit;
}

if ($uri === '/cart/add' && $method === 'POST') {
    CarritoController::add($pdo);
    exit;
}

if ($uri === '/cart/update' && $method === 'PUT') {
    CarritoController::update($pdo);
    exit;
}

if ($uri === '/cart/remove' && $method === 'DELETE') {
    CarritoController::remove($pdo);
    exit;
}

if ($uri === '/cart/merge' && $method === 'POST') {
    CarritoController::merge($pdo);
    exit;
}

if ($uri === '/cart/preview' && $method === 'POST') {
    CarritoController::preview($pdo);
    exit;
}

//Checkout
if($uri == '/checkout' && $method === 'POST'){
    CheckoutController::checkout($pdo);
    exit;
}

//Pedidos
if ($uri === '/orders' && $method === 'GET') {
    OrderController::getOrders($pdo);
    exit;
}

if (preg_match('#^/orders/([^/]+)$#', $uri, $matches) && $method === 'GET') {
    OrderController::getOrder($pdo, $matches[1]);
    exit;
}

if ($uri === '/admin/orders' && $method === 'GET') {
    OrderController::getOrdersAdmin($pdo);
    exit;
}

if (preg_match('#^/admin/orders/([^/]+)$#', $uri, $matches) && $method === 'GET') {
    OrderController::getOrderAdmin($pdo, $matches[1]);
    exit;
}

if (preg_match('#^/admin/orders/([^/]+)$#', $uri, $matches) && $method === 'PUT') {
    OrderController::updateStatus($pdo, $matches[1]);
    exit;
}


