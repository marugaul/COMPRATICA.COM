<?php
/**
 * Script para verificar y crear el directorio de sesiones con los permisos correctos
 */

echo "<h1>Fix Sessions Directory</h1>";

$sessionsDir = __DIR__ . '/sessions';

echo "<h2>1. Verificando directorio de sesiones</h2>";
echo "Ruta: <code>$sessionsDir</code><br>";

// Verificar si existe
if (is_dir($sessionsDir)) {
    echo "✅ El directorio existe<br>";
} else {
    echo "❌ El directorio NO existe - intentando crear...<br>";
    if (@mkdir($sessionsDir, 0755, true)) {
        echo "✅ Directorio creado exitosamente<br>";
    } else {
        echo "❌ No se pudo crear el directorio. Verifica permisos del directorio padre.<br>";
        exit;
    }
}

// Verificar permisos
echo "<h2>2. Verificando permisos</h2>";
$perms = fileperms($sessionsDir);
$permsOctal = substr(sprintf('%o', $perms), -4);
echo "Permisos actuales: <strong>$permsOctal</strong><br>";

if (is_writable($sessionsDir)) {
    echo "✅ El directorio es escribible<br>";
} else {
    echo "❌ El directorio NO es escribible - intentando cambiar permisos...<br>";
    if (@chmod($sessionsDir, 0755)) {
        echo "✅ Permisos cambiados a 0755<br>";
    } else {
        echo "❌ No se pudieron cambiar los permisos automáticamente.<br>";
        echo "<p><strong>Ejecuta manualmente en SSH:</strong></p>";
        echo "<pre>chmod 755 /home/comprati/public_html/sessions</pre>";
    }
}

// Probar escribir un archivo de prueba
echo "<h2>3. Probando escritura</h2>";
$testFile = $sessionsDir . '/test_' . time() . '.txt';
if (@file_put_contents($testFile, 'test')) {
    echo "✅ Se puede escribir archivos en el directorio<br>";
    @unlink($testFile);
} else {
    echo "❌ NO se puede escribir en el directorio<br>";
    echo "<p>Error: " . error_get_last()['message'] . "</p>";
}

// Información adicional
echo "<h2>4. Información del servidor</h2>";
echo "Usuario PHP: <strong>" . get_current_user() . "</strong><br>";
if (function_exists('posix_geteuid')) {
    echo "UID: <strong>" . posix_geteuid() . "</strong><br>";
    echo "GID: <strong>" . posix_getegid() . "</strong><br>";
}

echo "<h2>5. Solución</h2>";
echo "<p>Si el problema persiste, ejecuta estos comandos en SSH:</p>";
echo "<pre>cd /home/comprati/public_html
mkdir -p sessions
chmod 755 sessions
chown comprati:comprati sessions</pre>";

echo "<h3>O cambia el directorio de sesiones a /tmp</h3>";
echo "<p>Edita tus archivos PHP y cambia el session_save_path a '/tmp' temporalmente:</p>";
echo "<pre>ini_set('session.save_path', '/tmp');</pre>";
