<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\In;

final class RegisterRequest extends Data
{
    public function __construct(
        #[Required, Min(2), Max(255)]
        public string $name,
        
        #[Required, Email]
        public string $email,
        
        #[Required, Min(8)]
        public string $password,
        
        #[Required, In(['buyer', 'seller', 'admin'])]
        public string $role = 'buyer',
        
        public ?array $profile_data = null
    ) {}
}

