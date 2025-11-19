#!/bin/bash

echo "========================================="
echo "CompraTica Email Marketing - Instalador"
echo "========================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if running from correct directory
if [ ! -f "composer.json" ]; then
    echo -e "${RED}Error: Debe ejecutar este script desde /home/user/COMPRATICA.COM${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Instalando dependencias con Composer...${NC}"
if command -v composer &> /dev/null; then
    composer install
    echo -e "${GREEN}✓ Dependencias instaladas${NC}"
else
    echo -e "${RED}✗ Composer no encontrado. Instalar manualmente.${NC}"
    echo "  curl -sS https://getcomposer.org/installer | php"
    echo "  php composer.phar install"
fi
echo ""

echo -e "${YELLOW}2. Creando directorios necesarios...${NC}"
mkdir -p uploads/email_attachments
chmod 755 uploads/email_attachments
echo -e "${GREEN}✓ Directorio uploads/email_attachments creado${NC}"
echo ""

echo -e "${YELLOW}3. Configuración de Base de Datos${NC}"
echo "Por favor ingrese los datos de conexión a MySQL:"
read -p "Host (default: localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Usuario MySQL: " DB_USER
read -sp "Contraseña MySQL: " DB_PASS
echo ""
read -p "Nombre de base de datos (default: comprati_marketplace): " DB_NAME
DB_NAME=${DB_NAME:-comprati_marketplace}

echo ""
echo -e "${YELLOW}Ejecutando schema SQL...${NC}"

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < admin/setup_email_marketing.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tablas creadas exitosamente${NC}"
else
    echo -e "${RED}✗ Error al crear tablas. Verificar credenciales.${NC}"
    exit 1
fi
echo ""

echo -e "${YELLOW}4. Insertando plantillas de ejemplo...${NC}"

# Insert Mixtico template
MIXTICO_HTML=$(cat admin/email_templates/mixtico_template.html | sed "s/'/\\\\'/g")
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO email_templates (name, company, subject_default, html_content, created_at)
VALUES (
    'Mixtico - Transporte Privado',
    'Mixtico',
    'Su Transporte Privado en Costa Rica 🚐',
    '$MIXTICO_HTML',
    NOW()
) ON DUPLICATE KEY UPDATE html_content = VALUES(html_content);
EOF

# Insert CRV-SOFT template
CRVSOFT_HTML=$(cat admin/email_templates/crv_soft_template.html | sed "s/'/\\\\'/g")
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO email_templates (name, company, subject_default, html_content, created_at)
VALUES (
    'CRV-SOFT - Soluciones Tecnológicas',
    'CRV-SOFT',
    'Transforme su Negocio con Tecnología 💻',
    '$CRVSOFT_HTML',
    NOW()
) ON DUPLICATE KEY UPDATE html_content = VALUES(html_content);
EOF

# Insert CompraTica template
COMPRATICA_HTML=$(cat admin/email_templates/compratica_template.html | sed "s/'/\\\\'/g")
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO email_templates (name, company, subject_default, html_content, created_at)
VALUES (
    'CompraTica - Marketplace Costa Rica',
    'CompraTica',
    '¡Descubre las Mejores Ofertas en CompraTica! 🇨🇷',
    '$COMPRATICA_HTML',
    NOW()
) ON DUPLICATE KEY UPDATE html_content = VALUES(html_content);
EOF

echo -e "${GREEN}✓ Plantillas insertadas${NC}"
echo ""

echo -e "${YELLOW}5. Insertando configuraciones SMTP de ejemplo...${NC}"

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO email_smtp_configs (name, from_email, from_name, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, is_active)
VALUES
    ('Mixtico', 'info@mixtico.net', 'Mixtico Shuttle Service', 'smtp.gmail.com', 587, 'info@mixtico.net', '', 'tls', 1),
    ('CRV-SOFT', 'contacto@crv-soft.com', 'CRV-SOFT', 'smtp.gmail.com', 587, 'contacto@crv-soft.com', '', 'tls', 1),
    ('Compratica', 'info@compratica.com', 'CompraTica Costa Rica', 'smtp.gmail.com', 587, 'info@compratica.com', '', 'tls', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
EOF

echo -e "${GREEN}✓ Configuraciones SMTP creadas (requieren completar contraseñas)${NC}"
echo ""

echo "========================================="
echo -e "${GREEN}✓ Instalación Completada${NC}"
echo "========================================="
echo ""
echo "Próximos pasos:"
echo ""
echo "1. Configurar SMTP:"
echo "   https://compratica.com/admin/email_marketing.php?page=smtp-config"
echo ""
echo "2. Configurar DNS para prevenir SPAM:"
echo "   - Agregar registro SPF en DNS"
echo "   - Configurar DKIM"
echo ""
echo "3. Acceder al sistema:"
echo "   https://compratica.com/admin/email_marketing.php"
echo ""
echo "Leer documentación completa en:"
echo "   admin/EMAIL_MARKETING_README.md"
echo ""
