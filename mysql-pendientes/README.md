# 📥 MySQL Pendientes

Esta carpeta contiene archivos SQL que serán ejecutados **automáticamente** por el cron.

## 🚀 Cómo usar:

1. **Crea un archivo .sql** aquí con tu script SQL
2. **Haz push a git**
3. **El cron lo ejecuta automáticamente** (cada minuto)
4. **El archivo se mueve** a `mysql-ejecutados/`
5. **Se genera un log** en `mysql-logs/`

## 📝 Ejemplo:

```sql
-- mysql-pendientes/mi-script.sql
CREATE TABLE ejemplo (
    id INT PRIMARY KEY,
    nombre VARCHAR(255)
);
```

## ⚠️ Importante:

- Los archivos se ejecutan en **orden alfabético**
- Usa prefijos numéricos: `001-tabla1.sql`, `002-tabla2.sql`
- Solo archivos `.sql` o `.txt` son procesados
- Si falla, el archivo permanece aquí y puedes ver el error en los logs

## 📊 Ver resultados:

- **Logs**: `mysql-logs/`
- **Ejecutados**: `mysql-ejecutados/`
