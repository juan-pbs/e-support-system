@php
    $reportFooterText = trim((string)($pdfTheme['footer_text'] ?? 'Documento generado automaticamente por el sistema.'));
@endphp

<div class="footer">
    {{ $reportFooterText }} | Generado el {{ now()->format('d/m/Y H:i') }}
</div>
