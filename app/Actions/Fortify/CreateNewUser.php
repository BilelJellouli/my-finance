<?php

namespace App\Actions\Fortify;

use App\Actions\CreateAccount;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Currency;
use App\Enums\EntityColor;
use App\Enums\EntityType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateAccount $createAccount) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $entity = $user->entities()->create([
                'name' => 'Personal',
                'type' => EntityType::PERSONAL,
                'color' => EntityColor::GREEN,
            ]);

            $this->createAccount->execute($entity, 'Main', Currency::TND, isMain: true);

            return $user;
        });
    }
}
