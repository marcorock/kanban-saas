<?php

declare(strict_types=1);

namespace App\Validators;

use App\Services\ActivityPresenter;
use DateTimeImmutable;
use Pecee\SimpleRouter\Exceptions\HttpException;

final class ReportFilters
{
    private static function text(array $input, string $key, string $default = ''): string
    {
        $value = $input[$key] ?? $default;
        if (!is_string($value) && !is_int($value)) { throw new HttpException('Formato de filtro inválido.', 422); }
        return trim((string) $value);
    }

    public static function parse(array $input, bool $history = false): array
    {
        $project = self::text($input, 'project_id');
        if ($project !== '' && (!ctype_digit($project) || strlen($project) > 18 || (int) $project < 1)) { throw new HttpException('Projeto inválido.', 422); }
        $page = self::text($input, 'page', '1');
        if (!ctype_digit($page) || strlen($page) > 7 || (int) $page < 1) { throw new HttpException('Página inválida.', 422); }
        $result = ['project_id' => $project === '' ? null : (int) $project, 'page' => min((int) $page, 1000000)];
        foreach (['from', 'to'] as $key) {
            $value = self::text($input, $key);
            $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01')) { throw new HttpException('Informe datas válidas nos filtros.', 422); }
            $result[$key] = $value;
        }
        if ($result['from'] && $result['to'] && $result['from'] > $result['to']) { throw new HttpException('A data final deve ser igual ou posterior à inicial.', 422); }
        $enums = $history ? ['action' => ['' => ''] + ActivityPresenter::LABELS] : [
            'priority' => ['' => '', 'low' => '', 'normal' => '', 'high' => '', 'urgent' => ''],
            'status' => ['pending' => '', 'completed' => '', 'in_progress' => '', 'all' => ''],
            'due' => ['' => '', 'overdue' => '', 'upcoming' => '', 'none' => ''],
            'archived' => ['active' => '', 'archived' => '', 'all' => ''],
        ];
        foreach ($enums as $key => $options) {
            $value = self::text($input, $key, $key === 'status' ? 'pending' : ($key === 'archived' ? 'active' : ''));
            if (!array_key_exists($value, $options)) { throw new HttpException('Opção de filtro inválida.', 422); }
            $result[$key] = $value;
        }
        return $result;
    }
}
