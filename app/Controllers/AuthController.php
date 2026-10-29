<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\AuthService;
use App\Services\RateLimiter;
use App\Validators\AuthValidator;
use Twig\Environment;

final class AuthController
{
    public function __construct(private Environment $views, private array $config) {}

    private function redirect(string $path): string
    {
        header('Location: ' . $path, true, 303);
        return '';
    }

    public function form(bool $register, array $errors = [], array $old = []): string
    {
        if (isset($_SESSION['user_id'])) {
            return $this->redirect('/dashboard');
        }
        return $this->renderForm($register, $errors, $old);
    }

    private function renderForm(bool $register, array $errors, array $old = []): string
    {
        return $this->views->render('auth.html.twig', [
            'projectName' => $this->config['name'], 'register' => $register,
            'errors' => $errors, 'old' => array_intersect_key($old, array_flip(['name', 'email'])), 'csrf' => $_SESSION['csrf'],
        ]);
    }

    public function submit(bool $register): string
    {
        if (!Session::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            return $this->renderForm($register, ['Sua sessão expirou. Preencha o formulário novamente para continuar.']);
        }
        if (isset($_SESSION['user_id'])) {
            return $this->redirect('/dashboard');
        }
        $data = [];
        foreach (['name', 'email', 'password', 'password_confirmation'] as $key) {
            if (isset($_POST[$key]) && !is_string($_POST[$key])) {
                http_response_code(422);
                return $this->form($register, ['Formato de formulário inválido.']);
            }
            $data[$key] = $_POST[$key] ?? '';
        }
        $data['name'] = trim($data['name']);
        $data['email'] = strtolower(trim($data['email']));
        $pdo = Database::connect($this->config['database']);
        $limiter = new RateLimiter($pdo);
        $scope = $register ? 'register' : 'login';
        $ipAllowed = $limiter->allow($scope . '-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $register ? 10 : 30);
        $emailAllowed = $limiter->allow($scope . '-email', $data['email'], 10);
        if (!$ipAllowed || !$emailAllowed) {
            http_response_code(429);
            header('Retry-After: 900');
            return $this->form($register, ['Muitas tentativas. Aguarde 15 minutos.']);
        }
        $errors = AuthValidator::errors($data, $register);
        if ($errors) {
            http_response_code(422);
            return $this->form($register, $errors, $data);
        }
        $auth = new AuthService($pdo);
        $authenticated = $register ? null : $auth->authenticate($data['email'], $data['password']);
        $id = $register ? $auth->register($data['name'], $data['email'], $data['password']) : ($authenticated['id'] ?? null);
        if ($id === null) {
            http_response_code($register ? 422 : 401);
            return $this->form($register, [$register ? 'Não foi possível criar a conta com os dados informados.' : 'E-mail ou senha inválidos.'], $data);
        }
        Session::login($id, $authenticated['session_version'] ?? 1);
        if ($register) {
            (new \App\Services\AccountTokenService($pdo, new \App\Services\Mailer($this->config['mail']), $this->config['url']))->request($data['email'], 'verify');
        }
        return $this->redirect(isset($_SESSION['pending_invitation']) ? '/invitations/accept' : '/dashboard');
    }

    public function dashboard(): string
    {
        return (new ReportingController($this->views, $this->config))->handle('dashboard');
    }

    public function logout(): string
    {
        if (!Session::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            return 'Sessão do formulário expirada. Recarregue a página.';
        }
        Session::clear();
        return $this->redirect('/login');
    }
}
