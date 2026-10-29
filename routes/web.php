<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use Pecee\SimpleRouter\SimpleRouter;

$home = new HomeController($views, $config['name']);
$auth = new AuthController($views, $config);
SimpleRouter::get('/', static fn (): string => $home->index());
SimpleRouter::get('/register', static fn (): string => $auth->form(true));
SimpleRouter::post('/register', static fn (): string => $auth->submit(true));
SimpleRouter::get('/login', static fn (): string => $auth->form(false));
SimpleRouter::post('/login', static fn (): string => $auth->submit(false));
SimpleRouter::post('/logout', static fn (): string => $auth->logout());
SimpleRouter::get('/dashboard', static fn (): string => $auth->dashboard());

$account = new \App\Controllers\AccountController($views, $config);
SimpleRouter::get('/password/forgot', static fn (): string => $account->page('forgot'));
SimpleRouter::post('/password/forgot', static fn (): string => $account->submit('forgot'));
SimpleRouter::get('/password/reset', static fn (): string => $account->page('reset'));
SimpleRouter::post('/password/reset', static fn (): string => $account->submit('reset'));
SimpleRouter::get('/email/resend', static fn (): string => $account->page('resend'));
SimpleRouter::post('/email/resend', static fn (): string => $account->submit('resend'));
SimpleRouter::get('/email/verify', static fn (): string => $account->page('verify'));
SimpleRouter::post('/email/verify', static fn (): string => $account->submit('verify'));

$projects = new \App\Controllers\ProjectController($views, $config);
SimpleRouter::get('/projects', static fn (): string => $projects->dispatch('index'));
SimpleRouter::get('/projects/new', static fn (): string => $projects->dispatch('new'));
SimpleRouter::post('/projects', static fn (): string => $projects->dispatch('create'));
SimpleRouter::get('/projects/{id}/edit', static fn (string $id): string => $projects->dispatch('edit', $id));
SimpleRouter::get('/projects/{id}', static fn (string $id): string => $projects->dispatch('show', $id));
SimpleRouter::post('/projects/{id}', static fn (string $id): string => $projects->dispatch('update', $id));
SimpleRouter::post('/projects/{id}/archive', static fn (string $id): string => $projects->dispatch('archive', $id));
SimpleRouter::post('/projects/{id}/restore', static fn (string $id): string => $projects->dispatch('restore', $id));
SimpleRouter::post('/projects/{id}/delete', static fn (string $id): string => $projects->dispatch('delete', $id));

SimpleRouter::get('/projects/{id}/members', static fn (string $id): string => $projects->dispatch('members', $id));
SimpleRouter::post('/projects/{id}/invitations', static fn (string $id): string => $projects->dispatch('invite', $id));
SimpleRouter::post('/projects/{id}/invitations/cancel', static fn (string $id): string => $projects->dispatch('cancel-invitation', $id));
SimpleRouter::post('/projects/{id}/members/role', static fn (string $id): string => $projects->dispatch('member-role', $id));
SimpleRouter::post('/projects/{id}/members/remove', static fn (string $id): string => $projects->dispatch('member-remove', $id));
SimpleRouter::post('/projects/{id}/transfer', static fn (string $id): string => $projects->dispatch('transfer', $id));
$invitations = new \App\Controllers\InvitationController($views, $config);
SimpleRouter::get('/invitations/accept', static fn (): string => $invitations->handle(false));
SimpleRouter::post('/invitations/accept', static fn (): string => $invitations->handle(true));

$board = new \App\Controllers\BoardController($views, $config);
SimpleRouter::get('/projects/{id}/board', static fn (string $id): string => $board->handle($id, false));
SimpleRouter::post('/projects/{id}/board', static fn (string $id): string => $board->handle($id, true));

SimpleRouter::get('/projects/{id}/tasks/{taskId}', static fn (string $id, string $taskId): string => $board->handle($id, false, $taskId));
