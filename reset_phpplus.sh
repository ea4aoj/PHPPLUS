#!/bin/bash

# ============================================
# Script: reset_phpplus.sh
# Descripción: Intenta git pull, si falla borra con sudo y re-clona
# ============================================

# Configuración
REPO_URL="http://github.com/ea4aoj/PHPPLUS"
REPO_NAME="PHPPLUS"
DEST_DIR="/home/pi"
REPO_PATH="$DEST_DIR/$REPO_NAME"

echo "========================================"
echo "  Actualizador de repositorio PHPPLUS   "
echo "========================================"
echo ""

# Si la carpeta existe, intentar git pull
if [ -d "$REPO_PATH" ]; then
    echo "📥 Intentando actualizar con git pull..."
    cd "$REPO_PATH" || exit 1
    
    if git pull; then
        echo ""
        echo "========================================"
        echo "✅ Repositorio actualizado correctamente"
        echo "📂 Ubicación: $REPO_PATH"
        echo "========================================"
        exit 0
    else
        echo ""
        echo "⚠️  git pull falló, procediendo a re-clonar..."
        cd "$DEST_DIR" || exit 1
        echo "🗑️  Borrando carpeta con sudo: $REPO_PATH"
        sudo rm -rf "$REPO_NAME"
    fi
else
    echo "ℹ️  La carpeta $REPO_NAME no existe, clonando desde cero..."
fi

# Clonar el repositorio (solo si falló git pull o no existía)
cd "$DEST_DIR" || exit 1
echo "📥 Clonando desde $REPO_URL ..."
git clone "$REPO_URL"

if [ $? -eq 0 ]; then
    echo ""
    echo "========================================"
    echo "✅ Repositorio clonado correctamente"
    echo "📂 Ubicación: $REPO_PATH"
    echo "========================================"
else
    echo ""
    echo "========================================"
    echo "❌ Error al clonar el repositorio"
    echo "========================================"
    exit 1
fi
