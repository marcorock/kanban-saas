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
$workspaces = new \App\Controllers\WorkspaceController($views, $config);
SimpleRouter::get('/dashboard', static fn (): string => $auth->dashboard());
SimpleRouter::get('/workspaces/new', static fn (): string => $workspaces->handle('new'));
SimpleRouter::post('/workspaces', static fn (): string => $workspaces->handle('create'));
SimpleRouter::post('/settings/workspace', static fn (): string => $workspaces->handle('default'));

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

$reports = new \App\Controllers\ReportingController($views, $config);
SimpleRouter::get('/tasks', static fn (): string => $reports->handle('tasks'));
SimpleRouter::get('/history', static fn (): string => $reports->handle('history'));
SimpleRouter::get('/projects/{id}/history', static fn (string $id): string => $reports->handle('history', $id));

$profile = new \App\Controllers\ProfileController($views, $config);
SimpleRouter::get('/settings', static fn (): string => $profile->handle(false));
SimpleRouter::post('/settings', static fn (): string => $profile->handle(true));

$notifications = new \App\Controllers\NotificationController($views, $config);
SimpleRouter::get('/notifications', static fn (): string => $notifications->handle('index'));
SimpleRouter::post('/notifications/{id}/read', static fn (string $id): string => $notifications->handle('read', $id));
SimpleRouter::get('/workspaces/{workspaceId}/notifications', static fn (string $workspaceId): string => $notifications->handle('index', null, $workspaceId));
SimpleRouter::post('/workspaces/{workspaceId}/notifications/{id}/read', static fn (string $workspaceId, string $id): string => $notifications->handle('read', $id, $workspaceId));
SimpleRouter::get('/notifications/invitations', static fn (): string => $notifications->handle('invitations'));
SimpleRouter::post('/notifications/invitations/{id}/read', static fn (string $id): string => $notifications->handle('invitation-read', $id));
SimpleRouter::post('/settings/notifications', static fn (): string => $notifications->handle('preferences'));

$push = new \App\Controllers\PushController($config);
SimpleRouter::get('/notifications/push-config', static fn (): string => $push->handle('config'));
SimpleRouter::get('/notifications/push-context', static fn (): string => $push->handle('context'));
SimpleRouter::post('/settings/notifications/push', static fn (): string => $push->handle('save'));
SimpleRouter::post('/settings/notifications/push/remove', static fn (): string => $push->handle('remove'));

$notes = new \App\Controllers\QuickNoteController($views,$config);
SimpleRouter::get('/notes/version', static fn (): string => $notes->handle('version'));
SimpleRouter::get('/notes/state', static fn (): string => $notes->handle('state'));
SimpleRouter::get('/notes/new', static fn (): string => $notes->handle('new'));
SimpleRouter::get('/notes/projects', static fn (): string => $notes->handle('projects'));
SimpleRouter::get('/notes/columns', static fn (): string => $notes->handle('columns'));
SimpleRouter::post('/notes', static fn (): string => $notes->handle('create'));
SimpleRouter::post('/notes/{id}/delete', static fn (string $id): string => $notes->handle('delete',$id));
SimpleRouter::post('/notes/{id}/convert', static fn (string $id): string => $notes->handle('convert',$id));

SimpleRouter::post('/notes/{id}/update', static fn (string $id): string => $notes->handle('update',$id));

SimpleRouter::get('/notes/{id}/recipients', static fn (string $id): string => $notes->handle('recipients',$id));
SimpleRouter::post('/notes/{id}/transfer', static fn (string $id): string => $notes->handle('transfer',$id));

SimpleRouter::get('/projects/{id}/board/version', static fn (string $id): string => $board->handle($id,false,null,'version'));
SimpleRouter::get('/projects/{id}/board/state', static fn (string $id): string => $board->handle($id,false,null,'state'));

