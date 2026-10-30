<?php

declare(strict_types=1);
namespace App\Services;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class NotificationEmail
{
    public function __construct(private array $config) {}
    public function send(string $email,array $items,bool $deadlines): void
    {
        $views=new Environment(new FilesystemLoader(dirname(__DIR__,2).'/resources/views'),['autoescape'=>false,'strict_variables'=>true]);
        $body=$views->render('emails/notifications.txt.twig',['items'=>$items,'baseUrl'=>rtrim($this->config['url'],'/')]);
        (new Mailer($this->config['mail']))->send($email,$deadlines?'Resumo de prazos · Kanban':$items[0]['title'].' · Kanban',$body);
    }
}
