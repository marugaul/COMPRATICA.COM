# Prevenir Desincronización de affiliate_id en Productos

## Problema
Los productos creados desde `admin/dashboard.php` no incluyen `affiliate_id` ni `sale_id`, causando que queden huérfanos o con IDs incorrectos.

## Soluciones Implementadas

### 1. Script de Auto-Fix (Inmediato)
**Archivo:** `auto_fix_affiliate_id.php`

**Qué hace:**
- Detecta productos con `affiliate_id` incorrecto
- Los corrige automáticamente basándose en el `sale_id`
- Puede ejecutarse manualmente o vía cron

**Uso manual:**
```
https://compratica.com/auto_fix_affiliate_id.php
```

**Uso en cron (recomendado - cada 6 horas):**
```bash
0 */6 * * * /usr/bin/php /home/comprati/public_html/auto_fix_affiliate_id.php >/dev/null 2>&1
```

---

### 2. Modificación del Panel de Afiliado (Preventivo)

**Archivo a modificar:** `affiliate/products.php`

Ya tiene la validación correcta en las líneas 44-49:
```php
// Validar que el espacio sea del afiliado y esté activo
$chk = $pdo->prepare("SELECT 1 FROM sales WHERE id=? AND affiliate_id=? AND is_active=1");
$chk->execute([$sale_id, $aff_id]);
if (!$chk->fetchColumn()) {
    throw new RuntimeException('El espacio seleccionado no es válido o no está activo.');
}
```

✅ **Este código ya previene que se creen productos con affiliate_id incorrecto desde el panel de afiliado.**

---

### 3. Deprecar admin/dashboard.php para crear productos

**Problema:** `admin/dashboard.php` permite crear productos SIN `affiliate_id` ni `sale_id`.

**Solución recomendada:**

Opción A: Modificar `admin/dashboard.php` línea 86-88 para que REQUIERA `sale_id` y auto-asigne `affiliate_id`:

```php
if ($action === 'create') {
    // NUEVO: Requerir sale_id
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    if (!$sale_id) {
        $msg = "Error: Debe seleccionar un espacio de venta.";
    } else {
        // Auto-obtener affiliate_id del espacio
        $stmt = $pdo->prepare("SELECT affiliate_id FROM sales WHERE id=?");
        $stmt->execute([$sale_id]);
        $affiliate_id = (int)$stmt->fetchColumn();

        if (!$affiliate_id) {
            $msg = "Error: Espacio no válido.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products
                (affiliate_id, sale_id, name, description, price, stock, image, currency, active, category, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $affiliate_id,
                $sale_id,
                $name,
                $desc,
                $price,
                $stock,
                $imageName,
                $currency,
                $active,
                $category,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);

            $msg = "Producto creado.";
        }
    }
}
```

Opción B (más simple): Agregar nota en el panel que redirija a `affiliate/products.php`:

```html
<div class="alert alert-warning">
    <strong>⚠️ Importante:</strong> Para crear productos,
    usa el panel de <a href="/affiliate/products.php">Productos del Afiliado</a>.
</div>
```

---

### 4. Trigger de Base de Datos (Opcional - más robusto)

Si tu versión de SQLite lo soporta, puedes crear un trigger que valide automáticamente:

```sql
CREATE TRIGGER validate_product_affiliate
BEFORE INSERT ON products
FOR EACH ROW
WHEN NEW.sale_id IS NOT NULL
BEGIN
    SELECT RAISE(ABORT, 'affiliate_id must match the sale affiliate_id')
    WHERE NEW.affiliate_id != (SELECT affiliate_id FROM sales WHERE id = NEW.sale_id);
END;
```

**Nota:** SQLite tiene limitaciones con triggers complejos. Es mejor usar las soluciones 1 y 2.

---

## Resumen de Implementación

### Inmediato (hoy):
1. ✅ Ejecutar `fix_vanessa_products.php` para corregir el problema actual
2. ✅ Configurar `auto_fix_affiliate_id.php` en cron para auto-corrección

### Prevención (próxima semana):
3. Modificar `admin/dashboard.php` según Opción A o agregar Opción B
4. (Opcional) Implementar trigger de base de datos

---

## Monitoreo

Ejecuta periódicamente:
```bash
php auto_fix_affiliate_id.php
```

Si reporta 0 productos incorrectos, todo está funcionando correctamente.
