<?php

declare(strict_types=1);

namespace App\Controllers;

use Twig\Environment;

final class HomeController
{
    public function __construct(private Environment $views, private string $name)
    {
    }

    public function index(): string
    {
        return $this->views->render('home.html.twig', ['projectName' => $this->name]);
    }
}
