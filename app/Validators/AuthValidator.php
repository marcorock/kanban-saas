<?php

declare(strict_types=1);

namespace App\Validators;

use Rakit\Validation\Validator;

final class AuthValidator
{
    public static function errors(array $data, bool $register): array
    {
        $rules = ['email' => 'required|email|max:254', 'password' => 'required|min:12|max:72'];
        if ($register) {
            $rules['name'] = 'required|min:2|max:120';
            $rules['password_confirmation'] = 'required|same:password';
        } else {
            $rules['password'] = 'required|max:72';
        }
        return self::validate($data, $rules);
    }

    public static function passwordErrors(string $password, string $confirmation): array
    {
        return self::validate(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => 'required|min:12|max:72',
            'password_confirmation' => 'required|same:password',
        ]);
    }

    private static function validate(array $data, array $rules): array
    {
        $validator = new Validator([
            'required' => 'Preencha :attribute.', 'email' => 'Informe um e-mail válido.',
            'min' => ':attribute deve ter pelo menos :min caracteres.',
            'max' => ':attribute excede o limite de :max caracteres.',
            'same' => 'A confirmação da senha não confere.',
        ]);
        $validation = $validator->make($data, $rules);
        $validation->setAliases(['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'password_confirmation' => 'confirmação da senha']);
        $validation->validate();
        $errors = array_values($validation->errors()->firstOfAll());
        if (strlen($data['password']) > 72 || str_contains($data['password'], "\0")) {
            $errors[] = 'A senha deve conter até 72 bytes e não pode conter caracteres nulos.';
        }
        return $errors;
    }
}
