#!/bin/bash

printf "🔄 Iniciando actualización de NXDNHosts...\n"

# Ajuste de permisos previos (solo en /home/pi)
printf "🔧 Ajustando permisos en directorios locales...\n"
sudo chmod 777 -R /home/pi/NXDNClients > /dev/null 2>&1
printf "✅ Permisos configurados correctamente.\n"

# Descarga del archivo
cd /home/pi || { printf "❌ Error: No se puede acceder a /home/pi\n"; exit 1; }

printf "📥 Descargando NXDNHosts.json desde mirror oficial...\n"

# Descarga desde Pi-Star
if wget -O NXDNHosts.json -q https://www.pistar.uk/downloads/NXDNHosts.json; then
    printf "✅ Descarga completada con éxito.\n"
else
    printf "❌ Error al descargar el archivo NXDNHosts.json.\n"
    exit 1
fi

# Distribución a NXDNGateway
printf "📤 Instalando NXDNHosts.json en NXDNGateway...\n"

if sudo mv /home/pi/NXDNHosts.json /home/pi/NXDNClients/NXDNGateway/NXDNHosts.json; then
    printf "✅ Archivo instalado en NXDNGateway.\n"
else
    printf "❌ Error al mover el archivo a NXDNGateway.\n"
    exit 1
fi

# Finalización
printf "🎉 NXDNHosts actualizados correctamente.\n"
sleep 3
