<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso Privado - <?= htmlspecialchars($sale['title'] ?? 'Espacio Privado') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .access-container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 480px;
      width: 100%;
      padding: 2.5rem;
      animation: slideUp 0.4s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .lock-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      color: white;
      font-size: 2rem;
    }

    h1 {
      font-size: 1.75rem;
      font-weight: 700;
      color: #2d3748;
      text-align: center;
      margin-bottom: 0.5rem;
    }

    .subtitle {
      text-align: center;
      color: #718096;
      font-size: 0.95rem;
      margin-bottom: 2rem;
    }

    .sale-title {
      background: #f7fafc;
      border-left: 4px solid #667eea;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    .sale-title strong {
      color: #2d3748;
      font-weight: 600;
    }

    .error-message {
      background: #fff5f5;
      border: 1px solid #fc8181;
      color: #c53030;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: shake 0.4s ease-in-out;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }

    .error-message i {
      font-size: 1.25rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    label {
      display: block;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 0.5rem;
      font-size: 0.95rem;
    }

    .code-input {
      width: 100%;
      padding: 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 1.5rem;
      font-family: 'Courier New', monospace;
      letter-spacing: 0.5rem;
      text-align: center;
      transition: all 0.3s ease;
      outline: none;
    }

    .code-input:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .code-input::placeholder {
      letter-spacing: normal;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
    }

    .hint {
      color: #718096;
      font-size: 0.85rem;
      margin-top: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .submit-btn {
      width: 100%;
      padding: 1rem;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }

    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

    .submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 1.5rem;
      color: #718096;
      text-decoration: none;
      font-size: 0.9rem;
      transition: color 0.3s ease;
    }

    .back-link:hover {
      color: #667eea;
    }

    .back-link i {
      margin-right: 0.5rem;
    }

    @media (max-width: 480px) {
      .access-container {
        padding: 2rem 1.5rem;
      }

      h1 {
        font-size: 1.5rem;
      }

      .code-input {
        font-size: 1.25rem;
        letter-spacing: 0.4rem;
      }
    }
  </style>
</head>
<body>
  <div class="access-container">
    <div class="lock-icon">
      <i class="fas fa-lock"></i>
    </div>

    <h1>Espacio Privado</h1>
    <p class="subtitle">Este espacio requiere un código de acceso</p>

    <div class="sale-title">
      <strong><?= htmlspecialchars($sale['title'] ?? 'Espacio Privado') ?></strong>
    </div>

    <?php if (!empty($accessError)): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($accessError) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="accessForm">
      <div class="form-group">
        <label for="access_code">
          <i class="fas fa-key"></i> Código de acceso
        </label>
        <input
          type="text"
          id="access_code"
          name="access_code"
          class="code-input"
          placeholder="000000"
          pattern="[0-9]{6}"
          maxlength="6"
          required
          autofocus
          autocomplete="off"
        >
        <div class="hint">
          <i class="fas fa-info-circle"></i>
          Ingresa el código de 6 dígitos proporcionado por el vendedor
        </div>
      </div>

      <button type="submit" class="submit-btn" id="submitBtn">
        <i class="fas fa-unlock"></i>
        <span>Acceder al Espacio</span>
      </button>
    </form>

    <a href="venta-garaje.php" class="back-link">
      <i class="fas fa-arrow-left"></i>
      Volver a espacios públicos
    </a>
  </div>

  <script>
    // Auto-focus y validación
    const codeInput = document.getElementById('access_code');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('accessForm');

    // Solo permitir números
    codeInput.addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);

      // Auto-submit cuando se completen los 6 dígitos
      if (this.value.length === 6) {
        submitBtn.disabled = false;
      } else {
        submitBtn.disabled = true;
      }
    });

    // Prevenir submit si no tiene 6 dígitos
    form.addEventListener('submit', function(e) {
      if (codeInput.value.length !== 6) {
        e.preventDefault();
        codeInput.focus();
      }
    });

    // Estado inicial
    submitBtn.disabled = true;
  </script>
</body>
</html>
