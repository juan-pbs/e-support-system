@php
    $companyName = $pdfTheme['company_name'] ?? 'E-SUPPORT QUERÉTARO';
    $companyLines = $pdfTheme['_company_lines'] ?? [
        'Jose Alberto Rivera Rodríguez',
        'RFC: RIRA781030RI8',
        'Av. Emeterio González No. 27 int. 2',
        'Hércules, Querétaro, Qro. C.P. 76069',
        'Cel: 442-169-7094',
    ];
@endphp

<strong>{{ $companyName }}</strong>
@foreach($companyLines as $line)
    <span>{{ $line }}</span>
@endforeach
