<?php
// Renderiza fila de botones sociales (Facebook + Google). Apple eliminado.
?>
<div class="actions" style="display:flex; gap:8px; flex-wrap:wrap;">
  <a class="btn btn-social btn-fb" href="/auth/login_facebook.php">
    <!-- Ícono FB (SVG simple) -->
    <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1.9 0 1.8.1 1.8.1v2h-1c-1 0-1.3.6-1.3 1.2V12h2.2l-.3 3h-1.9v7A10 10 0 0 0 22 12z"/></svg>
    <span>Continuar con Facebook</span>
  </a>
  <a class="btn btn-social btn-gg" href="/auth/login_google.php" style="background:#fff;">
    <!-- Ícono Google (SVG simple) -->
    <svg width="16" height="16" viewBox="0 0 533.5 544.3" aria-hidden="true"><path fill="#EA4335" d="M533.5 278.4c0-17.4-1.6-34.1-4.7-50.2H272.1v95.1h147c-6.3 34-25.2 62.7-53.9 81.9v67h87.2c51.1-47.1 80.1-116.5 80.1-193.8z"/><path fill="#34A853" d="M272.1 544.3c72.8 0 134.1-24.1 178.8-65.1l-87.2-67c-24.2 16.2-55.3 25.7-91.6 25.7-70.4 0-130-47.5-151.3-111.4H31.7v69.9c44.4 88.1 136.1 147.9 240.4 147.9z"/><path fill="#4A90E2" d="M120.8 326.5c-10.7-31.7-10.7-66 0-97.7V158.9H31.7c-43.1 86.3-43.1 189.1 0 275.4l89.1-67.8z"/><path fill="#FBBC05" d="M272.1 107.7c39.6-.6 77.6 13.9 106.7 40.7l79.8-79.8C405.9 24 341.4-.2 272.1 0 167.8 0 76 59.8 31.7 147.9l89.1 69.9C142.1 155.9 201.7 108.4 272.1 107.7z"/></svg>
    <span>Continuar con Google</span>
  </a>
</div>
