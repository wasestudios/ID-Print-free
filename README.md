# ID-Server (Servidor Local)

ID-Server es un servidor local portátil para Windows que integra PHP y Caddy para ofrecer un entorno de backend y API REST, ideal para aplicaciones de impresión y gestión local.

## Componentes principales

- **PHP**: Motor de backend y ejecución de scripts.
- **Caddy**: Servidor web moderno, seguro y fácil de configurar.
- **Python**: Interfaz gráfica de gestión y automatización.

## Estructura recomendada

- `Id-server.exe` — Ejecutable principal (generado con PyInstaller)
- `php/` — Carpeta con binarios y configuración de PHP
- `caddy.exe` — Servidor web Caddy
- `icons/` — Iconos para la interfaz
- `public/` — Carpeta pública para archivos y frontend (debe estar junto al .exe)
- `cert/` — Certificados SSL (debe estar junto al .exe)
- `iniciar_servidor.vbs` y `detener_servidor.vbs` — Scripts para iniciar/detener el servidor

## Uso

1. Coloca el ejecutable y las carpetas necesarias (`php`, `caddy.exe`, `icons`, `public`, `cert`, `.vbs`) en la misma carpeta.
2. Ejecuta `Id-server.exe`.
3. La interfaz gráfica permite iniciar/detener el servidor, abrir la carpeta pública, ver información de PHP y certificados, y más.
4. El servidor web estará disponible en `https://localhost` o en la IP local.

## Requisitos

- Windows 10/11
- No requiere instalación, es portable.
- El usuario debe tener permisos para ejecutar scripts `.vbs` y abrir puertos locales.

## Licencias

- PHP: [PHP License v3.01](https://www.php.net/license/3_01.txt)
- Caddy: [Apache License 2.0](https://github.com/caddyserver/caddy/blob/master/LICENSE)

Consulta el archivo LICENSE para más detalles.

---

## Recomendación para Descargar y Usar la Aplicación

Se recomienda descargar la aplicación en formato **ZIP** y descomprimirla en una carpeta específica de su elección. Esto ayuda a evitar problemas relacionados con la seguridad de los archivos en Windows.

### Posibles Problemas y Soluciones

1. **Archivos Bloqueados por Windows**:
   - Windows puede marcar algunos archivos como inseguros al descargarlos de Internet, lo que puede impedir su correcto funcionamiento.
   - Si experimenta problemas, desbloquee los siguientes archivos manualmente:
     - `caddy.exe`
     - `iniciar_servidor.vbs`
     - `detener_servidor.vbs`
   - Para desbloquear un archivo:
     1. Haga clic derecho en el archivo y seleccione **Propiedades**.
     2. En la parte inferior de la ventana, marque la opción **Desbloquear** (si está disponible) y haga clic en **Aceptar**.

2. **Ejecutar como Administrador**:
   - Asegúrese de ejecutar los scripts (`iniciar_servidor.vbs` y `detener_servidor.vbs`) con permisos de administrador para evitar restricciones del sistema.

Si sigue estas recomendaciones, la aplicación debería funcionar correctamente después de ser descargada.

---

Para soporte o licencias comerciales, contacta a: willyruiz95@gmail.com
