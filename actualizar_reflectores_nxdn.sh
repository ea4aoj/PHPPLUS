#!/bin/bash

printf "🔄 Iniciando actualización de NXDNHosts...\n"

# Ajuste de permisos previos (solo en /home/pi)
printf "🔧 Ajustando permisos en directorios locales...\n"
sudo chmod 777 -R /home/pi/NXDNClients > /dev/null 2>&1
printf "✅ Permisos configurados correctamente.\n"

# Ir al directorio base
cd /home/pi || { 
    printf "❌ Error: No se puede acceder a /home/pi\n"
    exit 1
}

# Descarga del fichero correcto
printf "📥 Descargando NXDNHosts.txt desde Pi-Star...\n"

if wget -O NXDNHosts.txt -q https://www.pistar.uk/downloads/NXDN_Hosts.txt; then
    printf "✅ Descarga completada con éxito.\n"
else
    printf "❌ Error al descargar NXDN_Hosts.txt\n"
    exit 1
fi

# Instalación en NXDNGateway
printf "📤 Instalando NXDNHosts.txt en NXDNGateway...\n"

if sudo mv /home/pi/NXDNHosts.txt /home/pi/NXDNClients/NXDNGateway/NXDNHosts.txt; then
    printf "✅ Archivo instalado en NXDNGateway.\n"
else
    printf "❌ Error al mover el archivo a NXDNGateway.\n"
    exit 1
fi

# Permisos finales
sudo chmod 644 /home/pi/NXDNClients/NXDNGateway/NXDNHosts.txt > /dev/null 2>&1

# Fin
printf "🎉 NXDNHosts actualizados correctamente.\n"
sleep 3
