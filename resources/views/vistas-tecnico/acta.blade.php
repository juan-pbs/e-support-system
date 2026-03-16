@extends('layouts.sidebar-navigation-tecnico')

@section('title', 'Acta de conformidad')

@section('content')
<div class="max-w-4xl mx-auto p-4 md:p-6">
  <div class="bg-gradient-to-r from-blue-50 via-sky-50 to-slate-50 border border-sky-100 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 mb-6">
    <div class="flex items-start sm:items-center gap-3">
      <x-boton-volver />
      <div>
        <h1 class="text-sm sm:text-base font-bold text-slate-800">Acta de conformidad</h1>
        <p class="text-xs sm:text-sm text-slate-500">Captura y firma del acta para cierre de servicio.</p>
      </div>
    </div>
  </div>
  @php
    use Illuminate\Support\Facades\Storage;

    $oid   = $orden->id_orden_servicio ?? $orden->getKey();
    $folio = $orden->folio ?? ('ORD-' . str_pad((string)$oid, 5, '0', STR_PAD_LEFT));
    $isFirmada = ($orden->acta_estado === 'firmada');
    $pdfUrlDef = route('tecnico.ordenes.acta.pdf', $oid);

    // Prefill desde borrador/firmada (JSON acta_data)
    $actaArr = is_array($orden->acta_data)
               ? $orden->acta_data
               : (json_decode($orden->acta_data ?? '[]', true) ?: []);

    // Helpers de data URI desde Storage privado
    $dataUriFromStorage = function (?string $path) {
        if (empty($path) || !Storage::exists($path)) return null;
        try {
            $mime = Storage::mimeType($path) ?: 'image/png';
            $bin  = Storage::get($path);
            return 'data:'.$mime.';base64,'.base64_encode($bin);
        } catch (\Throwable $e) { return null; }
    };

    $normalizeDataUri = function ($v) {
        if (empty($v) || !is_string($v)) return null;
        $v = trim($v);
        if (stripos($v, 'data:image/') === 0) {
            $parts = explode('base64,', $v, 2);
            if (count($parts) === 2) {
                $v = $parts[0] . 'base64,' . str_replace(' ', '+', $parts[1]);
            }
            return $v;
        }
        return 'data:image/png;base64,' . str_replace(' ', '+', $v);
    };

    // Prefill firma RESPONSABLE (cliente)
    $firmaRespPrefill = $normalizeDataUri($actaArr['firma_responsable'] ?? null)
                        ?: $normalizeDataUri($orden->firma_resp_data ?? null)
                        ?: $dataUriFromStorage($orden->firma_resp_path ?? ($actaArr['firma_responsable_path'] ?? null));

    // Firma predeterminada de la empresa (inyectada desde el controlador)
    $firmaDefault = $firmaDefaultEmpresa ?? [
        'nombre'  => 'Ing. José Alberto Rivera Rodríguez',
        'puesto'  => 'E-SUPPORT QUERÉTARO',
        'empresa' => 'E-SUPPORT QUERÉTARO',
        'image'   => null,
    ];

    // Prefill firma EMPRESA (representante)
    $firmaEmpPrefill  = $normalizeDataUri($actaArr['firma_empresa'] ?? null)
                        ?: $normalizeDataUri($orden->firma_emp_data ?? null)
                        ?: $dataUriFromStorage($orden->firma_emp_path ?? ($actaArr['firma_empresa_path'] ?? null))
                        ?: ($firmaDefault['image'] ?? null);

    // Arreglo para el componente de firma de EMPRESA
    $firmaEmpresa = [
        'nombre'  => $actaArr['firma_emp_nombre']  ?? ($firmaDefault['nombre']  ?? 'Ing. José Alberto Rivera Rodríguez'),
        'puesto'  => $actaArr['firma_emp_puesto']  ?? ($firmaDefault['puesto']  ?? 'E-SUPPORT QUERÉTARO'),
        'empresa' => $actaArr['firma_emp_empresa'] ?? ($firmaDefault['empresa'] ?? 'E-SUPPORT QUERÉTARO'),
        'image'   => $firmaEmpPrefill,
    ];
  @endphp

  {{-- Estado del acta --}}
  <div class="mb-4 space-y-2">
    @if($orden->acta_estado === 'firmada')
      <span class="inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full bg-green-100 text-green-800">
        Acta firmada (definitiva)
      </span>
      @if(!empty($orden->acta_pdf_hash))
        <div class="text-xs text-gray-500">
          Huella SHA-256 del PDF definitivo:
          <span class="font-mono break-all">{{ strtoupper($orden->acta_pdf_hash) }}</span>
        </div>
      @endif
      <div>
        {{-- Ahora abre el PDF en un modal, no en otra pestaña --}}
        <button type="button"
           onclick="openFinalPdfModal('{{ $pdfUrlDef }}')"
           class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white">
          Ver acta PDF
        </button>
      </div>
    @elseif($orden->acta_estado === 'borrador')
      <span class="inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full bg-amber-100 text-amber-800">
        Borrador guardado
      </span>
    @else
      <span class="inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full bg-gray-100 text-gray-700">
        Sin acta
      </span>
    @endif
  </div>

  {{-- Resumen OS --}}
  <div class="bg-white border rounded-xl p-5 mb-6">
    <div class="grid md:grid-cols-2 gap-3 text-sm">
      <p><span class="font-medium text-gray-600">Folio:</span> {{ $folio }}</p>
      <p><span class="font-medium text-gray-600">Cliente:</span> {{ $orden->cliente->nombre ?? '—' }}</p>
      <p><span class="font-medium text-gray-600">Servicio:</span> {{ $orden->servicio ?? '—' }}</p>
      <p><span class="font-medium text-gray-600">Fecha de OS:</span> {{ optional($orden->created_at)->format('d/m/Y H:i') }}</p>
    </div>
    @if(!$isFirmada && !empty($orden->acta_pdf_hash))
      <div class="text-xs text-gray-500 mt-2">
        Huella SHA-256 (última generación): <span class="font-mono break-all">{{ strtoupper($orden->acta_pdf_hash) }}</span>
      </div>
    @endif
  </div>

  {{-- Formulario / vista de acta --}}
  <form id="formActa" class="bg-white border rounded-xl p-5 space-y-5" enctype="multipart/form-data">
    @csrf

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del responsable que recibe</label>
        <input type="text" name="responsable" class="w-full rounded-lg border-gray-300"
               required
               {{ $isFirmada ? 'readonly disabled' : '' }}
               value="{{ old('responsable', $actaArr['responsable'] ?? '') }}">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Puesto</label>
        <input type="text" name="puesto" class="w-full rounded-lg border-gray-300"
               {{ $isFirmada ? 'readonly disabled' : '' }}
               value="{{ old('puesto', $actaArr['puesto'] ?? '') }}">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
        <input type="date" name="fecha" class="w-full rounded-lg border-gray-300" required
               {{ $isFirmada ? 'readonly disabled' : '' }}
               value="{{ old('fecha', $actaArr['fecha'] ?? now()->toDateString()) }}">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Hora</label>
        <input type="time" name="hora" step="60" class="w-full rounded-lg border-gray-300" required
               {{ $isFirmada ? 'readonly disabled' : '' }}
               value="{{ old('hora', $actaArr['hora'] ?? now()->format('H:i')) }}">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Trabajo realizado</label>
      <textarea name="trabajo_realizado" rows="4"
                class="w-full rounded-lg border-gray-300"
                {{ $isFirmada ? 'readonly disabled' : '' }}>{{ old('trabajo_realizado', $actaArr['trabajo_realizado'] ?? ($orden->descripcion_servicio ?? $orden->servicio)) }}</textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">¿El cliente está conforme?</label>
      @php $conforme = old('conforme', $actaArr['conforme'] ?? 'si'); @endphp
      <div class="flex items-center gap-6">
        <label class="inline-flex items-center gap-2">
          <input type="radio" name="conforme" value="si" class="rounded"
                 {{ $conforme === 'si' ? 'checked' : '' }}
                 {{ $isFirmada ? 'disabled' : '' }}>
          <span>Sí</span>
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="radio" name="conforme" value="no" class="rounded"
                 {{ $conforme === 'no' ? 'checked' : '' }}
                 {{ $isFirmada ? 'disabled' : '' }}>
          <span>No</span>
        </label>
      </div>
    </div>

    {{-- Firmas (dos) --}}
    <div class="grid md:grid-cols-2 gap-4">
      {{-- Firma del responsable / cliente --}}
      <div class="space-y-2">
        @if($isFirmada)
          <label class="block text-sm font-medium text-gray-700">
            Firma del responsable que recibe
          </label>
          <div class="border rounded-lg bg-gray-50 p-3">
            @if($firmaRespPrefill)
              <div class="flex justify-center">
                <img src="{{ $firmaRespPrefill }}"
                     alt="Firma del responsable"
                     class="max-h-32 object-contain">
              </div>
            @else
              <p class="text-xs text-gray-500 italic">
                No se encontró imagen de la firma del responsable.
              </p>
            @endif
            <p class="mt-2 text-xs text-gray-500">
              Acta firmada. La firma del responsable ya no puede modificarse.
            </p>
          </div>
        @else
          <x-firma-cliente
              label="Firma del responsable que recibe"
              fieldBase64="firma_responsable"
              :initialBase64="$firmaRespPrefill"
          />
          <p class="text-xs text-gray-500">
            Obligatoria para confirmar el acta.
          </p>
        @endif
      </div>

      {{-- Firma del representante de la empresa --}}
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">
          Firma del representante de la empresa
        </label>

        @if($isFirmada)
          <div class="border rounded-lg bg-gray-50 p-3 space-y-2">
            @if($firmaEmpPrefill)
              <div class="flex justify-center">
                <img src="{{ $firmaEmpPrefill }}"
                     alt="Firma de la empresa"
                     class="max-h-32 object-contain">
              </div>
            @else
              <p class="text-xs text-gray-500 italic">
                No se encontró imagen de la firma del representante.
              </p>
            @endif

            <div class="text-xs text-gray-700 mt-2">
              <div class="font-semibold">{{ $firmaEmpresa['nombre'] ?? '' }}</div>
              <div>{{ $firmaEmpresa['puesto'] ?? '' }}</div>
              <div>{{ $firmaEmpresa['empresa'] ?? '' }}</div>
            </div>

            <p class="mt-1 text-xs text-gray-500">
              Acta firmada. La firma de la empresa ya no puede modificarse.
            </p>
          </div>
        @else
          <x-firma-digital
              :firma="$firmaEmpresa"
              fieldBase64="firma_empresa"
              fieldNombre="firma_emp_nombre"
              fieldPuesto="firma_emp_puesto"
              fieldEmpresa="firma_emp_empresa"
              fieldSaveDefault="firma_emp_guardar_default"
          />
          <p class="text-xs text-gray-500">
            Opcional. Puedes marcar “Guardar como firma predeterminada” en el modal.
          </p>
        @endif
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
      <textarea name="observaciones" rows="3"
                class="w-full rounded-lg border-gray-300"
                {{ $isFirmada ? 'readonly disabled' : '' }}>{{ old('observaciones', $actaArr['observaciones'] ?? '') }}</textarea>
    </div>

    {{-- Controles inferiores --}}
    @if(!$isFirmada)
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="cerrar_os" value="1" class="rounded"
                 {{ old('cerrar_os', '1') ? 'checked' : '' }}>
          <span>Marcar orden como <strong>Completada</strong> si está conforme</span>
        </label>

        <div class="flex flex-wrap items-center gap-2 md:justify-end">
          <button type="button" id="btnDraft" class="h-10 rounded-lg px-4 border text-gray-800 hover:bg-gray-50">
            Guardar borrador
          </button>

          <button type="button" id="btnPreview" class="h-10 rounded-lg px-4 bg-gray-800 hover:bg-gray-900 text-white font-semibold">
            Previsualizar y confirmar
          </button>
        </div>
      </div>
    @else
      <div class="flex items-center justify-between text-sm text-gray-600">
        <div class="flex items-center gap-2">
          <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
          <span>Esta acta está firmada y bloqueada para edición.</span>
        </div>
        <div>
          <button type="button"
                  onclick="openFinalPdfModal('{{ $pdfUrlDef }}')"
                  class="inline-flex items-center gap-2 rounded-lg bg-slate-800 hover:bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">
            Ver PDF definitivo
          </button>
        </div>
      </div>
    @endif
  </form>
