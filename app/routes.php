<?php
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ConversationController;
use App\Controllers\MessageController;
use App\Controllers\UploadController;
use App\Controllers\MediaController;
use App\Controllers\ProfileController;
use App\Controllers\GroupController;

$router = new Router();

$router->add('POST', '#^/api/register$#', function () use ($config) {
    AuthController::register($config);
});
$router->add('POST', '#^/api/login$#', function () use ($config) {
    AuthController::login($config);
});
$router->add('GET', '#^/api/me$#', function () use ($config) {
    UserController::me($config);
});
$router->add('POST', '#^/api/me$#', function () use ($config) {
    UserController::update($config);
});
$router->add('GET', '#^/api/users/search$#', function () use ($config) {
    UserController::search($config);
});
$router->add('POST', '#^/api/groups$#', function () use ($config) {
    GroupController::create($config);
});
$router->add('POST', '#^/api/groups/join-by-link$#', function () use ($config) {
    GroupController::joinByLink($config);
});
$router->add('POST', '#^/api/groups/([a-z0-9_]+)/join$#', function ($matches) use ($config) {
    GroupController::join($config, $matches[1]);
});
$router->add('GET', '#^/api/groups/(\\d+)/messages$#', function ($matches) use ($config) {
    GroupController::listMessages($config, (int)$matches[1]);
});
$router->add('POST', '#^/api/groups/(\\d+)/messages$#', function ($matches) use ($config) {
    GroupController::sendMessage($config, (int)$matches[1]);
});
$router->add('POST', '#^/api/groups/(\\d+)/invite$#', function ($matches) use ($config) {
    GroupController::invite($config, (int)$matches[1]);
});
$router->add('DELETE', '#^/api/groups/(\\d+)/members/(\\d+)$#', function ($matches) use ($config) {
    GroupController::removeMember($config, (int)$matches[1], (int)$matches[2]);
});
$router->add('GET', '#^/api/groups/([a-z0-9_]+)$#', function ($matches) use ($config) {
    GroupController::info($config, $matches[1]);
});
$router->add('PATCH', '#^/api/groups/(\\d+)$#', function ($matches) use ($config) {
    GroupController::update($config, (int)$matches[1]);
});
$router->add('GET', '#^/api/conversations$#', function () use ($config) {
    ConversationController::list($config);
});
$router->add('POST', '#^/api/conversations$#', function () use ($config) {
    ConversationController::start($config);
});
$router->add('GET', '#^/api/messages$#', function () use ($config) {
    MessageController::list($config);
});
$router->add('POST', '#^/api/messages$#', function () use ($config) {
    MessageController::send($config);
});
$router->add('POST', '#^/api/uploads$#', function () use ($config) {
    UploadController::upload($config);
});
$router->add('GET', '#^/api/media/(\\d+)$#', function ($matches) use ($config) {
    MediaController::serve($config, (int)$matches[1]);
});
$router->add('POST', '#^/api/messages/delete-for-me$#', function () use ($config) {
    MessageController::deleteForMe($config);
});
$router->add('POST', '#^/api/messages/delete-for-everyone$#', function () use ($config) {
    MessageController::deleteForEveryone($config);
});
$router->add('POST', '#^/api/profile/photo$#', function () use ($config) {
    ProfileController::uploadPhoto($config);
});
$router->add('POST', '#^/api/profile/photo/active$#', function () use ($config) {
    ProfileController::setActive($config);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
