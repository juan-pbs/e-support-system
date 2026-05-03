<x-errors.layout
    code="503"
    title="Seccion en mantenimiento"
    :message="$section->message ?: 'Esta seccion esta temporalmente en mantenimiento.'"
    detail="El resto del sistema puede seguir disponible. Intenta entrar nuevamente cuando el equipo termine los ajustes."
    variant="green"
    :showBack="false"
    :showHome="false"
/>
