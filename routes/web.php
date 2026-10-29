<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Pecee\SimpleRouter\SimpleRouter;

$home = new HomeController($views, $config['name']);
SimpleRouter::get('/', static fn (): string => $home->index());
