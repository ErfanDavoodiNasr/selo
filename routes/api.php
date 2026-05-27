<?php

use App\Controllers\AuthController;
use App\Controllers\ConversationController;
use App\Controllers\GroupController;
use App\Controllers\MediaController;
use App\Controllers\MessageController;
use App\Controllers\ProfileController;
use App\Controllers\StreamController;
use App\Controllers\UploadController;
use App\Controllers\UserController;
use App\Http\Controllers\ApiActionController;
use Illuminate\Support\Facades\Route;

$api = static fn (callable $handler) => ApiActionController::call($handler);

Route::post('/register', fn () => $api(fn () => AuthController::register($GLOBALS['config'])));
Route::post('/login', fn () => $api(fn () => AuthController::login($GLOBALS['config'])));
Route::post('/logout', fn () => $api(fn () => AuthController::logout($GLOBALS['config'])));
Route::post('/token/refresh', fn () => $api(fn () => AuthController::refresh($GLOBALS['config'])));

Route::get('/me', fn () => $api(fn () => UserController::me($GLOBALS['config'])));
Route::post('/me', fn () => $api(fn () => UserController::update($GLOBALS['config'])));
Route::patch('/me/settings', fn () => $api(fn () => UserController::updateSettings($GLOBALS['config'])));
Route::get('/users/search', fn () => $api(fn () => UserController::search($GLOBALS['config'])));

Route::post('/groups', fn () => $api(fn () => GroupController::create($GLOBALS['config'])));
Route::get('/groups/search', fn () => $api(fn () => GroupController::search($GLOBALS['config'])));
Route::post('/groups/join-by-link', fn () => $api(fn () => GroupController::joinByLink($GLOBALS['config'])));
Route::post('/groups/{token}/join', fn (string $token) => $api(fn () => GroupController::join($GLOBALS['config'], $token)))
    ->where('token', '[a-z0-9_]+');
Route::get('/groups/{groupId}/messages', fn (int $groupId) => $api(fn () => GroupController::listMessages($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');
Route::post('/groups/{groupId}/messages', fn (int $groupId) => $api(fn () => GroupController::sendMessage($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');
Route::post('/groups/{groupId}/invite', fn (int $groupId) => $api(fn () => GroupController::invite($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');
Route::delete('/groups/{groupId}/members/{memberId}', fn (int $groupId, int $memberId) => $api(fn () => GroupController::removeMember($GLOBALS['config'], $groupId, $memberId)))
    ->whereNumber('groupId')
    ->whereNumber('memberId');
Route::get('/groups/{slug}', fn (string $slug) => $api(fn () => GroupController::info($GLOBALS['config'], $slug)))
    ->where('slug', '[a-z0-9_]+');
Route::patch('/groups/{groupId}', fn (int $groupId) => $api(fn () => GroupController::update($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');

Route::get('/conversations', fn () => $api(fn () => ConversationController::list($GLOBALS['config'])));
Route::post('/conversations', fn () => $api(fn () => ConversationController::start($GLOBALS['config'])));
Route::patch('/conversations/settings', fn () => $api(fn () => ConversationController::updateSettings($GLOBALS['config'])));

Route::get('/messages', fn () => $api(fn () => MessageController::list($GLOBALS['config'])));
Route::get('/unread-count', fn () => $api(fn () => MessageController::unreadCount($GLOBALS['config'])));
Route::post('/messages', fn () => $api(fn () => MessageController::send($GLOBALS['config'])));
Route::post('/messages/edit', fn () => $api(fn () => MessageController::edit($GLOBALS['config'])));
Route::post('/messages/ack', fn () => $api(fn () => MessageController::ack($GLOBALS['config'])));
Route::post('/messages/mark-read', fn () => $api(fn () => MessageController::markRead($GLOBALS['config'])));
Route::get('/messages/status', fn () => $api(fn () => MessageController::status($GLOBALS['config'])));
Route::put('/messages/{messageId}/reaction', fn (int $messageId) => $api(fn () => MessageController::react($GLOBALS['config'], $messageId)))
    ->whereNumber('messageId');
Route::delete('/messages/{messageId}/reaction', fn (int $messageId) => $api(fn () => MessageController::removeReaction($GLOBALS['config'], $messageId)))
    ->whereNumber('messageId');
Route::get('/messages/{messageId}/reactions', fn (int $messageId) => $api(fn () => MessageController::reactions($GLOBALS['config'], $messageId)))
    ->whereNumber('messageId');
Route::post('/messages/delete-for-me', fn () => $api(fn () => MessageController::deleteForMe($GLOBALS['config'])));
Route::post('/messages/delete-for-everyone', fn () => $api(fn () => MessageController::deleteForEveryone($GLOBALS['config'])));

Route::post('/uploads', fn () => $api(fn () => UploadController::upload($GLOBALS['config'])));
Route::get('/stream', fn () => $api(fn () => StreamController::stream($GLOBALS['config'])));
Route::get('/poll', fn () => $api(fn () => StreamController::poll($GLOBALS['config'])));
Route::get('/media/{mediaId}', fn (int $mediaId) => $api(fn () => MediaController::serve($GLOBALS['config'], $mediaId)))
    ->whereNumber('mediaId');

Route::post('/profile/photo', fn () => $api(fn () => ProfileController::uploadPhoto($GLOBALS['config'])));
Route::post('/profile/photo/active', fn () => $api(fn () => ProfileController::setActive($GLOBALS['config'])));
Route::delete('/profile/photo/{photoId}', fn (int $photoId) => $api(fn () => ProfileController::deletePhoto($GLOBALS['config'], $photoId)))
    ->whereNumber('photoId');
