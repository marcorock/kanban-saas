<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\AuthService;
use App\Services\BoardService;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Throwable;
use Twig\Environment;

final class BoardController
{
    public function __construct(private Environment $views, private array $config) {}

    private function json(array $body, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function handle(string $id, bool $post, ?string $taskId = null): string
    {
        $api = $post || $taskId !== null;
        try {
            if (!isset($_SESSION['user_id'])) { throw new HttpException('Entre novamente para acessar o quadro.', 401); }
            $pdo = Database::connect($this->config['database']);
            $user = (new AuthService($pdo))->currentUser();
            if (!$user) { throw new HttpException('Entre novamente para acessar o quadro.', 401); }
            if ($user['email_verified_at'] === null) {
                if ($api) { throw new HttpException('Confirme seu e-mail antes de alterar o quadro.', 403); }
                http_response_code(403);
                return $this->views->render('verify-notice.html.twig', ['projectName' => $this->config['name'], 'csrf' => $_SESSION['csrf']]);
            }
            if (!ctype_digit($id) || strlen($id) > 18 || (int) $id < 1) { throw new HttpException('Quadro não encontrado.', 404); }
            $service = new BoardService($pdo);
            if (!$post && $taskId !== null) {
                if (!ctype_digit($taskId) || strlen($taskId) > 18 || (int) $taskId < 1) { throw new HttpException('Tarefa não encontrada.', 404); }
                $page = static fn (string $key): int => filter_var($_GET[$key] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
                return $this->json(['ok' => true] + $service->task((int) $user['id'], (int) $id, (int) $taskId, $page('comments_page'), $page('activity_page')));
            }
            if (!$post) {
                $state = $service->state((int) $user['id'], (int) $id);
                return $this->views->render('board.html.twig', [
                    'projectName' => $this->config['name'], 'user' => $user, 'csrf' => $_SESSION['csrf'], 'wide' => true,
                    'board' => $state, 'boardJson' => json_encode($state, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                ]);
            }
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') { throw new HttpException('Formato de solicitação inválido.', 415); }
            $raw = file_get_contents('php://input', false, null, 0, 65537);
            if (strlen($raw) > 65536) { throw new HttpException('Solicitação muito grande.', 413); }
            try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { throw new HttpException('JSON inválido.', 422); }
            if (!is_array($data) || !Session::verifyCsrf($data['_token'] ?? null)) { throw new HttpException('Sessão do formulário expirada. Atualize a página.', 419); }
            if (!is_int($data['version'] ?? null) || $data['version'] < 0 || !is_string($data['action'] ?? null)) { throw new HttpException('Solicitação inválida.', 422); }
            $state = $service->mutate((int) $user['id'], (int) $id, $data['version'], $data['action'], $data);
            return $this->json(['ok' => true, 'board' => $state]);
        } catch (HttpException $exception) {
            if (!$api) {
                if ($exception->getCode() === 401) { header('Location: /login', true, 303); return ''; }
                throw $exception;
            }
            return $this->json(['ok' => false, 'message' => $exception->getMessage()], (int) $exception->getCode());
        } catch (Throwable $exception) {
            if (!$api) { throw $exception; }
            error_log(sprintf('[kanbansaas] %s em %s:%d', $exception::class, basename($exception->getFile()), $exception->getLine()));
            return $this->json(['ok' => false, 'message' => $post ? 'Não foi possível salvar. Atualize o quadro para conferir o estado atual antes de tentar novamente.' : 'Não foi possível carregar os detalhes da tarefa.'], 500);
        }
    }
}
