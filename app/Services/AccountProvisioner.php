<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;

/**
 * Crea una cuenta lista para trabajar: owner + pipeline por defecto
 * con etapas terminales (el modelo Kommo). Lo usan el registro, la
 * expulsión de miembros (recuperan cuenta propia) y los tests.
 */
class AccountProvisioner
{
    public const DEFAULT_STAGES = [
        ['name' => 'Nuevo', 'color' => '#3b82f6', 'stage_type' => 'open'],
        ['name' => 'Contactado', 'color' => '#8b5cf6', 'stage_type' => 'open'],
        ['name' => 'Negociación', 'color' => '#f59e0b', 'stage_type' => 'open'],
        ['name' => 'Ganado', 'color' => '#10b981', 'stage_type' => 'won'],
        ['name' => 'Perdido', 'color' => '#ef4444', 'stage_type' => 'lost'],
    ];

    public function createForUser(User $user, ?string $name = null): Account
    {
        $account = Account::create([
            'name' => $name ?? $user->name,
            'owner_user_id' => $user->id,
        ]);

        $user->update(['account_id' => $account->id, 'account_role' => User::ROLE_OWNER]);

        $pipeline = Pipeline::create([
            'account_id' => $account->id,
            'name' => 'Ventas',
            'is_default' => true,
        ]);

        foreach (self::DEFAULT_STAGES as $position => $stage) {
            PipelineStage::create([
                'pipeline_id' => $pipeline->id,
                'position' => $position,
                ...$stage,
            ]);
        }

        return $account;
    }
}
