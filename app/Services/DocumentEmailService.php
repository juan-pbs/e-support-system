<?php

namespace App\Services;

use App\Models\Cliente;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentEmailService
{
    public function ensureEnabled(string $documentType = 'general'): void
    {
        if (! AppSettings::emailEnabledFor($documentType)) {
            throw ValidationException::withMessages([
                'email' => 'El envio de correos para este tipo de documento esta deshabilitado desde Sistema.',
            ]);
        }
    }

    public function sendPdfToClient(
        ?Cliente $cliente,
        string $subject,
        string $body,
        string $pdfBinary,
        string $filename,
        string $documentType = 'general'
    ): void {
        $this->ensureEnabled($documentType);

        $to = trim((string) ($cliente?->correo_electronico ?? ''));

        $validator = Validator::make(['email' => $to], [
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'El cliente no tiene correo electronico registrado.',
            'email.email' => 'El correo del cliente no es valido.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        Mail::raw($body, function ($message) use ($to, $subject, $pdfBinary, $filename) {
            $message
                ->to($to)
                ->subject($subject)
                ->attachData($pdfBinary, $filename, [
                    'mime' => 'application/pdf',
                ]);
        });
    }
}
