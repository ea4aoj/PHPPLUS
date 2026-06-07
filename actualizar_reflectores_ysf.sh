#!/bin/bash

printf "🔄 Iniciando actualización de YSFHosts...\n"

# Ajuste de permisos previos (solo en /home/pi)
printf "🔧 Ajustando permisos en directorios locales...\n"
sudo chmod 777 -R /home/pi/YSFClients > /dev/null 2>&1
printf "✅ Permisos configurados correctamente.\n"

# Descarga del archivo
cd /home/pi || { printf "❌ Error: No se puede acceder a /home/pi\n"; exit 1; }

printf "📥 Descargando YSFHosts.txt desde mirror oficial...\n"
# Usamos -O para forzar el nombre del archivo y -q para que sea silencioso
if wget -O YSFHosts.txt -q https://www.pistar.uk/downloads/YSF_Hosts.txt; then
    printf "✅ Descarga completada con éxito.\n"
else
    printf "❌ Error al descargar el archivo.\n"
    exit 1
fi

# Distribución a YSFGateway
printf "📤 Instalando YSFHosts.txt en YSFGateway...\n"
if sudo mv /home/pi/YSFHosts.txt /home/pi/YSFClients/YSFGateway/YSFHosts.txt; then
    printf "✅ Archivo instalado en YSFGateway.\n"
else
    printf "❌ Error al mover el archivo a YSFGateway.\n"
    exit 1
fi

# Copia adicional a Fusion2X (usando sudo para escribir en /opt)
printf "📤 Copiando YSFHosts.txt a Fusion2X (/opt)...\n"
if sudo cp /home/pi/YSFClients/YSFGateway/YSFHosts.txt /opt/fusion2x/data/YSFHosts.txt; then
    sudo chmod 644 /opt/fusion2x/data/YSFHosts.txt
    printf "✅ Archivo copiado en /opt/fusion2x/data con permisos correctos.\n"
else
    printf "❌ Error al copiar el archivo a Fusion2X.\n"
    exit 1
fi

# Finalización
printf "🎉 YSFHosts actualizados correctamente.\n"
sleep 3
