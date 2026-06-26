#!/bin/bash

###############################################################################
#
# Copyright (C) 2025 by Jonathan Naylor G4KLX
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#
###############################################################################

NXDNHOSTS=/home/pi/NXDNClients/NXDNGateway/NXDNHosts.json
URL="https://hostfiles.refcheck.radio/NXDNHosts.json"

echo "🔄 ══════════════════════════════════════"
echo "📡  ACTUALIZACIÓN DE REFLECTORES NXDN"
echo "🔄 ══════════════════════════════════════"
echo ""
echo "🌐  Fuente: $URL"
echo "📂  Destino: $NXDNHOSTS"
echo ""

# Hacer copia de seguridad del fichero actual si existe
if [ -f "$NXDNHOSTS" ]; then
    cp "$NXDNHOSTS" "${NXDNHOSTS}.bak" 2>/dev/null
    echo "💾  Copia de seguridad creada: ${NXDNHOSTS}.bak"
fi

echo "⏳  Descargando fichero NXDNHosts.json..."
echo ""

# Descargar el fichero
HTTP_CODE=$(curl --silent --write-out "%{http_code}" -S -L -o "${NXDNHOSTS}" -A "NXDNGateway - G4KLX" "$URL")
CURL_EXIT=$?

echo "🔄 ═════════════════════════════════════════"

if [ $CURL_EXIT -ne 0 ]; then
    echo "❌  ERROR: La descarga ha fallado (código curl: $CURL_EXIT)"
    # Restaurar copia de seguridad si existe
    if [ -f "${NXDNHOSTS}.bak" ]; then
        cp "${NXDNHOSTS}.bak" "$NXDNHOSTS" 2>/dev/null
        echo "🔄  Restaurada copia de seguridad anterior"
    fi
    echo "🔄 ═══════════════════════════════════════════════════════"
    exit 1
fi

if [ "$HTTP_CODE" -ge 400 ] 2>/dev/null; then
    echo "❌  ERROR: El servidor respondió con código HTTP $HTTP_CODE"
    if [ -f "${NXDNHOSTS}.bak" ]; then
        cp "${NXDNHOSTS}.bak" "$NXDNHOSTS" 2>/dev/null
        echo "🔄  Restaurada copia de seguridad anterior"
    fi
    echo "🔄 ═══════════════════════════════════════════════════════"
    exit 1
fi

# Verificar que el fichero existe y no está vacío
if [ ! -f "$NXDNHOSTS" ] || [ ! -s "$NXDNHOSTS" ]; then
    echo "❌  ERROR: El fichero descargado está vacío o no existe"
    if [ -f "${NXDNHOSTS}.bak" ]; then
        cp "${NXDNHOSTS}.bak" "$NXDNHOSTS" 2>/dev/null
        echo "🔄  Restaurada copia de seguridad anterior"
    fi
    echo "🔄 ═══════════════════════════════════════════════════════"
    exit 1
fi

# Obtener información del fichero
FILE_SIZE=$(stat -c%s "$NXDNHOSTS" 2>/dev/null || stat -f%z "$NXDNHOSTS" 2>/dev/null)
FILE_DATE=$(stat -c%y "$NXDNHOSTS" 2>/dev/null | cut -d. -f1 || stat -f"%Sm" "$NXDNHOSTS" 2>/dev/null)
REFLECTOR_COUNT=$(grep -o '"name"' "$NXDNHOSTS" 2>/dev/null | wc -l)

echo "✅  ¡DESCARGA COMPLETADA CON ÉXITO!"
echo ""
echo "📊  INFORMACIÓN DEL FICHERO:"
echo "   📁  Ruta:      $NXDNHOSTS"
echo "   📏  Tamaño:    $FILE_SIZE bytes"
echo "   📅  Fecha:     $FILE_DATE"
echo "   📡  Reflectores detectados: $REFLECTOR_COUNT"
echo ""
echo "🎉  Proceso finalizado correctamente"
echo "🔄 ═══════════════════════════════════════════════════════"

exit 0
