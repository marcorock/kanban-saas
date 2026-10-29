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
