@props([
    'label'        => 'Firma de conformidad del cliente / responsable que recibe',
    'fieldBase64'  => 'firma_responsable',
    'initialBase64'=> null,
])

@php
    $initial = is_string($initialBase64) && trim($initialBase64) !== ''
        ? $initialBase64
        : null;
@endphp

<div
    x-data="firmaClienteWidget({
        campoBase64: @js($fieldBase64),
        base64Inicial: @js($initial),
    })"
    x-init="init()"
>
    <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">
            {{ $label }}
        </label>

        <div class="border rounded-lg p-3 flex items-center gap-3">
            <img
                x-ref="preview"
                x-show="hasFirma"
                :src="previewSrc"
                alt="Firma del cliente"
                class="h-16 rounded bg-gray-50 object-contain"
            >
            <div class="flex-1">
                <button type="button"
                        class="rounded-lg px-3 py-1.5 border text-gray-800 hover:bg-gray-50"
                        @click="abrir()">
                    <span x-text="hasFirma ? 'Editar firma' : 'Firmar'"></span>
                </button>
                <button type="button"
                        class="rounded-lg px-3 py-1.5 border text-gray-800 hover:bg-gray-50 ml-2"
                        @click="quitar()">
                    Quitar
                </button>
                <p class="text-xs text-gray-500 mt-1">
                    Esta firma se usará en el PDF del acta de conformidad.
                </p>
            </div>
        </div>
    </div>

    {{-- Campo oculto que viaja en el formulario --}}
    <input type="hidden" name="{{ $fieldBase64 }}" x-ref="inputBase64">

    {{-- Modal de firma --}}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         x-show="show" x-cloak x-transition>
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            {{-- Header --}}
            <div class="px-6 py-4 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">
                    {{ $label }}
                </h2>
                <button type="button"
                        class="text-gray-400 hover:text-gray-600"
                        @click="cerrar()">
                    ✕
                </button>
            </div>

            {{-- Contenido --}}
            <div class="px-6 py-4 space-y-4 overflow-y-auto">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Dibuja la firma
                    </label>
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
                            Usa mouse, dedo o lápiz para firmar.
                        </p>
                    </div>
                </div>
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
        function firmaClienteWidget(initial) {
            return {
                show: false,
                campoBase64: initial.campoBase64 || 'firma_responsable',
                base64: initial.base64Inicial || '',
                previewSrc: initial.base64Inicial || '',
                hasFirma: !!(initial.base64Inicial),

                _canvas: null,
                _ctx: null,
                _drawing: false,
                _lastX: 0,
                _lastY: 0,

                init() {
                    // Prefill hidden input si ya hay firma
                    if (this.base64 && this.$refs.inputBase64) {
                        this.$refs.inputBase64.value = this.base64;
                        this.previewSrc = this.base64;
                        this.hasFirma = true;
                    }
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

                    // Si ya teníamos una firma previa, dibujarla en el canvas
                    if (this.base64) {
                        const img = new Image();
                        img.onload = () => {
                            const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
                            const w = img.width * scale;
                            const h = img.height * scale;
                            const x = (canvas.width - w) / 2;
                            const y = (canvas.height - h) / 2;
                            ctx.drawImage(img, x, y, w, h);
                        };
                        img.src = this.base64.startsWith('data:')
                            ? this.base64
                            : ('data:image/png;base64,' + this.base64);
                    }

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
                        this.previewSrc = this.base64;
                        this.hasFirma = !!this.base64;
                    }

                    if (this.$refs.inputBase64) {
                        this.$refs.inputBase64.value = this.base64 || '';
                    }

                    this.cerrar();
                },

                quitar() {
                    this.base64 = '';
                    this.previewSrc = '';
                    this.hasFirma = false;
                    if (this.$refs.inputBase64) {
                        this.$refs.inputBase64.value = '';
                    }
                }
            };
        }
    </script>
@endonce
