<?php

namespace App\Services\Invoice;

use App\Models\Integration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP hacia Komo Invoice. Lo usa LeadController@quote para
 * crear una cotización pre-llenada desde la ficha del lead ganado —
 * el "botón Cotizar" de la Fase 4 F4-Invoice.
 */
class Client
{
    public function __construct(private readonly Integration $integration) {}

    public static function for(Integration $integration): self
    {
        return new self($integration);
    }

    /** POST /api/v1/quotes en Invoice — devuelve public_url + edit_url. */
    public function createQuote(array $payload): array
    {
        return $this->unwrap(
            $this->request()->post($this->url('quotes'), $payload)
        );
    }

    private function request()
    {
        return Http::withToken($this->integration->invoice_api_key)
            ->acceptJson()
            ->timeout(10);
    }

    private function url(string $path): string
    {
        return $this->integration->invoiceBaseUrl().'/api/v1/'.ltrim($path, '/');
    }

    private function unwrap(Response $response): array
    {
        if ($response->failed()) {
            $error = $response->json('message') ?? $response->body();
            throw new RuntimeException("Invoice API error ({$response->status()}): {$error}");
        }

        return $response->json() ?? [];
    }
}
