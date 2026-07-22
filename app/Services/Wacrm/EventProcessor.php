<?php

namespace App\Services\Wacrm;

use App\Models\Contact;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Pipeline;

/**
 * Convierte los eventos del wacrm en actividad del CRM de leads:
 *
 *   contact.created  → crea/actualiza el contacto espejo aquí.
 *   message.received → busca el contacto por teléfono; si no tiene
 *                      ningún lead ABIERTO, crea uno (source whatsapp,
 *                      primera etapa del pipeline por defecto); el
 *                      mensaje aterriza en el timeline del lead.
 *
 * Esta es la regla de oro de Kommo: cada conversación nueva de un
 * canal se vuelve un lead que el equipo tiene que trabajar.
 */
class EventProcessor
{
    public function process(Integration $integration, string $event, array $data): void
    {
        match ($event) {
            'contact.created' => $this->syncContact($integration, $data['contact'] ?? []),
            'message.received' => $this->handleInboundMessage($integration, $data),
            default => null, // eventos que no nos interesan se ignoran
        };
    }

    private function syncContact(Integration $integration, array $remote): ?Contact
    {
        $normalized = Contact::normalizePhone($remote['phone'] ?? null);

        if (! $normalized) {
            return null;
        }

        $contact = Contact::forAccount($integration->account_id)
            ->where('phone_normalized', $normalized)
            ->first();

        if ($contact) {
            // Completa datos que falten sin pisar lo editado aquí.
            $contact->update(array_filter([
                'name' => $contact->name === $contact->phone ? ($remote['name'] ?? null) : null,
                'email' => $contact->email ? null : ($remote['email'] ?? null),
                'wacrm_contact_id' => $remote['id'] ?? null,
            ]));

            return $contact;
        }

        return Contact::create([
            'account_id' => $integration->account_id,
            'name' => $remote['name'] ?: ($remote['phone'] ?? 'Sin nombre'),
            'phone' => $remote['phone'] ?? null,
            'email' => $remote['email'] ?? null,
            'wacrm_contact_id' => $remote['id'] ?? null,
        ]);
    }

    private function handleInboundMessage(Integration $integration, array $data): void
    {
        $contact = $this->syncContact($integration, $data['contact'] ?? []);

        if (! $contact) {
            return;
        }

        $lead = Lead::forAccount($integration->account_id)
            ->where('contact_id', $contact->id)
            ->where('status', Lead::STATUS_OPEN)
            ->latest()
            ->first();

        // Atribución de anuncios: el wacrm reenvía el `referral` de Meta
        // cuando el mensaje llegó desde un anuncio Click-to-WhatsApp.
        $referral = $data['message']['referral'] ?? null;

        // Sin lead abierto → conversación nueva = lead nuevo (regla Kommo).
        if (! $lead) {
            $pipeline = Pipeline::forAccount($integration->account_id)
                ->where('is_default', true)
                ->first()
                ?? Pipeline::forAccount($integration->account_id)->first();

            $firstStage = $pipeline?->stages()->where('stage_type', 'open')->orderBy('position')->first();

            if (! $pipeline || ! $firstStage) {
                return; // cuenta sin pipeline configurado
            }

            $lead = Lead::create([
                'account_id' => $integration->account_id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $firstStage->id,
                'contact_id' => $contact->id,
                'title' => 'WhatsApp: '.$contact->name,
                'source' => 'whatsapp',
                'source_ref' => $referral['source_id'] ?? null,
                'source_url' => $referral['source_url'] ?? null,
                'wacrm_conversation_id' => $data['conversation_id'] ?? null,
            ]);

            $lead->recordEvent('created', null, array_filter([
                'source' => 'whatsapp',
                'ad_id' => $referral['source_id'] ?? null,
            ]));

            // Aviso al owner: entró un lead nuevo por WhatsApp.
            \App\Models\AppNotification::notify(
                $integration->account_id,
                $integration->account->owner_user_id,
                'lead_created_whatsapp',
                'Nuevo lead de WhatsApp',
                "{$contact->name} escribió por WhatsApp",
                $lead->id,
            );
        }

        // Mantén el vínculo con la conversación si aún no lo tenía.
        if (! $lead->wacrm_conversation_id && ($data['conversation_id'] ?? null)) {
            $lead->update(['wacrm_conversation_id' => $data['conversation_id']]);
        }

        // La atribución original se preserva: solo se escribe si el
        // lead abierto aún no tiene anuncio de origen.
        if (! $lead->source_ref && ($referral['source_id'] ?? null)) {
            $lead->update([
                'source_ref' => $referral['source_id'],
                'source_url' => $referral['source_url'] ?? null,
            ]);
        }

        $lead->recordEvent('message_in', null, [
            'text' => mb_substr($data['message']['text'] ?? '', 0, 500),
            'type' => $data['message']['type'] ?? 'text',
            'wamid' => $data['message']['wamid'] ?? null,
        ]);
    }
}
