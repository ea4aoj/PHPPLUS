#!/bin/bash

# ============================================
# Script: reset_phpplus.sh
# Descripción: Intenta git pull, si falla borra y re-clona
# Autor: EA4AOJ
# Fecha: 2026-06-27
# ============================================

# Configuración
REPO_URL="http://github.com/ea4aoj/PHPPLUS"
REPO_NAME="PHPPLUS"
DEST_DIR="/home/pi"
REPO_PATH="$DEST_DIR/$REPO_NAME"

# Colores para los mensajes
ROJO='\033[0;31m'
VERDE='\033[0;32m'
AMARILLO='\033[1;33m'
AZUL='\033[0;34m'
NC='\033[0m' # Sin color

echo -e "${AZUL}========================================${NC}"
echo -e "${AZUL}  Actualizador de repositorio PHPPLUS   ${NC}"
echo -e "${AZUL}========================================${NC}"
echo ""

# 1. Verificar que git está instalado
if ! command -v git &> /dev/null; then
    echo -e "${ROJO}❌ Error: git no está instalado${NC}"
    echo "Instálalo con: sudo apt install git"
    exit 1
fi

# 2. Verificar que el directorio destino existe
if [ ! -d "$DEST_DIR" ]; then
    echo -e "${ROJO}❌ Error: El directorio $DEST_DIR no existe${NC}"
    exit 1
fi

# 3. Si la carpeta existe, intentar git pull
if [ -d "$REPO_PATH" ]; then
    echo -e "${AZUL}📥 Intentando actualizar con git pull...${NC}"
    cd "$REPO_PATH" || exit 1
    
    if git pull; then
        echo ""
        echo -e "${VERDE}========================================${NC}"
        echo -e "${VERDE}✅ Repositorio actualizado correctamente${NC}"
        echo -e "${VERDE}📂 Ubicación: $REPO_PATH${NC}"
        echo -e "${VERDE}========================================${NC}"
        exit 0
    else
        echo ""
        echo -e "${AMARILLO}⚠️  git pull falló, procediendo a re-clonar...${NC}"
        cd "$DEST_DIR" || exit 1
        echo -e "${AMARILLO}🗑️  Borrando carpeta: $REPO_PATH${NC}"
        rm -rf "$REPO_NAME"
    fi
else
    echo -e "${AMARILLO}ℹ️  La carpeta $REPO_NAME no existe, clonando desde cero...${NC}"
fi

# 4. Clonar el repositorio (solo si falló git pull o no existía)
cd "$DEST_DIR" || exit 1
echo -e "${AZUL}📥 Clonando desde $REPO_URL ...${NC}"
git clone "$REPO_URL"

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${VERDE}========================================${NC}"
    echo -e "${VERDE}✅ Repositorio clonado correctamente${NC}"
    echo -e "${VERDE}📂 Ubicación: $REPO_PATH${NC}"
    echo -e "${VERDE}========================================${NC}"
else
    echo ""
    echo -e "${ROJO}========================================${NC}"
    echo -e "${ROJO}❌ Error al clonar el repositorio${NC}"
    echo -e "${ROJO}Verifica tu conexión a internet${NC}"
    echo -e "${ROJO}========================================${NC}"
    exit 1
fi