</div>

{{-- MODAL PREVIEW PDF (solo si no está firmada) --}}
@if(!$isFirmada)
  <div id="previewModal" class="hidden fixed inset-0 z-40 bg-black/50">
    <div class="absolute inset-0 flex items-center justify-center p-4">
      <div class="w-full max-w-5xl bg-white rounded-xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b">
          <h3 class="font-semibold text-gray-800">Previsualización — Acta de conformidad</h3>
          <div class="flex items-center gap-2">
            <button type="button" id="btnConfirm" class="px-3 py-1.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm">
              Confirmar y generar PDF definitivo
            </button>
            <button type="button" id="btnClose" class="px-3 py-1.5 rounded-md bg-gray-800 text-white text-sm">
              Cerrar
            </button>
          </div>
        </div>
        <div class="h-[75vh] relative">
          <div id="previewLoading" class="hidden absolute inset-0 grid place-content-center text-sm text-gray-600 bg-white/60">
            Generando previsualización…
          </div>
          <iframe id="previewFrame" class="w-full h-full" src=""></iframe>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- MODAL PARA VER PDF DEFINITIVO (mismo estilo que en reportes) --}}
<div id="finalPdfModal" class="fixed inset-0 z-40 hidden bg-black/50">
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-5xl bg-white rounded-xl shadow-xl overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <div>
          <h3 class="font-semibold text-gray-800 text-sm md:text-base">
            Acta de conformidad — PDF definitivo
          </h3>
          <p class="hidden md:block text-xs text-gray-500">
            Vista del documento final. Puedes descargarlo desde el botón de la derecha.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <a id="finalPdfDownload"
             href="#"
             target="_blank"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-600 text-blue-600 text-xs md:text-sm hover:bg-blue-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M4 20h16M12 4v9m0 0 3.5-3.5M12 13 8.5 9.5" />
            </svg>
            <span>Descargar PDF</span>
          </a>
          <button type="button"
                  id="btnCloseFinalPdf"
                  class="px-3 py-1.5 rounded-lg text-xs md:text-sm border hover:bg-gray-50">
            Cerrar
          </button>
        </div>
      </div>
      <div class="h-[75vh] bg-gray-100">
        <iframe id="finalPdfFrame"
                src=""
                class="w-full h-full border-0 rounded-b-xl"
                frameborder="0"></iframe>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
