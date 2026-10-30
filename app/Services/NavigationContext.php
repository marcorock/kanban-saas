<?php

declare(strict_types=1);

namespace App\Services;

/** Contexto visual por rotas conhecidas. Não concede acesso ou permissões. */
final class NavigationContext
{
    public static function forPath(string $path): array
    {
        $path = rtrim($path, '/') ?: '/';
        $section = ''; $title = 'Seu espaço'; $caption = 'Um passo de cada vez';
        if ($path === '/dashboard') { $section = 'dashboard'; $title = 'Visão geral'; $caption = 'Seu trabalho, no seu ritmo'; }
        elseif ($path === '/tasks') { $section = 'tasks'; $title = 'Minhas tarefas'; $caption = 'Foco no que vem a seguir'; }
        elseif ($path === '/history') { $section = 'history'; $title = 'Meu histórico'; $caption = 'O caminho até aqui'; }
        elseif ($path === '/notifications') { $section = 'notifications'; $title = 'Notificações'; $caption = 'O que precisa da sua atenção'; }
        elseif ($path === '/settings') { $section = 'settings'; $title = 'Configurações'; $caption = 'Seu perfil e sua segurança'; }
        elseif ($path === '/invitations/accept') { $section = 'projects'; $title = 'Convite para projeto'; $caption = 'Trabalho que aproxima'; }
        elseif ($path === '/projects' || $path === '/projects/new' || preg_match('~^/projects/[0-9]+(?:/(?:edit|board|members|history))?$~D', $path)) {
            $section = 'projects'; $title = match (true) {
                $path === '/projects' => 'Projetos', $path === '/projects/new' => 'Novo projeto',
                str_ends_with($path, '/board') => 'Quadro Kanban', str_ends_with($path, '/members') => 'Participantes',
                str_ends_with($path, '/history') => 'Histórico do projeto', str_ends_with($path, '/edit') => 'Editar projeto',
                default => 'Seu projeto',
            };
            $caption = 'Ideias que ganham movimento';
        }
        return compact('section', 'title', 'caption', 'path');
    }
}
