<?php

declare(strict_types=1);

namespace App\Validators;

use DateTimeImmutable;
use Rakit\Validation\Validator;

final class ProjectValidator
{
    public static function errors(array $data): array
    {
        $validation = (new Validator([
            'required' => 'Informe :attribute.', 'max' => ':attribute excede :max caracteres.',
        ]))->make($data, ['name' => 'required|max:120', 'description' => 'max:5000']);
        $validation->setAliases(['name' => 'nome', 'description' => 'descrição']);
        $validation->validate();
        $errors = array_values($validation->errors()->firstOfAll());
        foreach (['starts_on' => 'data inicial', 'due_on' => 'previsão de término'] as $field => $label) {
            if ($data[$field] === '') { continue; }
            $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $data[$field]) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]) : false;
            if (!$date || $date->format('Y-m-d') !== $data[$field] || $data[$field] < '1000-01-01') {
                $errors[] = 'Informe uma ' . $label . ' válida.';
            }
        }
        if (!$errors && $data['starts_on'] !== '' && $data['due_on'] !== '' && $data['due_on'] < $data['starts_on']) {
            $errors[] = 'A previsão de término deve ser igual ou posterior à data inicial.';
        }
        return $errors;
    }
}
