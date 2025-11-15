VENTAGARAJE — Paquete de fixes (sin opción 3 “legado”)
=====================================================

Contenido:
1) tools/fix_affiliate_orders.php
   - Backfill de órdenes antiguas para completar orders.affiliate_id a partir del producto.
   - Úsalo UNA vez y luego bórralo.

2) buy.php (referencia)
   - Versión de referencia con el cambio mínimo: guarda affiliate_id y sale_id en la orden.
   - Si tu buy.php actual tiene personalizaciones de UI/pagos, copia SOLO el bloque marcado
     “// [AFILIADO] ...” a tu archivo actual.

Cómo usar
---------
A) Sube tools/fix_affiliate_orders.php a /tools/ de tu hosting.
   Ejecuta en el navegador: https://TU_DOMINIO/tools/fix_affiliate_orders.php
   Verás el número de órdenes actualizadas. Luego ELIMINA el archivo por seguridad.

B) Aplica el cambio mínimo en tu buy.php actual:
   - Asegúrate que checkout.php envía estos campos hidden:
       <input type="hidden" name="affiliate_id" value="<?= (int)$affiliate_id ?>">
       <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
   - En buy.php, añade el bloque marcado “// [AFILIADO]…” antes del INSERT,
     y ajusta tu INSERT para incluir affiliate_id y sale_id.

Notas
-----
- Este paquete NO incluye la opción 3 (consulta “legado”). El listado del afiliado seguirá
  filtrando estrictamente por orders.affiliate_id = afiliado logueado.
- Si después del backfill y el patch de buy.php algún pedido no aparece en el afiliado,
  revisa que el producto tenga affiliate_id correcto.
