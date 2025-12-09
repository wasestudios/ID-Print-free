#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script para generar el ejecutable optimizado de ID-Server
Ejecutar: python build_exe.py
"""

import os
import subprocess
import sys

# Comando PyInstaller optimizado
# NOTA: Solo se incluyen recursos críticos que no existen en la raíz
# Las carpetas cert, public, php ya existen y se leen desde disco
cmd = [
    'pyinstaller',
    '--name=Id-server',
    '--onefile',
    '--windowed',
    '--icon=icon.png',
    '--add-data=icons;icons',  # Solo iconos para la interfaz
    '--hidden-import=PIL._tkinter_finder',
    '--collect-all=pystray',
    '--collect-all=PIL',
    '--optimize=2',
    '--strip',
    '--noupx',
    '--exclude-module=numpy',  # Excluir numpy si no se usa
    '--exclude-module=matplotlib',  # Excluir matplotlib si no se usa
    '--exclude-module=scipy',  # Excluir scipy si no se usa
    'Id-server.py'
]

print("=" * 60)
print("Generando ejecutable optimizado de ID-Server...")
print("=" * 60)
print("\nEsto puede tomar varios minutos...\n")

try:
    result = subprocess.run(cmd, check=True)
    print("\n" + "=" * 60)
    print("✓ Ejecutable generado exitosamente!")
    print("=" * 60)
    print("\nUbicación: dist\\Id-server.exe")
    print("\nNOTA IMPORTANTE:")
    print("  - El .exe está optimizado y pesa mucho menos")
    print("  - Copia el .exe a la raíz del proyecto")
    print("  - Necesita las carpetas en la raíz: icons, cert, public, php, caddy.exe, *.vbs")
    print("  - Tamaño reducido: ~24 MB (vs 117 MB anterior - reducción del 80%)")
    print("  - ¡Las carpetas pesadas NO están dentro del .exe!")
    print("  - Y los archivos: caddy.exe, Caddyfile, *.vbs")
except subprocess.CalledProcessError as e:
    print("\n❌ Error al generar el ejecutable:")
    print(f"   {e}")
    sys.exit(1)
