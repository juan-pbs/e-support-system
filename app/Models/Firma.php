<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Firma extends Model
{
    protected $table = 'firmas';

    protected $fillable = [
        'user_id',
        'nombre',                // almacenado cifrado
        'puesto',                // almacenado cifrado
        'empresa',               // almacenado cifrado
        'firma_svg',             // almacenado cifrado (opcional)
        'firma_image_base64',    // almacenado cifrado (opcional)
    ];

    // Si en el futuro quieres usar accesores de solo lectura (descifrados), puedes usar estos:
    // OJO: tu controlador ya cifra/descifra; si usas estos, no cifres dos veces.
    public function getNombreDecAttribute()
    {
        try { return \Illuminate\Support\Facades\Crypt::decryptString($this->nombre); } catch (\Throwable $e) { return null; }
    }
    public function getPuestoDecAttribute()
    {
        try { return \Illuminate\Support\Facades\Crypt::decryptString($this->puesto); } catch (\Throwable $e) { return null; }
    }
    public function getEmpresaDecAttribute()
    {
        try { return \Illuminate\Support\Facades\Crypt::decryptString($this->empresa); } catch (\Throwable $e) { return null; }
    }
    public function getImagenDecAttribute()
    {
        try { return \Illuminate\Support\Facades\Crypt::decryptString($this->firma_image_base64); } catch (\Throwable $e) { return null; }
    }
    public function getSvgDecAttribute()
    {
        try { return \Illuminate\Support\Facades\Crypt::decryptString($this->firma_svg); } catch (\Throwable $e) { return null; }
    }
}
