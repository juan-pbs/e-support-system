@props([
    'firma' => null,
    'fieldBase64'      => 'firma_base64',
    'fieldNombre'      => 'firma_nombre',
    'fieldPuesto'      => 'firma_puesto',
    'fieldEmpresa'     => 'firma_empresa',
    'fieldSaveDefault' => 'firma_guardar_default',
])

@php
    $user = auth()->user();

    // Empresa fija
    $defaultEmpresa = 'E-SUPPORT QUERÉTARO';

    // Rol / puesto del usuario
    $rol = $user->puesto ?? $user->role ?? $user->rol ?? $user->tipo ?? null;
    $rol = is_string($rol) ? strtolower($rol) : '';

    // Nombre por defecto según usuario / rol
    $defaultNombre = $user->name ?? null;
    if (!$defaultNombre) {
        switch ($rol) {
            case 'admin':
                $defaultNombre = 'Administrador E-SUPPORT';
                break;
            case 'gerente':
                $defaultNombre = 'Gerente de Servicio';
                break;
            case 'tecnico':
            case 'técnico':
                $defaultNombre = 'Técnico de Servicio';
                break;
            default:
                $defaultNombre = 'Representante E-SUPPORT';
        }
    }

    // Puesto por defecto según rol
    $defaultPuesto = $rol ?: null;
    if (!$defaultPuesto) {
        switch ($rol) {
            case 'admin':
                $defaultPuesto = 'admin';
                break;
            case 'gerente':
                $defaultPuesto = 'gerente';
                break;
            case 'tecnico':
            case 'técnico':
                $defaultPuesto = 'técnico';
                break;
            default:
                $defaultPuesto = 'representante';
        }
    }

    // === Valores finales que verá el componente ===
    // Si existe una firma guardada en BD y la pasas como $firma,
    // se usan esos datos. Si no, se usan los defaults del usuario actual.
    $nombre  = data_get($firma, 'nombre',  $defaultNombre);
    $puesto  = data_get($firma, 'puesto',  $defaultPuesto);
    $empresa = data_get($firma, 'empresa', $defaultEmpresa);
    $image   = data_get($firma, 'image',   null);
@endphp

<div
    x-data="firmaWidget({
        base64Inicial: @js($image),
        nombreInicial: @js($nombre),
        puestoInicial: @js($puesto),
        empresaInicial: @js($empresa),
    })"
    x-init="init()"
