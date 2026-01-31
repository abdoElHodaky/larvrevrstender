<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Min;

final class LoginRequest extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        
        #[Required, Min(6)]
        public string $password,
        
        public bool $remember = false
    ) {}
}