// Módulos delimitados pelo contexto da Área de Trabalho.
SimpleRouter::get('/workspaces/{workspaceId}/projects', static fn (string $workspaceId): string => $projects->dispatch('index', null, $workspaceId));
SimpleRouter::get('/workspaces/{workspaceId}/projects/new', static fn (string $workspaceId): string => $projects->dispatch('new', null, $workspaceId));
SimpleRouter::post('/workspaces/{workspaceId}/projects', static fn (string $workspaceId): string => $projects->dispatch('create', null, $workspaceId));
foreach (['dashboard', 'tasks', 'history'] as $mode) {
    SimpleRouter::get('/workspaces/{workspaceId}/' . $mode, static fn (string $workspaceId): string => $reports->handle($mode, null, $workspaceId));
}
foreach (['version','state','new','projects','columns'] as $mode) {
    SimpleRouter::get('/workspaces/{workspaceId}/notes/' . $mode, static fn (string $workspaceId): string => $notes->handle($mode, null, $workspaceId));
}
SimpleRouter::post('/workspaces/{workspaceId}/notes', static fn (string $workspaceId): string => $notes->handle('create', null, $workspaceId));
foreach (['delete','convert','update','transfer'] as $mode) {
    SimpleRouter::post('/workspaces/{workspaceId}/notes/{id}/' . $mode, static fn (string $workspaceId, string $id): string => $notes->handle($mode, $id, $workspaceId));
}
SimpleRouter::get('/workspaces/{workspaceId}/notes/{id}/recipients', static fn (string $workspaceId, string $id): string => $notes->handle('recipients', $id, $workspaceId));

// Recursos de projeto no mesmo contexto.
SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}', static fn (string $workspaceId, string $id): string => $projects->dispatch('show', $id, $workspaceId));
SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}/edit', static fn (string $workspaceId, string $id): string => $projects->dispatch('edit', $id, $workspaceId));
SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}/members', static fn (string $workspaceId, string $id): string => $projects->dispatch('members', $id, $workspaceId));
foreach (['' => 'update', '/archive' => 'archive', '/restore' => 'restore', '/delete' => 'delete', '/invitations' => 'invite', '/invitations/cancel' => 'cancel-invitation', '/members/role' => 'member-role', '/members/remove' => 'member-remove', '/transfer' => 'transfer'] as $suffix => $action) {
    SimpleRouter::post('/workspaces/{workspaceId}/projects/{id}' . $suffix, static fn (string $workspaceId, string $id): string => $projects->dispatch($action, $id, $workspaceId));
}
SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}/board', static fn (string $workspaceId, string $id): string => $board->handle($id, false, null, null, $workspaceId));
SimpleRouter::post('/workspaces/{workspaceId}/projects/{id}/board', static fn (string $workspaceId, string $id): string => $board->handle($id, true, null, null, $workspaceId));
SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}/tasks/{taskId}', static fn (string $workspaceId, string $id, string $taskId): string => $board->handle($id, false, $taskId, null, $workspaceId));
foreach (['version', 'state'] as $resource) {
    SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}/board/' . $resource, static fn (string $workspaceId, string $id): string => $board->handle($id, false, null, $resource, $workspaceId));
}
SimpleRouter::get('/workspaces/{workspaceId}/projects/{id}/history', static fn (string $workspaceId, string $id): string => $reports->handle('history', $id, $workspaceId));

$workspaceMembers = new \App\Controllers\WorkspaceMemberController($views, $config);
SimpleRouter::get('/workspace-invitations', static fn (): string => $workspaceMembers->handle('invitations'));
SimpleRouter::get('/workspace-invitations/accept', static fn (): string => $workspaceMembers->handle('accept'));
SimpleRouter::post('/workspace-invitations/accept', static fn (): string => $workspaceMembers->handle('accept'));
SimpleRouter::get('/workspaces/{workspaceId}/members', static fn (string $workspaceId): string => $workspaceMembers->handle('members', $workspaceId));
foreach (['invite', 'cancel', 'role', 'transfer', 'rename'] as $action) {
    SimpleRouter::post('/workspaces/{workspaceId}/members/' . $action, static fn (string $workspaceId): string => $workspaceMembers->handle($action, $workspaceId));
}
