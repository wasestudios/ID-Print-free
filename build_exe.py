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
cmd = [
    'pyinstaller',
    '--name=Id-server',
    '--onefile',
    '--windowed',
    '--icon=icon.png',
    '--add-data=icons;icons',
    '--add-data=cert;cert',
    '--add-data=public;public',
    '--add-data=php;php',
    '--add-data=Caddyfile;.',
    '--add-data=caddy.exe;.',
    '--add-data=iniciar_servidor.vbs;.',
    '--add-data=detener_servidor.vbs;.',
    '--hidden-import=PIL._tkinter_finder',
    '--collect-all=pystray',
    '--collect-all=PIL',
    '--optimize=2',
    '--strip',
    '--noupx',
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
    print("  - El .exe está en la carpeta 'dist'")
    print("  - Copia el .exe a la raíz del proyecto")
    print("  - El .exe necesita las carpetas: icons, cert, public, php")
    print("  - Y los archivos: caddy.exe, Caddyfile, *.vbs")
except subprocess.CalledProcessError as e:
    print("\n❌ Error al generar el ejecutable:")
    print(f"   {e}")
    sys.exit(1)
