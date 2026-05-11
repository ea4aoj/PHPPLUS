#!/bin/bash
# setup_esp32_web.sh - Configura ESP32 Web Programmer con HTTPS
# Ejecutar con: sudo ./setup_esp32_web.sh
# Autor: REM-ESP 2025 | Adaptado por IA Assistant

set -e
echo "🔧 Configurando Programador ESP32 Web con HTTPS..."

# 1. Instalar dependencias
echo "📦 Instalando/verificando dependencias..."
apt update -qq
apt install -y apache2 php libapache2-mod-php python3-pip openssl > /dev/null 2>&1 || true

# 2. Instalar esptool (si no está)
echo "🔌 Verificando esptool..."
if ! python3 -m esptool --help > /dev/null 2>&1; then
    pip3 install esptool --break-system-packages -q
fi

# 3. Generar certificado autofirmado
echo "🔐 Generando certificado HTTPS autofirmado..."
mkdir -p /etc/apache2/ssl

# Obtener IP local automáticamente
LOCAL_IP=$(hostname -I | awk '{print $1}' | tr -d ' ')

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout /etc/apache2/ssl/esp32.key \
  -out /etc/apache2/ssl/esp32.crt \
  -subj "/C=ES/ST=Spain/L=Home/O=REM-ESP/CN=esp32.local" \
  -addext "subjectAltName=DNS:esp32.local,DNS:raspberrypi.local,IP:${LOCAL_IP}" 2>/dev/null || \
openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout /etc/apache2/ssl/esp32.key \
  -out /etc/apache2/ssl/esp32.crt \
  -subj "/C=ES/ST=Spain/L=Home/O=REM-ESP/CN=${LOCAL_IP}"

chmod 600 /etc/apache2/ssl/esp32.key
chmod 644 /etc/apache2/ssl/esp32.crt
echo "✅ Certificado generado: /etc/apache2/ssl/"

# 4. Configurar Apache para HTTPS
echo "⚙️ Configurando Apache..."
a2enmod ssl > /dev/null 2>&1 || true
a2enmod headers > /dev/null 2>&1 || true

# Configurar sitio HTTPS
cat > /etc/apache2/sites-available/esp32-ssl.conf << APACHE_EOF
<VirtualHost *:443>
    ServerName esp32.local
    ServerAlias raspberrypi.local ${LOCAL_IP}
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /etc/apache2/ssl/esp32.crt
    SSLCertificateKeyFile /etc/apache2/ssl/esp32.key
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Headers para Web Serial API
    Header always set Permissions-Policy "serial=(self)"
    Header always set Feature-Policy "serial 'self'"
    
    ErrorLog \${APACHE_LOG_DIR}/esp32-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/esp32-ssl-access.log combined
</VirtualHost>
APACHE_EOF

# Configurar redirección HTTP → HTTPS
cat > /etc/apache2/sites-available/esp32-redirect.conf << APACHE_EOF
<VirtualHost *:80>
    ServerName esp32.local
    ServerAlias raspberrypi.local ${LOCAL_IP}
    Redirect permanent / https://${LOCAL_IP}/
</VirtualHost>
APACHE_EOF

# Activar sitios y desactivar default
a2ensite esp32-ssl > /dev/null 2>&1 || true
a2ensite esp32-redirect > /dev/null 2>&1 || true
a2dissite 000-default.conf > /dev/null 2>&1 || true

# 5. Crear carpetas y CORREGIR permisos
echo "📁 Configurando permisos..."
mkdir -p /var/www/html/{uploads,logs,jobs}
# ✅ CORRECCIÓN: www-data:www-data (usuario:grupo)
chown -R www-data:www-data /var/www/html
chmod 755 /var/www/html/{uploads,logs,jobs} 2>/dev/null || true
chmod 644 /var/www/html/*.php 2>/dev/null || true

# 6. Reiniciar Apache
echo "🔄 Reiniciando Apache..."
systemctl daemon-reload
systemctl restart apache2

# 7. Mostrar información final
echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║  ✅ ¡Configuración completada exitosamente! 🎉      ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo "🔗 Accede desde cualquier navegador en tu red LAN:"
echo "   👉 https://${LOCAL_IP}/esp32.php"
echo ""
echo "🌐 Para acceso desde INTERNET:"
echo "   ┌─────────────────────────────────────────┐"
echo "   │ Opción A: Redirección de puertos + DDNS │"
echo "   │ 1. En tu router: 443 → ${LOCAL_IP}:443      │"
echo "   │ 2. Registra dominio en duckdns.org      │"
echo "   │ 3. Configura ddclient para IP dinámica  │"
echo "   ├─────────────────────────────────────────┤"
echo "   │ Opción B: Cloudflare Tunnel (Recomend.) │"
echo "   │ sudo apt install cloudflared            │"
echo "   │ cloudflared tunnel login                │"
echo "   │ cloudflared tunnel create esp32-tunnel  │"
echo "   │ cloudflared tunnel route dns ...        │"
echo "   └─────────────────────────────────────────┘"
echo ""
echo "⚠️  La primera vez, el navegador mostrará:"
echo "   'La conexión no es privada' o 'Certificado no válido'"
echo "   👉 Haz clic en: 'Avanzado' → 'Continuar a ${LOCAL_IP}'"
echo ""
echo "📋 Para reemplazar con certificado real (Let's Encrypt):"
echo "   sudo apt install certbot python3-certbot-apache"
echo "   sudo certbot --apache -d tudominio.com"
echo ""
echo "🛠️  Comandos útiles:"
echo "   • Ver logs Apache:  tail -f /var/log/apache2/esp32-ssl-error.log"
echo "   • Reiniciar Apache: sudo systemctl restart apache2"
echo "   • Ver estado:       sudo systemctl status apache2"
echo ""
echo "📦 Archivos creados:"
echo "   • /etc/apache2/ssl/esp32.crt (certificado público)"
echo "   • /etc/apache2/ssl/esp32.key (clave privada - ¡NO COMPARTIR!)"
echo "   • /etc/apache2/sites-available/esp32-ssl.conf"
echo "   • /var/www/html/esp32.php  ← COLOCA TU ARCHIVO AQUÍ"
echo ""
