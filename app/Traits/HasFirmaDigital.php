<?php

namespace App\Traits;

use App\Models\Firma;
use Illuminate\Support\Facades\Crypt;

trait HasFirmaDigital
{
    /**
     * Defaults de firma cuando el usuario NO tiene registro en BD.
     */
    protected function firmaDefaults(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [
                'nombre'  => 'Representante E-SUPPORT',
                'puesto'  => 'representante',
                'empresa' => 'E-SUPPORT QUERÉTARO',
                'image'   => null,
                'svg'     => null,
            ];
        }

        $empresa = 'E-SUPPORT QUERÉTARO';

        $rol = $user->puesto ?? $user->role ?? $user->rol ?? $user->tipo ?? null;
        $rol = is_string($rol) ? strtolower($rol) : null;

        $nombre = $user->name ?: 'Representante E-SUPPORT';

        if (!$rol) {
            $rol = 'representante';
        }

        return [
            'nombre'  => $nombre,
            'puesto'  => $rol,
            'empresa' => $empresa,
            'image'   => null,
            'svg'     => null,
        ];
    }

    /**
     * Lee la firma predeterminada DEL USUARIO ACTUAL.
     * Siempre 1 firma por user_id.
     */
    protected function readFirma(): array
    {
        $user = auth()->user();
        if (!$user) {
            return $this->firmaDefaults();
        }

        $firma = Firma::where('user_id', $user->id)->first();

        if (!$firma) {
            return $this->firmaDefaults();
        }

        $defaults = $this->firmaDefaults();

        return [
            // Usamos los accesores descifrados del modelo
            'nombre'  => $firma->nombre_dec  ?? $defaults['nombre'],
            'puesto'  => $firma->puesto_dec  ?? $defaults['puesto'],
            'empresa' => $firma->empresa_dec ?? $defaults['empresa'],
            'image'   => $firma->imagen_dec  ?? null,   // base64 listo para <img src="">
            'svg'     => $firma->svg_dec     ?? null,
        ];
    }

    /**
     * Guarda / actualiza la firma por USUARIO a partir del request.
     * Funciona tanto para gerente/admin como para técnico.
     */
    protected function saveFirmaDefaultFromRequest($request): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Firma actual (si ya existe) — para decidir si auto-guardar la primera vez
        $firmaActual = Firma::where('user_id', $user->id)->first();

        // Checkbox del componente (tanto en OS como en Acta)
        $saveDefaultFlag = (bool) $request->input('firma_guardar_default');

        // Si el usuario aún no tiene firma en BD, guardamos la PRIMERA de forma automática
        $saveDefault = $saveDefaultFlag || !$firmaActual;

        if (!$saveDefault) {
            return;
        }

        /* ===================== 1) OBTENER BASE64 DE LA FIRMA ===================== */

        // Nombres más usados en tus vistas/componentes:
        $base64 = $request->input('firma_base64')
            ?: $request->input('firma_empresa')          // en el acta usamos este name para la firma empresa
            ?: $request->input('firma_tecnico')
            ?: $request->input('firma_tecnico_base64');

        // Como último recurso: buscar cualquier campo que parezca data:image/...
        if (!$base64) {
            foreach ($request->all() as $value) {
                if (is_string($value) && str_starts_with(trim($value), 'data:image/')) {
                    $base64 = $value;
                    break;
                }
            }
        }

        if (!$base64) {
            // No hay firma que guardar
            return;
        }

        /* ===================== 2) NOMBRE / PUESTO / EMPRESA ===================== */

        $nombre = $request->input('firma_nombre')
            ?: $request->input('firma_emp_nombre')
            ?: $request->input('firma_tecnico_nombre')
            ?: $user->name; // respaldo

        $puesto = $request->input('firma_puesto')
            ?: $request->input('firma_emp_puesto')
            ?: $request->input('firma_tecnico_puesto')
            ?: ($user->puesto ?? 'representante');

        // OJO: aquí usamos solo los campos de texto para empresa, no el base64
        $empresa = $request->input('firma_emp_empresa')
            ?: $request->input('empresa')
            ?: 'E-SUPPORT QUERÉTARO';

        if (!$nombre) {
            return;
        }

        $svg = $request->input('firma_svg');

        /* ===================== 3) GUARDAR / ACTUALIZAR EN BD ===================== */

        Firma::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nombre'             => Crypt::encryptString($nombre),
                'puesto'             => Crypt::encryptString($puesto),
                'empresa'            => Crypt::encryptString($empresa),
                'firma_svg'          => $svg
                    ? Crypt::encryptString($svg)
                    : null,
                'firma_image_base64' => Crypt::encryptString($base64),
            ]
        );
    }
}
