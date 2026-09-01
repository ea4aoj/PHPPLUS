#!/bin/bash

printf "🔄 Iniciando actualización de YSFHosts...\n"

# Ajuste de permisos previos (solo en /home/pi)
printf "🔧 Ajustando permisos en directorios locales...\n"
sudo chmod 777 -R /home/pi/YSFClients > /dev/null 2>&1
printf "✅ Permisos configurados correctamente.\n"

# Descarga del archivo
cd /home/pi || { printf "❌ Error: No se puede acceder a /home/pi\n"; exit 1; }

printf "📥 Descargando YSFHosts.txt desde mirror oficial...\n"
if wget -O YSFHosts.txt -q https://www.pistar.uk/downloads/YSF_Hosts.txt; then
    printf "✅ Descarga completada con éxito.\n"
else
    printf "❌ Error al descargar el archivo.\n"
    exit 1
fi

# Distribución a YSFGateway
printf "📤 Instalando YSFHosts.txt en YSFGateway...\n"
if sudo mv /home/pi/YSFHosts.txt /home/pi/YSFClients/YSFGateway/YSFHosts.txt; then
    sudo chown pi:pi /home/pi/YSFClients/YSFGateway/YSFHosts.txt
    sudo chmod 644 /home/pi/YSFClients/YSFGateway/YSFHosts.txt
    printf "✅ Archivo instalado en YSFGateway.\n"
else
    printf "❌ Error al mover el archivo a YSFGateway.\n"
    exit 1
fi

# Copia adicional a Fusion2X (usando sudo para escribir en /opt)
printf "📤 Copiando YSFHosts.txt a Fusion2X (/opt)...\n"
if sudo cp /home/pi/YSFClients/YSFGateway/YSFHosts.txt /opt/fusion2x/data/YSFHosts.txt; then
    sudo chown pi:pi /opt/fusion2x/data/YSFHosts.txt
    sudo chmod 644 /opt/fusion2x/data/YSFHosts.txt
    printf "✅ Archivo copiado en /opt/fusion2x/data con permisos correctos.\n"
else
    printf "❌ Error al copiar el archivo a Fusion2X.\n"
    exit 1
fi

# === Generar YSFHosts.json a partir del .txt recién instalado ===
printf "🔄 Convirtiendo YSFHosts.txt a YSFHosts.json...\n"

CONVERT_COUNT=$(python3 - <<'PYEOF'
import json, re, sys
from datetime import datetime, timezone

src = "/home/pi/YSFClients/YSFGateway/YSFHosts.txt"
dst = "/home/pi/YSFHosts.json"

reflectors = []
try:
    with open(src, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            parts = line.split(";")
            if len(parts) < 5:
                continue
            designator, raw_name, description, ipv4, port = parts[0], parts[1], parts[2], parts[3], parts[4]
            user_count = parts[5] if len(parts) > 5 else ""

            if raw_name.startswith("XX-"):
                use_xx_prefix = True
                country = ""
                name = raw_name[3:]
            elif "-" in raw_name:
                country, name = raw_name.split("-", 1)
                use_xx_prefix = False
            else:
                country = ""
                name = raw_name
                use_xx_prefix = False

            slug = re.sub(r"[^a-z0-9]+", "-", f"ysf-{designator}-{name}".lower()).strip("-")

            try:
                port_num = int(port)
            except ValueError:
                port_num = 0

            reflectors.append({
                "designator": designator,
                "name": name,
                "use_xx_prefix": use_xx_prefix,
                "description": description if description else name,
                "slug": slug,
                "url": None,
                "dns": None,
                "ipv4": ipv4 if ipv4 else None,
                "ipv6": None,
                "port": port_num,
                "sponsor": None,
                "country": country,
                "user_count": user_count,
                "ip_source": None,
                "dns_cache_updated_at": None,
                "last_verified_at": None,
                "network_type": None,
                "refcheck_protocol_abuse": False,
                "refcheck_fcs_cross_listed": False
            })

    data = {
        "_local_metadata": {
            "filename": "YSFHosts.json",
            "generated": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC"),
            "generated_by": "update_ysfhosts.sh (conversion local desde YSFHosts.txt)",
            "source_txt": "https://www.pistar.uk/downloads/YSF_Hosts.txt",
            "note": "JSON generado localmente. 'country' y 'name' van separados (YSFGateway reconstruye 'country-name' internamente); 'country' nunca es null, para 'XX-' se deja en cadena vacia."
        },
        "reflectors": reflectors
    }

    with open(dst, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

    print(len(reflectors))
except Exception as e:
    print(f"ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PYEOF
)
CONVERT_STATUS=$?

if [ "$CONVERT_STATUS" -eq 0 ] && [ -f /home/pi/YSFHosts.json ]; then
    printf "✅ YSFHosts.json generado correctamente (%s reflectores).\n" "$CONVERT_COUNT"
else
    printf "❌ Error al convertir YSFHosts.txt a JSON.\n"
    exit 1
fi

printf "📤 Instalando YSFHosts.json en YSFGateway...\n"
if sudo mv /home/pi/YSFHosts.json /home/pi/YSFClients/YSFGateway/YSFHosts.json; then
    sudo chown pi:pi /home/pi/YSFClients/YSFGateway/YSFHosts.json
    sudo chmod 644 /home/pi/YSFClients/YSFGateway/YSFHosts.json
    printf "✅ Archivo instalado en YSFGateway.\n"
else
    printf "❌ Error al mover el archivo a YSFGateway.\n"
    exit 1
fi

printf "📤 Copiando YSFHosts.json a Fusion2X (/opt)...\n"
if sudo cp /home/pi/YSFClients/YSFGateway/YSFHosts.json /opt/fusion2x/data/YSFHosts.json; then
    sudo chown pi:pi /opt/fusion2x/data/YSFHosts.json
    sudo chmod 644 /opt/fusion2x/data/YSFHosts.json
    printf "✅ Archivo copiado en /opt/fusion2x/data con permisos correctos.\n"
else
    printf "❌ Error al copiar el archivo a Fusion2X.\n"
    exit 1
fi

# Finalización
printf "🎉 YSFHosts actualizados correctamente (txt + json).\n"
sleep 3
