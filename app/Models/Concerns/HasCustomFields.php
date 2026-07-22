<?php

namespace App\Models\Concerns;

use App\Models\CustomField;
use Illuminate\Support\Facades\DB;

/**
 * Valores de campos personalizados para leads, contactos y empresas.
 * El pivot polimórfico no tiene modelo propio (PK compuesta) — se
 * lee y escribe con query builder.
 */
trait HasCustomFields
{
    /** Mapa custom_field_id => value para esta entidad. */
    public function customFieldValues(): array
    {
        return DB::table('custom_field_values')
            ->where('fieldable_type', $this->getMorphClass())
            ->where('fieldable_id', $this->getKey())
            ->pluck('value', 'custom_field_id')
            ->all();
    }

    /**
     * Guarda un mapa custom_field_id => value. Solo acepta campos de
     * la misma cuenta y la entidad correcta; vacío borra el valor.
     */
    public function syncCustomFieldValues(array $values, string $entity): void
    {
        $validIds = CustomField::forAccount($this->account_id)
            ->where('entity', $entity)
            ->pluck('id')
            ->flip();

        foreach ($values as $fieldId => $value) {
            if (! isset($validIds[$fieldId])) {
                continue;
            }

            if ($value === null || $value === '') {
                DB::table('custom_field_values')
                    ->where('custom_field_id', $fieldId)
                    ->where('fieldable_type', $this->getMorphClass())
                    ->where('fieldable_id', $this->getKey())
                    ->delete();

                continue;
            }

            DB::table('custom_field_values')->updateOrInsert(
                [
                    'custom_field_id' => $fieldId,
                    'fieldable_type' => $this->getMorphClass(),
                    'fieldable_id' => $this->getKey(),
                ],
                ['value' => mb_substr((string) $value, 0, 1000), 'updated_at' => now()],
            );
        }
    }
}