>
    {{-- Tarjeta de estado --}}
    <div class="mt-2 border rounded-lg p-4 flex items-center justify-between bg-gray-50">
        <div class="text-sm text-gray-700">
            <template x-if="tieneFirma">
                <span>
                    ✅ Firma ya configurada para:
                    <span class="font-medium" x-text="nombre"></span>
                </span>
            </template>
            <template x-if="!tieneFirma">
                <span class="text-gray-600">
                    Aún no hay una firma configurada. Captura una firma para esta orden.
                </span>
            </template>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                    class="px-3 py-1.5 rounded-md border text-sm text-gray-700 hover:bg-gray-100"
                    @click="abrir()">
                <span x-text="tieneFirma ? 'Editar firma' : 'Configurar firma'"></span>
            </button>
        </div>
    </div>

    {{-- Campos ocultos que se van al formulario --}}
    <input type="hidden" name="{{ $fieldBase64 }}"      x-ref="inputBase64">
    <input type="hidden" name="{{ $fieldNombre }}"      x-ref="inputNombre">
    <input type="hidden" name="{{ $fieldPuesto }}"      x-ref="inputPuesto">
    <input type="hidden" name="{{ $fieldEmpresa }}"     x-ref="inputEmpresa">
    <input type="hidden" name="{{ $fieldSaveDefault }}" x-ref="inputSaveDefault" value="0">

    {{-- Modal de firma --}}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         x-show="show" x-cloak x-transition>
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            {{-- Header --}}
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Firma</h2>
                <button type="button" class="text-gray-400 hover:text-gray-600" @click="cerrar()">✕</button>
            </div>

            {{-- Contenido --}}
            <div class="px-6 py-4 space-y-4 overflow-y-auto">
                {{-- Nombre / Puesto / Empresa --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
                        <input type="text"
                               class="w-full border rounded-md px-2 py-1.5 text-sm"
                               x-model="nombre">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Puesto</label>
                        <input type="text"
                               class="w-full border rounded-md px-2 py-1.5 text-sm"
                               x-model="puesto">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Empresa</label>
                        <input type="text"
                               class="w-full border rounded-md px-2 py-1.5 text-sm"
                               x-model="empresa">
                    </div>
                </div>

                {{-- Canvas --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dibuja tu firma</label>
                    <div class="border rounded-lg bg-gray-50 h-40 relative overflow-hidden">
                        <canvas x-ref="canvas" class="w-full h-full"></canvas>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <button type="button"
                                class="px-3 py-1.5 rounded-md border text-xs text-gray-700 hover:bg-gray-50"
                                @click="limpiar()">
                            Limpiar
                        </button>
                        <p class="text-[11px] text-gray-500">
                            Usa mouse, dedo o lápiz para firmar
                        </p>
                    </div>
                </div>

                {{-- Guardar como predeterminada --}}
                <label class="inline-flex items-center gap-2 text-xs text-gray-700 mt-2">
                    <input type="checkbox" class="rounded border-gray-300" x-model="guardarComoDefault">
                    <span>Guardar como firma predeterminada</span>
                </label>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-3 border-t flex items-center justify-end gap-2 bg-gray-50">
                <button type="button"
                        class="px-4 py-2 rounded-md border text-sm text-gray-700 hover:bg-gray-100"
                        @click="cerrar()">
                    Cancelar
                </button>
                <button type="button"
                        class="px-4 py-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-sm text-white font-medium"
                        @click="guardar()">
                    Guardar firma
                </button>
            </div>
        </div>
    </div>
</div>

@once
    <script>
        function firmaWidget(initial) {
            return {
                show: false,
                nombre: initial.nombreInicial || '',
                puesto: initial.puestoInicial || '',
                empresa: initial.empresaInicial || '',
                base64: initial.base64Inicial || '',
                guardarComoDefault: false,
                tieneFirma: !!(initial.base64Inicial),

                _canvas: null,
                _ctx: null,
                _drawing: false,
                _lastX: 0,
                _lastY: 0,

                init() {
                    // Prefill hidden inputs
                    if (this.base64 && this.$refs.inputBase64) {
                        this.$refs.inputBase64.value = this.base64;
                    }
                    if (this.$refs.inputNombre)  this.$refs.inputNombre.value  = this.nombre;
                    if (this.$refs.inputPuesto)  this.$refs.inputPuesto.value  = this.puesto;
                    if (this.$refs.inputEmpresa) this.$refs.inputEmpresa.value = this.empresa;
                },

                abrir() {
                    this.show = true;
                    this.$nextTick(() => this.initCanvas());
                },

                cerrar() {
                    this.show = false;
                },

                initCanvas() {
                    const canvas = this.$refs.canvas;
                    if (!canvas) return;

                    const rect = canvas.getBoundingClientRect();
                    canvas.width  = rect.width  || 500;
                    canvas.height = rect.height || 160;

                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#111827';

                    this._canvas = canvas;
                    this._ctx    = ctx;
                    this._drawing = false;

                    const self = this;

                    const getPos = (e) => {
                        const r = canvas.getBoundingClientRect();
                        let x = e.clientX;
                        let y = e.clientY;
                        if (e.touches && e.touches[0]) {
                            x = e.touches[0].clientX;
                            y = e.touches[0].clientY;
                        }
                        return { x: x - r.left, y: y - r.top };
                    };

                    const start = (e) => {
                        e.preventDefault();
                        const p = getPos(e);
                        self._drawing = true;
                        self._lastX = p.x;
                        self._lastY = p.y;
                    };

                    const move = (e) => {
                        if (!self._drawing) return;
                        e.preventDefault();
                        const p = getPos(e);
                        self._ctx.beginPath();
                        self._ctx.moveTo(self._lastX, self._lastY);
                        self._ctx.lineTo(p.x, p.y);
                        self._ctx.stroke();
                        self._lastX = p.x;
                        self._lastY = p.y;
                    };

                    const end = () => { self._drawing = false; };

                    canvas.onmousedown = start;
                    canvas.onmousemove = move;
                    window.addEventListener('mouseup', end);

                    canvas.ontouchstart = start;
                    canvas.ontouchmove  = move;
                    canvas.ontouchend   = end;
                },

                limpiar() {
                    if (!this._canvas || !this._ctx) return;
                    this._ctx.fillStyle = '#ffffff';
                    this._ctx.fillRect(0, 0, this._canvas.width, this._canvas.height);
                },

                guardar() {
                    if (this._canvas) {
                        this.base64 = this._canvas.toDataURL('image/png');
                    }

                    if (this.$refs.inputBase64)      this.$refs.inputBase64.value      = this.base64 || '';
                    if (this.$refs.inputNombre)      this.$refs.inputNombre.value      = this.nombre || '';
                    if (this.$refs.inputPuesto)      this.$refs.inputPuesto.value      = this.puesto || '';
                    if (this.$refs.inputEmpresa)     this.$refs.inputEmpresa.value     = this.empresa || '';
                    if (this.$refs.inputSaveDefault) this.$refs.inputSaveDefault.value = this.guardarComoDefault ? '1' : '0';

                    this.tieneFirma = !!this.base64;
                    this.cerrar();
                }
            };
        }
    </script>
@endonce
