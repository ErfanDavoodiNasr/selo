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
use App\Http\Controllers\LegacyApiController;
use Illuminate\Support\Facades\Route;

$legacy = static fn (callable $handler) => LegacyApiController::call($handler);

Route::post('/register', fn () => $legacy(fn () => AuthController::register($GLOBALS['config'])));
Route::post('/login', fn () => $legacy(fn () => AuthController::login($GLOBALS['config'])));
Route::post('/logout', fn () => $legacy(fn () => AuthController::logout($GLOBALS['config'])));
Route::post('/token/refresh', fn () => $legacy(fn () => AuthController::refresh($GLOBALS['config'])));

Route::get('/me', fn () => $legacy(fn () => UserController::me($GLOBALS['config'])));
Route::post('/me', fn () => $legacy(fn () => UserController::update($GLOBALS['config'])));
Route::patch('/me/settings', fn () => $legacy(fn () => UserController::updateSettings($GLOBALS['config'])));
Route::get('/users/search', fn () => $legacy(fn () => UserController::search($GLOBALS['config'])));

Route::post('/groups', fn () => $legacy(fn () => GroupController::create($GLOBALS['config'])));
Route::get('/groups/search', fn () => $legacy(fn () => GroupController::search($GLOBALS['config'])));
Route::post('/groups/join-by-link', fn () => $legacy(fn () => GroupController::joinByLink($GLOBALS['config'])));
Route::post('/groups/{token}/join', fn (string $token) => $legacy(fn () => GroupController::join($GLOBALS['config'], $token)))
    ->where('token', '[a-z0-9_]+');
Route::get('/groups/{groupId}/messages', fn (int $groupId) => $legacy(fn () => GroupController::listMessages($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');
Route::post('/groups/{groupId}/messages', fn (int $groupId) => $legacy(fn () => GroupController::sendMessage($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');
Route::post('/groups/{groupId}/invite', fn (int $groupId) => $legacy(fn () => GroupController::invite($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');
Route::delete('/groups/{groupId}/members/{memberId}', fn (int $groupId, int $memberId) => $legacy(fn () => GroupController::removeMember($GLOBALS['config'], $groupId, $memberId)))
    ->whereNumber('groupId')
    ->whereNumber('memberId');
Route::get('/groups/{slug}', fn (string $slug) => $legacy(fn () => GroupController::info($GLOBALS['config'], $slug)))
    ->where('slug', '[a-z0-9_]+');
Route::patch('/groups/{groupId}', fn (int $groupId) => $legacy(fn () => GroupController::update($GLOBALS['config'], $groupId)))
    ->whereNumber('groupId');

Route::get('/conversations', fn () => $legacy(fn () => ConversationController::list($GLOBALS['config'])));
Route::post('/conversations', fn () => $legacy(fn () => ConversationController::start($GLOBALS['config'])));

Route::get('/messages', fn () => $legacy(fn () => MessageController::list($GLOBALS['config'])));
Route::get('/unread-count', fn () => $legacy(fn () => MessageController::unreadCount($GLOBALS['config'])));
Route::post('/messages', fn () => $legacy(fn () => MessageController::send($GLOBALS['config'])));
Route::post('/messages/edit', fn () => $legacy(fn () => MessageController::edit($GLOBALS['config'])));
Route::post('/messages/ack', fn () => $legacy(fn () => MessageController::ack($GLOBALS['config'])));
Route::post('/messages/mark-read', fn () => $legacy(fn () => MessageController::markRead($GLOBALS['config'])));
Route::get('/messages/status', fn () => $legacy(fn () => MessageController::status($GLOBALS['config'])));
Route::put('/messages/{messageId}/reaction', fn (int $messageId) => $legacy(fn () => MessageController::react($GLOBALS['config'], $messageId)))
    ->whereNumber('messageId');
Route::delete('/messages/{messageId}/reaction', fn (int $messageId) => $legacy(fn () => MessageController::removeReaction($GLOBALS['config'], $messageId)))
    ->whereNumber('messageId');
Route::get('/messages/{messageId}/reactions', fn (int $messageId) => $legacy(fn () => MessageController::reactions($GLOBALS['config'], $messageId)))
    ->whereNumber('messageId');
Route::post('/messages/delete-for-me', fn () => $legacy(fn () => MessageController::deleteForMe($GLOBALS['config'])));
Route::post('/messages/delete-for-everyone', fn () => $legacy(fn () => MessageController::deleteForEveryone($GLOBALS['config'])));

Route::post('/uploads', fn () => $legacy(fn () => UploadController::upload($GLOBALS['config'])));
Route::get('/stream', fn () => $legacy(fn () => StreamController::stream($GLOBALS['config'])));
Route::get('/poll', fn () => $legacy(fn () => StreamController::poll($GLOBALS['config'])));
Route::get('/media/{mediaId}', fn (int $mediaId) => $legacy(fn () => MediaController::serve($GLOBALS['config'], $mediaId)))
    ->whereNumber('mediaId');

Route::post('/profile/photo', fn () => $legacy(fn () => ProfileController::uploadPhoto($GLOBALS['config'])));
Route::post('/profile/photo/active', fn () => $legacy(fn () => ProfileController::setActive($GLOBALS['config'])));
Route::delete('/profile/photo/{photoId}', fn (int $photoId) => $legacy(fn () => ProfileController::deletePhoto($GLOBALS['config'], $photoId)))
    ->whereNumber('photoId');
