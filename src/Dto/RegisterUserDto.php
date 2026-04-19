<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterUserDto
{
    #[Assert\NotBlank(message: 'Поле Email sне должно быть пустым')]
    #[Assert\Email(message: 'Неправильный email address.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Пароль не может быть пустым')]
    #[Assert\Length(
        min: 6,
        minMessage: 'Пароль должен содержать не менее 6 символов'
    )]
    public string $password = '';
}