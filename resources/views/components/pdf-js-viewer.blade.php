@once
    @push('scripts')
        <script>
            window.eSupportPdfViewer = window.eSupportPdfViewer || (() => {
                const cdn = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                const worker = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                let pdfJsPromise = null;

                function loadPdfJs() {
                    if (window.pdfjsLib) {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = worker;
                        return Promise.resolve(window.pdfjsLib);
                    }

                    if (!pdfJsPromise) {
                        pdfJsPromise = new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = cdn;
                            script.onload = () => {
                                window.pdfjsLib.GlobalWorkerOptions.workerSrc = worker;
                                resolve(window.pdfjsLib);
                            };
                            script.onerror = reject;
                            document.head.appendChild(script);
                        });
                    }

                    return pdfJsPromise;
                }

                function getContainer(target) {
                    return typeof target === 'string' ? document.getElementById(target) : target;
                }

                function showMessage(container, message) {
                    container.innerHTML = `
                        <div class="min-h-full grid place-items-center p-6 text-sm text-gray-600">
                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">${message}</div>
                        </div>`;
                }

                async function renderDocument(pdf, container) {
                    container.innerHTML = '';
                    const width = Math.max((container.clientWidth || window.innerWidth) - 32, 280);

                    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                        const page = await pdf.getPage(pageNumber);
                        const baseViewport = page.getViewport({ scale: 1 });
                        const scale = Math.min(2.4, width / baseViewport.width);
                        const viewport = page.getViewport({ scale });
                        const outputScale = Math.max(2, Math.min(window.devicePixelRatio || 1, 3));
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');

                        canvas.width = Math.floor(viewport.width * outputScale);
                        canvas.height = Math.floor(viewport.height * outputScale);
                        canvas.style.width = `${Math.floor(viewport.width)}px`;
                        canvas.style.height = `${Math.floor(viewport.height)}px`;
                        canvas.className = 'mx-auto mb-4 block max-w-full rounded bg-white shadow';
                        container.appendChild(canvas);

                        await page.render({
                            canvasContext: context,
                            viewport,
                            transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
                        }).promise;
                    }
                }

                async function renderUrl(url, target) {
                    const container = getContainer(target);
                    if (!container || !url) return false;
                    showMessage(container, 'Cargando PDF...');

                    try {
                        const pdfjsLib = await loadPdfJs();
                        const pdf = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                        await renderDocument(pdf, container);
                        return true;
                    } catch (error) {
                        console.error('No se pudo renderizar el PDF', error);
                        showMessage(container, 'No se pudo mostrar el PDF en esta vista.');
                        return false;
                    }
                }

                async function renderBase64(base64, target) {
                    const container = getContainer(target);
                    if (!container || !base64) return false;
                    showMessage(container, 'Cargando PDF...');

                    try {
                        const binary = atob(base64);
                        const bytes = new Uint8Array(binary.length);
                        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

                        const pdfjsLib = await loadPdfJs();
                        const pdf = await pdfjsLib.getDocument({ data: bytes }).promise;
                        await renderDocument(pdf, container);
                        return true;
                    } catch (error) {
                        console.error('No se pudo renderizar el PDF', error);
                        showMessage(container, 'No se pudo mostrar el PDF en esta vista.');
                        return false;
                    }
                }

                function clear(target) {
                    const container = getContainer(target);
                    if (container) container.innerHTML = '';
                }

                return { renderUrl, renderBase64, clear };
            })();
        </script>
    @endpush
@endonce
