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
