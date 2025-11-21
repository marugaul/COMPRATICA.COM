<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Cron para Campañas Programadas</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #dc2626; }
        h2 { color: #0891b2; border-bottom: 2px solid #0891b2; padding-bottom: 10px; }
        .alert { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .alert-danger { background: #fee; border-left: 4px solid #dc2626; }
        .alert-success { background: #efe; border-left: 4px solid #16a34a; }
        .alert-info { background: #eff6ff; border-left: 4px solid #0891b2; }
        .code-box { background: #1e293b; color: #f1f5f9; padding: 20px; border-radius: 5px; font-family: monospace; overflow-x: auto; }
        .copy-btn { background: #0891b2; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        .copy-btn:hover { background: #0e7490; }
        .step { background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin: 15px 0; border-radius: 4px; }
        .step strong { color: #f59e0b; }
        ol { line-height: 2; }
        .btn { display: inline-block; padding: 12px 24px; background: #0891b2; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #0e7490; }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚙️ Configurar Envío Automático de Campañas Programadas</h1>

        <div class="alert alert-danger">
            <strong>⚠️ PROBLEMA ACTUAL:</strong><br>
            Las campañas programadas NO se envían automáticamente porque falta configurar el cron job que ejecuta el procesador cada minuto.
        </div>

        <h2>📋 Comando para Agregar al Cron</h2>
        <p>Copia y pega esta línea en tu crontab:</p>
        <div class="code-box" id="cronCommand">* * * * * cd /home/comprati/public_html/admin && php process_scheduled_campaigns.php >> /tmp/scheduled_campaigns.log 2>&1</div>
        <button class="copy-btn" onclick="copyCronCommand()">📋 Copiar Comando</button>

        <h2>🔧 Cómo Configurarlo</h2>

        <div class="step">
            <strong>Paso 1:</strong> Accede al panel de control de tu hosting (cPanel, Plesk, o similar)
        </div>

        <div class="step">
            <strong>Paso 2:</strong> Busca la sección "Cron Jobs" o "Tareas Programadas"
        </div>

        <div class="step">
            <strong>Paso 3:</strong> Crea un nuevo cron job con estos valores:
            <ul>
                <li><strong>Minuto:</strong> <code>*</code></li>
                <li><strong>Hora:</strong> <code>*</code></li>
                <li><strong>Día del mes:</strong> <code>*</code></li>
                <li><strong>Mes:</strong> <code>*</code></li>
                <li><strong>Día de la semana:</strong> <code>*</code></li>
                <li><strong>Comando:</strong> <code>cd /home/comprati/public_html/admin && php process_scheduled_campaigns.php >> /tmp/scheduled_campaigns.log 2>&1</code></li>
            </ul>
        </div>

        <div class="step">
            <strong>Paso 4:</strong> Guarda el cron job y espera 1 minuto
        </div>

        <h2>✅ Verificar que Funciona</h2>
        <ol>
            <li>Espera 1 minuto después de configurar el cron</li>
            <li>Haz clic en el botón "Probar Procesador" abajo</li>
            <li>Deberías ver un log de ejecución</li>
        </ol>

        <div style="text-align: center; margin: 30px 0;">
            <a href="SEND_SCHEDULED_NOW.php" class="btn">🚀 Probar Procesador de Campañas</a>
            <a href="email_marketing.php?page=campaigns" class="btn btn-secondary">📧 Ver Campañas</a>
        </div>

        <div class="alert alert-info">
            <strong>ℹ️ ¿Qué hace el cron job?</strong><br>
            • Se ejecuta cada minuto<br>
            • Busca campañas con <code>status='scheduled'</code> cuya hora programada ya llegó<br>
            • Las marca como <code>'sending'</code> y comienza el envío de emails<br>
            • Guarda log en <code>/tmp/scheduled_campaigns.log</code>
        </div>

        <h2>⏰ Información de Zona Horaria</h2>
        <div class="alert alert-info">
            <strong>Importante:</strong><br>
            • El servidor está en zona horaria <strong>UTC</strong><br>
            • Costa Rica está en <strong>UTC-6</strong><br>
            • Si programas una campaña para las <strong>4:30 PM CR</strong>, el sistema la guarda como <strong>22:30 UTC</strong><br>
            • El cron verifica: <code>scheduled_at <= NOW()</code> en UTC
        </div>

        <h2>🔄 Alternativa: Envío Manual</h2>
        <p>Mientras configuras el cron, puedes enviar campañas programadas manualmente:</p>
        <ol>
            <li>Ve a la página de campañas</li>
            <li>Busca la campaña con estado "Programada"</li>
            <li>Haz clic en el botón amarillo <strong>"Enviar Ahora"</strong></li>
        </ol>
    </div>

    <script>
    function copyCronCommand() {
        const command = document.getElementById('cronCommand').textContent;
        navigator.clipboard.writeText(command).then(() => {
            const btn = event.target;
            btn.textContent = '✓ Copiado!';
            btn.style.background = '#16a34a';
            setTimeout(() => {
                btn.textContent = '📋 Copiar Comando';
                btn.style.background = '#0891b2';
            }, 2000);
        });
    }
    </script>
</body>
</html>
