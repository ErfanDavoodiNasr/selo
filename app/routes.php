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
use App\Controllers\StreamController;

$router = new Router();

$router->add('POST', '#^/api/register$#', function () use ($config) {
    AuthController::register($config);
});
$router->add('POST', '#^/api/login$#', function () use ($config) {
    AuthController::login($config);
});
$router->add('POST', '#^/api/logout$#', function () use ($config) {
    AuthController::logout($config);
});
$router->add('POST', '#^/api/token/refresh$#', function () use ($config) {
    AuthController::refresh($config);
});
$router->add('GET', '#^/api/me$#', function () use ($config) {
    UserController::me($config);
});
$router->add('POST', '#^/api/me$#', function () use ($config) {
    UserController::update($config);
});
$router->add('PATCH', '#^/api/me/settings$#', function () use ($config) {
    UserController::updateSettings($config);
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
$router->add('POST', '#^/api/groups/([a-z0-9_]+)/join$#', function ($token) use ($config) {
    GroupController::join($config, $token);
});
$router->add('GET', '#^/api/groups/(\\d+)/messages$#', function ($groupId) use ($config) {
    GroupController::listMessages($config, (int)$groupId);
});
$router->add('POST', '#^/api/groups/(\\d+)/messages$#', function ($groupId) use ($config) {
    GroupController::sendMessage($config, (int)$groupId);
});
$router->add('POST', '#^/api/groups/(\\d+)/invite$#', function ($groupId) use ($config) {
    GroupController::invite($config, (int)$groupId);
});
$router->add('DELETE', '#^/api/groups/(\\d+)/members/(\\d+)$#', function ($groupId, $memberId) use ($config) {
    GroupController::removeMember($config, (int)$groupId, (int)$memberId);
});
$router->add('GET', '#^/api/groups/([a-z0-9_]+)$#', function ($slug) use ($config) {
    GroupController::info($config, $slug);
});
$router->add('PATCH', '#^/api/groups/(\\d+)$#', function ($groupId) use ($config) {
    GroupController::update($config, (int)$groupId);
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
$router->add('GET', '#^/api/unread-count$#', function () use ($config) {
    MessageController::unreadCount($config);
});
$router->add('POST', '#^/api/messages$#', function () use ($config) {
    MessageController::send($config);
});
$router->add('POST', '#^/api/messages/edit$#', function () use ($config) {
    MessageController::edit($config);
});
$router->add('POST', '#^/api/messages/ack$#', function () use ($config) {
    MessageController::ack($config);
});
$router->add('POST', '#^/api/messages/mark-read$#', function () use ($config) {
    MessageController::markRead($config);
});
$router->add('GET', '#^/api/messages/status$#', function () use ($config) {
    MessageController::status($config);
});
$router->add('PUT', '#^/api/messages/(\\d+)/reaction$#', function ($messageId) use ($config) {
    MessageController::react($config, (int)$messageId);
});
$router->add('DELETE', '#^/api/messages/(\\d+)/reaction$#', function ($messageId) use ($config) {
    MessageController::removeReaction($config, (int)$messageId);
});
$router->add('GET', '#^/api/messages/(\\d+)/reactions$#', function ($messageId) use ($config) {
    MessageController::reactions($config, (int)$messageId);
});
$router->add('POST', '#^/api/uploads$#', function () use ($config) {
    UploadController::upload($config);
});
$router->add('GET', '#^/api/stream$#', function () use ($config) {
    StreamController::stream($config);
});
$router->add('GET', '#^/api/poll$#', function () use ($config) {
    StreamController::poll($config);
});
$router->add('GET', '#^/api/media/(\\d+)$#', function ($mediaId) use ($config) {
    MediaController::serve($config, (int)$mediaId);
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
$router->add('DELETE', '#^/api/profile/photo/(\\d+)$#', function ($photoId) use ($config) {
    ProfileController::deletePhoto($config, (int)$photoId);
});
$router->dispatch($_SERVER['REQUEST_METHOD'], $path);