@if(!$isFirmada)
<script>
(function(){
  const OID        = {{ (int)$oid }};
  // Rutas pensadas para el módulo técnico
  const INDEX_URL  = @json(route('tecnico.detalles', ['orden' => $oid]));    // a dónde volver tras guardar borrador
  const SEGUIM_URL = @json(route('tecnico.servicios'));                      // a dónde ir tras confirmar
  const form       = document.getElementById('formActa');
  const btnDraft   = document.getElementById('btnDraft');
  const btnPrev    = document.getElementById('btnPreview');

  const csrf       = '{{ csrf_token() }}';

  // ---- Modal de PREVIEW PDF
  const modal    = document.getElementById('previewModal');
  const frame    = document.getElementById('previewFrame');
  const loading  = document.getElementById('previewLoading');
  const btnClose = document.getElementById('btnClose');
  const btnConf  = document.getElementById('btnConfirm');

  function setLoading(el, is){
    if(el){
      el.disabled = !!is;
      el.classList.toggle('opacity-60', !!is);
    }
  }

  function openModal(){ modal.classList.remove('hidden'); }
  function closeModal(){ modal.classList.add('hidden'); frame.src=''; }

  btnClose?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });

  // Helper para mostrar errores de validación (422)
  function show422(res, fallbackMsg){
    return res.json().then(j=>{
      const errs = j?.errors
        ? Object.entries(j.errors).map(([k,v])=>`${k}: ${v.join(', ')}`).join('\n')
        : '(sin detalle)';
      alert(`${j?.message || fallbackMsg}\n\n${errs}`);
    }).catch(()=>alert(fallbackMsg));
  }

  // Guardar borrador
  btnDraft?.addEventListener('click', async ()=>{
    try{
      setLoading(btnDraft,true);
      const res = await fetch(`{{ route('tecnico.ordenes.acta.borrador', $oid) }}`,{
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest'
        },
        body: new FormData(form)
      });
      if(res.status === 422){
        await show422(res, 'Error al guardar borrador');
        return;
      }
      const j = await res.json();
      if(!res.ok || !j.ok){
        throw new Error(j.message || 'Error al guardar borrador');
      }
      // En técnico regresamos al detalle de la orden
      window.location.href = INDEX_URL;
    }catch(err){
      alert(err.message);
    }finally{
      setLoading(btnDraft,false);
    }
  });

  // Previsualizar (PDF base64 en modal)
  btnPrev?.addEventListener('click', async ()=>{
    try{
      const fd = new FormData(form);
      const firmaResp = fd.get('firma_responsable');
      const firmaEmp  = fd.get('firma_empresa');

      if(!firmaResp && !firmaEmp){
        if(!confirm('No hay firmas capturadas. ¿Deseas continuar sin firmas? La confirmación final requerirá la firma del responsable.')){
          return;
        }
      }

      setLoading(btnPrev,true);
      loading?.classList.remove('hidden');

      const res = await fetch(`{{ route('tecnico.ordenes.acta.preview', $oid) }}`,{
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest'
        },
        body: fd
      });

      if(!res.ok){
        if(res.status === 422){
          await show422(res, 'No se pudo generar la previsualización');
        }else{
          try {
            const j = await res.json();
            alert(j.message || 'No se pudo generar la previsualización');
          } catch(e) {
            alert('No se pudo generar la previsualización');
          }
        }
        return;
      }

      const j = await res.json();
      if(!j.ok || !j.pdf_base64){
        throw new Error(j.message || 'No se pudo generar la previsualización');
      }
      frame.src = 'data:application/pdf;base64,' + j.pdf_base64;
      openModal();


    }catch(err){
      alert(err.message);
    }finally{
      setLoading(btnPrev,false);
      loading?.classList.add('hidden');
    }
  });
  btnConf?.addEventListener('click', async ()=>{
    try{
      const fd = new FormData(form);
      const firmaResp = fd.get('firma_responsable');

      if(!firmaResp){
        alert('Para confirmar necesitas capturar la firma del responsable que recibe.');
        return;
      }

      setLoading(btnConf,true);

      const res = await fetch(`{{ route('tecnico.ordenes.acta.confirmar', $oid) }}`,{
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest'
        },
        body: fd
      });

      if(!res.ok){
        if(res.status === 422){
          await show422(res, 'No se pudo confirmar el acta');
        }else{
          try{
            const j = await res.json();
            alert(j.message || 'No se pudo confirmar el acta');
          }catch(e){
            alert('No se pudo confirmar el acta');
          }
        }
        return;
      }

      const j = await res.json();
      if(!j.ok){
        throw new Error(j.message || 'No se pudo confirmar el acta');
      }

      closeModal();

      if (j.pdf_url) {
        window.open(j.pdf_url, '_blank');
      }
      window.location.href = SEGUIM_URL;

    }catch(err){
      alert(err.message);
    }finally{
      setLoading(btnConf,false);
    }
  });

})();
</script>
@endif

{{-- Script para modal de PDF definitivo (siempre disponible) --}}
<script>
(function () {
  const modal   = document.getElementById('finalPdfModal');
  const frame   = document.getElementById('finalPdfFrame');
  const btnClose = document.getElementById('btnCloseFinalPdf');
  const downloadLink = document.getElementById('finalPdfDownload');

  window.openFinalPdfModal = function (url) {
    if (!modal || !frame) return;
    frame.src = url;
    if (downloadLink) {
      downloadLink.href = url;
    }
    modal.classList.remove('hidden');
  };

  function closeFinalPdf() {
    if (!modal || !frame) return;
    modal.classList.add('hidden');
    frame.src = '';
  }

  btnClose?.addEventListener('click', closeFinalPdf);

  modal?.addEventListener('click', function (e) {
    if (e.target === modal) {
      closeFinalPdf();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeFinalPdf();
    }
  });
})();
</script>
@endpush
