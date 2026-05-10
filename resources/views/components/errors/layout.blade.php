@props([
    'code' => 'Error',
    'title' => 'Algo salio mal',
    'message' => 'No pudimos completar la solicitud.',
    'detail' => null,
    'variant' => 'blue',
    'showBack' => true,
    'showHome' => true,
    'homeLabel' => null,
])

@php
    $primary = '#007BD3';
    $secondary = '#034373';
    $soft = '#EAF5FF';
    $accent = match ($variant) {
        'amber' => '#F59E0B',
        'red' => '#EF4444',
        'green' => '#10B981',
        default => '#007BD3',
    };
    $homeUrl = auth()->check()
        ? (strtolower((string) auth()->user()->puesto) === 'tecnico' ? route('tecnico.inicio') : route('gerente.inicio'))
            : route('login');
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $code }} - {{ $title }}</title>
    <link rel="icon" href="{{ asset('images/ico.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('images/ico.png') }}" type="image/png">
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #0f172a; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 10% 15%, {{ $soft }} 0, transparent 28%),
                linear-gradient(135deg, #f8fafc 0%, #ffffff 48%, #edf7ff 100%);
        }
        .shell {
            width: min(100%, 920px);
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            overflow: hidden;
            background: rgba(255,255,255,.94);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 22px 60px rgba(15, 23, 42, .14);
        }
        .brand {
            padding: 36px;
            color: white;
            background: linear-gradient(135deg, {{ $primary }} 0%, {{ $secondary }} 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 420px;
        }
        .logo-card {
            width: 180px;
            max-width: 100%;
            padding: 14px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 14px 30px rgba(0,0,0,.16);
        }
        .logo-card img { width: 100%; height: auto; display: block; }
        .code {
            margin: 48px 0 0;
            font-size: clamp(64px, 12vw, 120px);
            line-height: .9;
            font-weight: 850;
            letter-spacing: -0.04em;
        }
        .brand p { margin: 12px 0 0; color: rgba(255,255,255,.82); font-size: 14px; }
        .content { padding: 44px; display: flex; flex-direction: column; justify-content: center; }
        .eyebrow {
            width: fit-content;
            margin: 0 0 18px;
            padding: 7px 11px;
            border-radius: 999px;
            background: {{ $soft }};
            color: {{ $secondary }};
            font-size: 12px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        h1 { margin: 0; font-size: clamp(28px, 4vw, 44px); line-height: 1.05; letter-spacing: -0.03em; }
        .message { margin: 16px 0 0; color: #475569; font-size: 16px; line-height: 1.65; }
        .message::before {
            content: "";
            display: block;
            width: 48px;
            height: 4px;
            margin-bottom: 18px;
            border-radius: 999px;
            background: {{ $accent }};
        }
        .detail {
            margin-top: 18px;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #f8fafc;
            color: #334155;
            font-size: 14px;
            line-height: 1.5;
        }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 750;
            border: 1px solid transparent;
        }
        .btn-primary { background: {{ $primary }}; color: white; }
        .btn-primary:hover { background: {{ $secondary }}; }
        .btn-secondary { background: white; color: #0f172a; border-color: #cbd5e1; }
        .hint { margin-top: 26px; color: #64748b; font-size: 13px; }
        @media (max-width: 720px) {
            body { padding: 0; place-items: stretch; background: #fff; }
            .shell { width: 100%; min-height: 100vh; grid-template-columns: 1fr; border: 0; border-radius: 0; box-shadow: none; }
            .brand { min-height: auto; padding: 24px; }
            .logo-card { width: 144px; }
            .code { margin-top: 34px; font-size: 72px; }
            .content { padding: 28px 24px 36px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="brand" aria-label="E Support">
            <div class="logo-card">
                <img src="{{ asset('images/logo.png') }}" alt="E Support">
            </div>
            <div>
                <div class="code">{{ $code }}</div>
                <p>e-support system</p>
            </div>
        </section>

        <section class="content">
            <div class="eyebrow">Estado del sistema</div>
            <h1>{{ $title }}</h1>
            <p class="message">{{ $message }}</p>

            @if($detail)
                <div class="detail">{{ $detail }}</div>
            @endif

            @if($showHome || $showBack)
            <div class="actions">
                @if($showHome)
                    <a class="btn btn-primary" href="{{ $homeUrl }}">
                        {{ $homeLabel ?: (auth()->check() ? 'Ir al inicio' : 'Iniciar sesion') }}
                    </a>
                @endif
                @if($showBack)
                    <a class="btn btn-secondary" href="{{ url()->previous() }}">Volver</a>
                @endif
            </div>
            @endif

            <p class="hint">Si el problema continua, contacta al administrador del sistema.</p>
        </section>
    </main>
</body>
</html>
