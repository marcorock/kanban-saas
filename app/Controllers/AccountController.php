<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\AccountTokenService;
use App\Services\Mailer;
use App\Services\RateLimiter;
use App\Validators\AuthValidator;
use Rakit\Validation\Validator;
use Twig\Environment;

final class AccountController
{
    public function __construct(private Environment $views, private array $config) {}

    public function page(string $mode, array $errors = [], string $message = '', ?string $token = null): string
    {
        header('Referrer-Policy: no-referrer');
        $token ??= is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        // Não refletir entradas arbitrárias; links inválidos exibem o formulário sem token.
        $token = preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : '';
        return $this->views->render('account.html.twig', [
            'projectName' => $this->config['name'], 'mode' => $mode, 'errors' => $errors,
            'message' => $message, 'token' => $token, 'csrf' => $_SESSION['csrf'],
        ]);
    }

    public function submit(string $mode): string
    {
        header('Referrer-Policy: no-referrer');
        if (!Session::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            return 'Sessão do formulário expirada. Recarregue a página.';
        }
        $pdo = Database::connect($this->config['database']);
        $limiter = new RateLimiter($pdo);
        $allowed = $limiter->allow('account-' . $mode . '-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20);
        if (!$allowed) {
            http_response_code(429);
            header('Retry-After: 900');
            return $this->page($mode, ['Muitas tentativas. Aguarde 15 minutos.']);
        }
        $tokens = new AccountTokenService($pdo, new Mailer($this->config['mail']), $this->config['url']);
        if (in_array($mode, ['forgot', 'resend'], true)) {
            $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
            if ((new Validator())->validate(['email' => $email], ['email' => 'required|email|max:254'])->fails()) {
                http_response_code(422);
                return $this->page($mode, ['Informe um e-mail válido.']);
            }
            // Mesma resposta para conta ausente, verificada e e-mail limitado.
            if ($limiter->allow('account-' . $mode . '-email', $email, 3)) {
                $tokens->request($email, $mode === 'forgot' ? 'reset' : 'verify');
            }
            return $this->page($mode, [], 'Se houver uma conta elegível, enviaremos um link para o e-mail informado.');
        }
        $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($mode === 'reset') {
            $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
            $errors = AuthValidator::passwordErrors($password, $confirmation);
            if ($errors) {
                http_response_code(422);
                return $this->page($mode, $errors, '', $token);
            }
        }
        if (!$tokens->consume($token, $mode === 'reset' ? 'reset' : 'verify', $mode === 'reset' ? $password : null)) {
            http_response_code(422);
            return $this->page($mode, ['Link inválido, expirado ou já utilizado. Solicite um novo link.'], '', $token);
        }
        if ($mode === 'reset') {
            Session::clear();
        }
        return $this->page($mode, [], $mode === 'reset' ? 'Senha alterada. Entre novamente com sua nova senha.' : 'E-mail confirmado. Você já pode acessar o painel.', '');
    }
}
