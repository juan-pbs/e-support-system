<div class="header">
    @if(!empty($barraBase64))
        <img src="{{ $barraBase64 }}" alt="" class="barra-superior">
    @else
        <table class="barra-fallback" role="presentation"><tr><td></td></tr></table>
    @endif

    <table class="tabla-header">
        <tr>
            <td class="td-logo">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo" alt="">
                @else
                    @include('pdf.partials.logo-fallback')
                @endif
            </td>
            <td class="td-info">
                <div class="info-empresa">
                    @include('pdf.partials.company-info')
                </div>
            </td>
        </tr>
    </table>
</div>
