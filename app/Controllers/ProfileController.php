<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\{AuthService, ProfileService, RateLimiter};
use App\Validators\AuthValidator;
use Twig\Environment;

final class ProfileController
{
    public function __construct(private Environment $views, private array $config) {}

    public function handle(bool $post): string
    {
        $pdo = Database::connect($this->config['database']);
        $auth = new AuthService($pdo);
        $user = $auth->currentUser();
        if (!$user) { header('Location: /login', true, 303); return ''; }
        $base = ['projectName' => $this->config['name'], 'user' => $user, 'csrf' => $_SESSION['csrf']];
        if ($user['email_verified_at'] === null) { http_response_code(403); return $this->views->render('verify-notice.html.twig', $base); }
        $errors = []; $name = $user['name'];
        $success = $_SESSION['profile_success'] ?? null;
        unset($_SESSION['profile_success']);
        if ($post) {
            $success = null;
            if (!Session::verifyCsrf($_POST['_token'] ?? null)) {
                http_response_code(419); $errors[] = 'Sessão do formulário expirada. Recarregue a página.';
            } else {
                $data = [];
                foreach (['action', 'name', 'current_password', 'password', 'password_confirmation'] as $key) {
                    if (isset($_POST[$key]) && !is_string($_POST[$key])) $errors[] = 'Formato de formulário inválido.';
                    $data[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
                }
                $action = $data['action'];
                if (!in_array($action, ['profile', 'password', 'sessions'], true)) $errors[] = 'Ação inválida.';
                if ($action === 'profile') {
                    $name = $data['name'] = trim($data['name']);
                    if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1f\x7f]/', $name)) $errors[] = 'Informe um nome de 2 a 120 caracteres, sem caracteres de controle.';
                } else {
                    if ($data['current_password'] === '' || strlen($data['current_password']) > 72 || str_contains($data['current_password'], "\0")) $errors[] = 'Informe a senha atual válida.';
                    if ($action === 'password') $errors = array_merge($errors, AuthValidator::passwordErrors($data['password'], $data['password_confirmation']));
                }
                if ($errors) http_response_code(422);
                else {
                    if (!(new RateLimiter($pdo))->allow('profile-' . $action, (string) $user['id'], $action === 'profile' ? 30 : 10)) {
                        http_response_code(429); header('Retry-After: 900'); $errors[] = 'Muitas tentativas. Aguarde 15 minutos.';
                    } else {
                        try {
                            $version = (new ProfileService($pdo))->update((int) $user['id'], (int) $user['session_version'], $action, $data);
                            if ($action !== 'profile') Session::login((int) $user['id'], $version);
                            $_SESSION['profile_success'] = match ($action) {
                                'profile' => 'Nome atualizado.', 'password' => 'Senha alterada. As demais sessões e os links de recuperação anteriores foram invalidados.',
                                'sessions' => 'As demais sessões foram encerradas. Esta sessão continua ativa.',
                            };
                            header('Location: /settings', true, 303); return '';
                        } catch (\DomainException $exception) {
                            if ($exception->getCode() === 401) { Session::clear(); header('Location: /login', true, 303); return ''; }
                            http_response_code(422); $errors[] = $exception->getMessage();
                        }
                    }
                }
            }
        }
        return $this->views->render('settings.html.twig', $base + ['errors' => array_unique($errors), 'name' => $name, 'success' => $success]);
    }
}
