<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest validant les requêtes de simulation de panne ou de reconnexion
 * d'un microservice (`POST /api/microservices/{slug}/simulate`).
 */
class SimulateMicroserviceRequest extends FormRequest
{
    /**
     * Autorise l'opérateur du Wallboard à déclencher une simulation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation strictes conformes au contrat OpenAPI 3.0.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:UP,DOWN,DEGRADED'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Messages d'erreur personnalisés en français pour l'interface utilisateur.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Le statut cible est obligatoire (UP, DOWN ou DEGRADED).',
            'status.in' => 'Le statut doit être exclusivement UP, DOWN ou DEGRADED.',
            'reason.max' => 'Le motif de la simulation ne peut pas dépasser 255 caractères.',
        ];
    }
}
