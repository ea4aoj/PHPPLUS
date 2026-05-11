#!/bin/bash
# revert_esp32_web.sh
# Revierte los cambios hechos por setup_esp32_web.sh
# y deja Apache como una instalación normal/original

set -e

echo "🧹 Revirtiendo configuración ESP32 HTTPS..."

# 1. Desactivar sitios creados por el script
echo "🔻 Desactivando sitios personalizados..."

sudo a2dissite esp32-ssl.conf >/dev/null 2>&1 || true
sudo a2dissite esp32-redirect.conf >/dev/null 2>&1 || true

# 2. Reactivar sitio por defecto
echo "🔺 Reactivando sitio por defecto Apache..."

sudo a2ensite 000-default.conf >/dev/null 2>&1 || true

# 3. Eliminar configuraciones creadas
echo "🗑️ Eliminando archivos de configuración..."

sudo rm -f /etc/apache2/sites-available/esp32-ssl.conf
sudo rm -f /etc/apache2/sites-available/esp32-redirect.conf

# 4. Eliminar certificados autofirmados
echo "🔐 Eliminando certificados autofirmados..."

sudo rm -f /etc/apache2/ssl/esp32.crt
sudo rm -f /etc/apache2/ssl/esp32.key

# Intentar borrar carpeta ssl si queda vacía
sudo rmdir /etc/apache2/ssl 2>/dev/null || true

# 5. Desactivar módulos extra si no se usan
echo "⚙️ Restaurando módulos Apache..."

sudo a2dismod ssl >/dev/null 2>&1 || true
sudo a2dismod headers >/dev/null 2>&1 || true

# 6. Restaurar puerto HTTP normal
echo "🌐 Restaurando configuración HTTP..."

if [ -f /etc/apache2/ports.conf ]; then
    sudo sed -i '/Listen 443/d' /etc/apache2/ports.conf
fi

# 7. Verificar configuración Apache
echo "🧪 Verificando configuración..."

sudo apache2ctl configtest

# 8. Reiniciar Apache
echo "🔄 Reiniciando Apache..."

sudo systemctl restart apache2

echo ""
echo "═══════════════════════════════════════"
echo "✅ Apache restaurado correctamente"
echo "═══════════════════════════════════════"
echo ""

LOCAL_IP=$(hostname -I | awk '{print $1}')

echo "📍 Acceso local:"
echo "   http://${LOCAL_IP}/"
echo ""

echo "📋 Sitios activos:"
sudo apache2ctl -S

echo ""
echo "🛠️ Si usabas un dominio y Let's Encrypt:"
echo "   sudo certbot --apache"
echo ""

echo "✅ El servidor ya NO redirige a la IP local por HTTPS."
echo ""
