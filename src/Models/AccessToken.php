<?php

namespace Opscale\NovaAPI\Models;

use Enigma\ValidatorTrait;
use Laravel\Nova\Actions\Actionable;
use Laravel\Sanctum\PersonalAccessToken;
use Opscale\NovaAPI\Models\Repositories\AccessTokenRepository;

class AccessToken extends PersonalAccessToken
{
    use AccessTokenRepository;
    use Actionable;
    use ValidatorTrait;

    /**
     * @var array<string, array<int, string>>
     */
    public array $validationRules = [
        'name' => ['required'],
        'abilities' => ['nullable', 'array', 'min:1'],
    ];

    /**
     * @var string
     */
    protected $table = 'personal_access_tokens';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
    ];
}
