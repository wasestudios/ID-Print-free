#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ID-Server - Servidor local portátil para impresión térmica"""

# Imports consolidados al inicio para mejor rendimiento
import os
import sys
import time
import threading
import subprocess
import socket
import webbrowser
import json
from tkinter import Tk, Label, Button, Toplevel, PhotoImage, messagebox, font, Entry, Frame
from tkinter.scrolledtext import ScrolledText
import psutil
import pystray
from PIL import Image, ImageDraw

# Ruta base portable (soporta PyInstaller)
if getattr(sys, 'frozen', False):
    BASE_DIR = os.path.dirname(sys.executable)
    # Los iconos están empaquetados dentro del ejecutable
    ICONS_PATH = os.path.join(sys._MEIPASS, "icons")
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))
    ICONS_PATH = os.path.join(BASE_DIR, "icons")

# Pre-calcular rutas comunes para evitar join() repetidos
PHP_PATH = os.path.join(BASE_DIR, "php", "php.exe")
CERT_PATH = os.path.join(BASE_DIR, "cert")
CERT_FILE_PATH = os.path.join(CERT_PATH, "certificado.pem")
PUBLIC_PATH = os.path.join(BASE_DIR, "public")
ICON_FILE_PATH = os.path.join(BASE_DIR, "icon.png")
CONFIG_FILE = os.path.join(BASE_DIR, "config.json")
CADDYFILE_PATH = os.path.join(BASE_DIR, "Caddyfile")

# Puertos por defecto
DEFAULT_PORTS = {
    "https": 443,
    "php": 8080
}

# Cache global para evitar chequeos constantes
_ultimo_chequeo = 0
_estado_cacheado = False
_cache_iconos = {}  # Cache global de iconos
_icono_bandeja = None  # Cache del icono de bandeja
_ventana_principal = None  # Ventana singleton
CACHE_DURACION = 2  # segundos

def cargar_config():
    """Carga la configuración de puertos desde config.json"""
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
                config = json.load(f)
                return {
                    "https": config.get("https", DEFAULT_PORTS["https"]),
                    "php": config.get("php", DEFAULT_PORTS["php"])
                }
        except Exception:
            pass
    return DEFAULT_PORTS.copy()

def guardar_config(https_port, php_port):
    """Guarda la configuración de puertos en config.json"""
    try:
        config = {
            "https": https_port,
            "php": php_port
        }
        with open(CONFIG_FILE, 'w', encoding='utf-8') as f:
            json.dump(config, f, indent=4)
        return True
    except Exception as e:
        messagebox.showerror("Error", f"No se pudo guardar la configuración: {e}")
        return False

def actualizar_caddyfile(https_port, php_port):
    """Actualiza el Caddyfile con los puertos configurados"""
    try:
        contenido = f"""# Caddyfile para proxy HTTPS a PHP
:{https_port} {{
    tls cert/certificado.pem cert/llave.pem
    reverse_proxy 127.0.0.1:{php_port}
    encode gzip
    log {{
        output file logs/access.log
    }}
}}
"""
        with open(CADDYFILE_PATH, 'w', encoding='utf-8') as f:
            f.write(contenido)
        return True
    except Exception as e:
        messagebox.showerror("Error", f"No se pudo actualizar Caddyfile: {e}")
        return False

def estado_servidor():
    """Verifica si PHP y Caddy están activos, con caché para optimizar rendimiento"""
    global _ultimo_chequeo, _estado_cacheado
    
    # Si el cache es reciente, retornar valor cacheado
    ahora = time.time()
    if ahora - _ultimo_chequeo < CACHE_DURACION:
        return _estado_cacheado
    
    # Usar set para búsqueda más rápida
    procesos_buscados = {'php.exe', 'caddy.exe'}
    procesos_encontrados = set()
    
    try:
        for proc in psutil.process_iter(['name'], ad_value=None):
            if proc.info and proc.info['name']:
                nombre = proc.info['name'].lower()
                if nombre in procesos_buscados:
                    procesos_encontrados.add(nombre)
                    # Si ya encontramos ambos, salir inmediatamente
                    if len(procesos_encontrados) == 2:
                        break
    except (psutil.NoSuchProcess, psutil.AccessDenied, AttributeError):
        pass
    
    # Actualizar cache
    _ultimo_chequeo = ahora
    _estado_cacheado = len(procesos_encontrados) == 2
    
    return _estado_cacheado

def crear_icono(estado):
    """Crea el icono para la bandeja del sistema con cache"""
    global _icono_bandeja
    
    # Cache del icono base (solo se carga una vez)
    if _icono_bandeja is None and os.path.exists(ICON_FILE_PATH):
        try:
            _icono_bandeja = Image.open(ICON_FILE_PATH)
            return _icono_bandeja
        except Exception:
            pass
    
    if _icono_bandeja:
        return _icono_bandeja
    
    # Fallback: crear icono simple (solo si no existe archivo)
    color = 'green' if estado else 'red'
    imagen = Image.new('RGB', (64, 64), color=color)
    d = ImageDraw.Draw(imagen)
    d.ellipse([16, 16, 48, 48], fill='white')
    return imagen

def actualizar_icono(icon):
    """Actualiza el icono de la bandeja sin bloquear"""
    try:
        estado = estado_servidor()
        icon.icon = crear_icono(estado)
        icon.title = f"ID-Server - {'Activo' if estado else 'Inactivo'}"
    except Exception:
        pass

def iniciar_servidor():
    """Inicia el servidor con los puertos configurados"""
    config = cargar_config()
    https_port = config["https"]
    php_port = config["php"]
    
    # Actualizar Caddyfile con los puertos actuales
    actualizar_caddyfile(https_port, php_port)
    
    # Verificar que PHP existe
    if not os.path.exists(PHP_PATH):
        messagebox.showerror("Error", f"No se encontró PHP en: {PHP_PATH}")
        return
    
    # Iniciar PHP en el puerto configurado
    php_cmd = f'"{PHP_PATH}" -S 0.0.0.0:{php_port} -t "{PUBLIC_PATH}"'
    subprocess.Popen(
        php_cmd,
        shell=True,
        stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
    )
    
    # Pequeña pausa para que PHP inicie
    time.sleep(0.3)
    
    # Iniciar Caddy con el Caddyfile actualizado
    caddy_exe = os.path.join(BASE_DIR, "caddy.exe")
    if os.path.exists(caddy_exe):
        caddy_cmd = f'"{caddy_exe}" run --config "{CADDYFILE_PATH}" --adapter caddyfile'
        subprocess.Popen(
            caddy_cmd,
            shell=True,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
        )

def apagar_servidor():
    """Apaga el servidor mediante script VBS (sin bloqueo)"""
    subprocess.Popen(
        ['start', os.path.join(BASE_DIR, 'detener_servidor.vbs')],
        shell=True,
        stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL
    )

def abrir_cert():
    """Abre la carpeta de certificados"""
    os.startfile(CERT_PATH)

def abrir_configuracion():
    """Abre el navegador con la IP local y puerto configurado"""
    config = cargar_config()
    https_port = config["https"]
    try:
        ip_local = socket.gethostbyname(socket.gethostname())
        url = f"https://{ip_local}:{https_port}" if https_port != 443 else f"https://{ip_local}"
        webbrowser.open(url)
    except Exception:
        url = f"https://localhost:{https_port}" if https_port != 443 else "https://localhost"
        webbrowser.open(url)

def salir(icon):
    """Cierra la aplicación completamente, apagando primero los servidores"""
    global _ventana_principal
    
    # 1. Apagar servidores PHP y Caddy si están activos
    if estado_servidor():
        apagar_servidor()
        # Esperar un poco para que se apaguen correctamente
        time.sleep(1)
    
    # 2. Destruir ventana si existe
    if _ventana_principal is not None:
        try:
            _ventana_principal.quit()
            _ventana_principal.destroy()
        except:
            pass
    
    # 3. Detener icono de bandeja
    icon.stop()

# --- Ventana moderna con Tkinter ---
def mostrar_ventana(icon):
    """Muestra la ventana de gestión del servidor"""
    global _ventana_principal
    
    # Si la ventana ya existe, solo mostrarla
    if _ventana_principal is not None:
        try:
            _ventana_principal.deiconify()  # Mostrar ventana oculta
            _ventana_principal.lift()  # Traer al frente
            _ventana_principal.focus_force()  # Dar foco
            return
        except:
            # Si hubo error, recrear ventana
            _ventana_principal = None
    
    def mostrar_info_php_cert():
        """Genera información de PHP y certificados (lazy loading)"""
        # Crear ventana inmediatamente
        win = Toplevel(ventana)
        win.title("Información PHP y Certificados")
        win.geometry("600x500")
        win.transient(ventana)  # Modal ligero
        txt = ScrolledText(win, wrap="word", font=("Segoe UI", 10))
        txt.insert("end", "Cargando información...\n")
        txt.pack(expand=True, fill="both", padx=10, pady=10)
        win.update_idletasks()  # Más eficiente que update()
        
        # Cargar info en background
        def cargar_info():
            info = ""
            # Info PHP
            if os.path.exists(PHP_PATH):
                try:
                    version = subprocess.check_output(
                        [PHP_PATH, "-v"],
                        universal_newlines=True,
                        stderr=subprocess.STDOUT,
                        timeout=3,
                        creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
                    )
                    info += f"PHP versión:\n{version}\n"
                    
                    mods = subprocess.check_output(
                        [PHP_PATH, "-m"],
                        universal_newlines=True,
                        stderr=subprocess.STDOUT,
                        timeout=3,
                        creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
                    )
                    info += f"Extensiones habilitadas:\n{mods}\n"
                    
                    phpini = subprocess.check_output(
                        [PHP_PATH, "--ini"],
                        universal_newlines=True,
                        stderr=subprocess.STDOUT,
                        timeout=3,
                        creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
                    )
                    info += f"\nArchivos de configuración:\n{phpini}\n"
                except subprocess.TimeoutExpired:
                    info += "Error: PHP tardó demasiado en responder\n"
                except Exception as e:
                    info += f"Error obteniendo información de PHP: {e}\n"
            else:
                info += "No se encontró php.exe en la carpeta php.\n"

            # Info Certificado
            if os.path.exists(CERT_FILE_PATH):
                try:
                    try:
                        from cryptography import x509
                        from cryptography.hazmat.backends import default_backend
                        with open(CERT_FILE_PATH, "rb") as f:
                            cert_data = f.read()
                            cert = x509.load_pem_x509_certificate(cert_data, default_backend())
                            info += f"\nCertificado digital:\nEmisor: {cert.issuer.rfc4514_string()}\n"
                            info += f"Válido desde: {cert.not_valid_before_utc}\n"
                            info += f"Válido hasta: {cert.not_valid_after_utc}\n"
                            # Mostrar SAN (Subject Alternative Name)
                            try:
                                ext = cert.extensions.get_extension_for_class(x509.SubjectAlternativeName)
                                sans = ext.value.get_values_for_type(x509.DNSName) + ext.value.get_values_for_type(x509.IPAddress)
                                if sans:
                                    info += "Dominios/IPs válidos (SAN):\n  " + ", ".join(str(s) for s in sans) + "\n"
                            except Exception:
                                info += "No se encontró información SAN en el certificado.\n"
                    except ImportError:
                        info += "\n[cryptography no instalado: no se puede mostrar info detallada del certificado]"
                except Exception as e:
                    info += f"\nError leyendo certificado: {e}"
            else:
                info += "\nNo se encontró certificado.pem en la carpeta cert."
            
            # Actualizar la ventana con la info
            try:
                txt.config(state="normal")
                txt.delete("1.0", "end")
                txt.insert("end", info)
                txt.config(state="disabled")
            except:
                pass
        
        # Ejecutar en thread separado
        threading.Thread(target=cargar_info, daemon=True).start()
    
    # Tooltip para botones (optimizado con __slots__)
    class ToolTip:
        __slots__ = ('widget', 'text', 'tipwindow')
        
        def __init__(self, widget, text):
            self.widget = widget
            self.text = text
            self.tipwindow = None
            widget.bind("<Enter>", self.show_tip, add='+')
            widget.bind("<Leave>", self.hide_tip, add='+')

        def show_tip(self, event=None):
            if self.tipwindow or not self.text:
                return
            x = self.widget.winfo_rootx() + 40
            y = self.widget.winfo_rooty() + 20
            self.tipwindow = tw = Toplevel(self.widget)
            tw.wm_overrideredirect(True)
            tw.wm_geometry(f"+{x}+{y}")
            tw.wm_attributes("-topmost", True)
            label = Label(tw, text=self.text, justify="left",
                         background="#ffffe0", relief="solid", borderwidth=1,
                         font=("Segoe UI", 9), padx=4, pady=2)
            label.pack()

        def hide_tip(self, event=None):
            if self.tipwindow:
                self.tipwindow.destroy()
                self.tipwindow = None

    def actualizar_estado():
        """Actualiza el estado del servidor cada 3 segundos (optimizado)"""
        try:
            estado = estado_servidor()
            estado_texto = "Activo" if estado else "Inactivo"
            estado_color = "#27ae60" if estado else "#e74c3c"
            estado_label.config(text=f"Estado del servidor: {estado_texto}", fg=estado_color)
            actualizar_icono(icon)
            
            # Habilitar/deshabilitar botones según estado
            if estado:
                btn_iniciar.config(state="disabled")
                btn_apagar.config(state="normal")
            else:
                btn_iniciar.config(state="normal")
                btn_apagar.config(state="disabled")
        except Exception:
            pass
        
        # Actualizar cada 3 segundos (antes era 1 segundo)
        ventana.after(3000, actualizar_estado)

    def on_iniciar():
        """Inicia el servidor y fuerza actualización inmediata"""
        global _ultimo_chequeo
        iniciar_servidor()
        _ultimo_chequeo = 0  # Invalidar cache
        ventana.after(400, actualizar_estado)

    def on_apagar():
        """Apaga el servidor y fuerza actualización inmediata"""
        global _ultimo_chequeo
        apagar_servidor()
        _ultimo_chequeo = 0  # Invalidar cache
        ventana.after(400, actualizar_estado)

    def on_cert():
        abrir_cert()

    def on_config():
        abrir_configuracion()

    def on_salir():
        """Cierra la aplicación completamente"""
        salir(icon)

    def on_public():
        """Abre la carpeta public"""
        if os.path.exists(PUBLIC_PATH):
            os.startfile(PUBLIC_PATH)
        else:
            messagebox.showerror("Error", "No se encontró la carpeta public.")
    
    def configurar_puertos():
        """Abre ventana para configurar puertos HTTPS y PHP"""
        config_actual = cargar_config()
        
        # Crear ventana de configuración
        win = Toplevel(ventana)
        win.title("Configurar Puertos")
        win.geometry("420x260")
        win.transient(ventana)
        win.resizable(False, False)
        win.configure(bg="white")
        
        # Centrar ventana
        win.update_idletasks()
        x = ventana.winfo_x() + (ventana.winfo_width() // 2) - (210)
        y = ventana.winfo_y() + (ventana.winfo_height() // 2) - (130)
        win.geometry(f"+{x}+{y}")
        
        # Frame principal con más padding
        main_frame = Frame(win, bg="white", padx=30, pady=25)
        main_frame.pack(fill="both", expand=True)
        
        # Título
        Label(main_frame, text="Configuración de Puertos", 
              font=("Segoe UI", 13, "bold"), bg="white").grid(row=0, column=0, columnspan=2, pady=(0, 20))
        
        # Puerto HTTPS
        Label(main_frame, text="Puerto HTTPS (Caddy):", 
              font=("Segoe UI", 10), bg="white", anchor="w").grid(row=1, column=0, sticky="w", pady=8)
        entry_https = Entry(main_frame, font=("Segoe UI", 11), width=12, justify="center")
        entry_https.insert(0, str(config_actual["https"]))
        entry_https.grid(row=1, column=1, sticky="e", pady=8, padx=(10, 0))
        
        # Puerto PHP
        Label(main_frame, text="Puerto PHP (Backend):", 
              font=("Segoe UI", 10), bg="white", anchor="w").grid(row=2, column=0, sticky="w", pady=8)
        entry_php = Entry(main_frame, font=("Segoe UI", 11), width=12, justify="center")
        entry_php.insert(0, str(config_actual["php"]))
        entry_php.grid(row=2, column=1, sticky="e", pady=8, padx=(10, 0))
        
        # Nota informativa con más espacio
        nota = Label(main_frame, 
                     text="Nota: Reinicie el servidor para aplicar cambios.\nPuertos comunes libres: 8443, 9000, 3000, 5000",
                     font=("Segoe UI", 8), bg="white", fg="#666", justify="center")
        nota.grid(row=3, column=0, columnspan=2, pady=(20, 15))
        
        def guardar_y_cerrar():
            try:
                https_port = int(entry_https.get().strip())
                php_port = int(entry_php.get().strip())
                
                # Validar rangos
                if not (1 <= https_port <= 65535) or not (1 <= php_port <= 65535):
                    messagebox.showerror("Error", "Los puertos deben estar entre 1 y 65535", parent=win)
                    return
                
                if https_port == php_port:
                    messagebox.showerror("Error", "Los puertos no pueden ser iguales", parent=win)
                    return
                
                # Guardar configuración
                if guardar_config(https_port, php_port):
                    # Actualizar Caddyfile
                    actualizar_caddyfile(https_port, php_port)
                    messagebox.showinfo("Éxito", 
                                      f"Configuración guardada:\n\n" +
                                      f"Puerto HTTPS: {https_port}\n" +
                                      f"Puerto PHP: {php_port}\n\n" +
                                      f"Reinicie el servidor para aplicar cambios.",
                                      parent=win)
                    win.destroy()
            except ValueError:
                messagebox.showerror("Error", "Por favor ingrese números válidos", parent=win)
        
        # Botones
        btn_frame = Frame(main_frame, bg="white")
        btn_frame.grid(row=4, column=0, columnspan=2, pady=(10, 0))
        
        btn_guardar = Button(btn_frame, text="Guardar", command=guardar_y_cerrar,
                            bg="#27ae60", fg="white", font=("Segoe UI", 10, "bold"),
                            cursor="hand2", relief="flat", padx=20, pady=5)
        btn_guardar.pack(side="left", padx=5)
        
        btn_cancelar = Button(btn_frame, text="Cancelar", command=win.destroy,
                             bg="#e74c3c", fg="white", font=("Segoe UI", 10, "bold"),
                             cursor="hand2", relief="flat", padx=20, pady=5)
        btn_cancelar.pack(side="left", padx=5)

    # Crear ventana principal (optimizada)
    ventana = Tk()
    _ventana_principal = ventana  # Guardar referencia global
    ventana.title("ID-Server")
    ventana.configure(bg="white")
    ventana.resizable(False, False)
    ventana.geometry("450x500")  # Reducido un poco para botones más pequeños
    ventana.protocol("WM_DELETE_WINDOW", lambda: ventana.withdraw())  # Minimizar en lugar de cerrar
    
    # Cargar icono de ventana (con cache)
    try:
        if os.path.exists(ICON_FILE_PATH):
            ventana.iconphoto(True, PhotoImage(file=ICON_FILE_PATH))
    except Exception:
        pass

    fuente = font.Font(family="Segoe UI", size=12)
    estado_label = Label(ventana, text="", font=fuente, bg="white")
    estado_label.grid(row=0, column=0, columnspan=3, pady=(15, 15), sticky="ew")

    # Cargar iconos con cache global
    def cargar_icono_icons(nombre):
        """Carga iconos desde /icons con cache global"""
        global _cache_iconos
        
        if nombre in _cache_iconos:
            return _cache_iconos[nombre]
        
        ruta = os.path.join(ICONS_PATH, nombre)
        if os.path.exists(ruta):
            try:
                from PIL import Image as PILImage, ImageTk
                img = PILImage.open(ruta)
                if img.mode != "RGBA":
                    img = img.convert("RGBA")
                img = img.resize((36, 36), PILImage.LANCZOS)  # Iconos más pequeños
                icono = ImageTk.PhotoImage(img)
                _cache_iconos[nombre] = icono
                return icono
            except Exception:
                pass
        return None

    iconos = {
        "iniciar": cargar_icono_icons("play.png"),
        "apagar": cargar_icono_icons("stop.png"),
        "cert": cargar_icono_icons("cert.png"),
        "config": cargar_icono_icons("cog.png"),
        "public": cargar_icono_icons("public.png"),
        "info": cargar_icono_icons("server.png"),
        "puertos": cargar_icono_icons("port.png"),
        "salir": cargar_icono_icons("out.png")
    }

    # Crear botones optimizados (matriz uniforme con tamaño reducido)
    def crear_boton(texto, comando, icono, fila, columna):
        """Crea un botón con tooltip optimizado"""
        btn = Button(
            ventana,
            image=icono,
            command=comando,
            width=52,
            height=52,
            bg="#f8f8f8",
            relief="flat",
            bd=0,
            highlightthickness=2,
            highlightbackground="#bdbdbd",
            highlightcolor="#3498db",
            cursor="hand2",
            activebackground="#e0e0e0",
            takefocus=0
        )
        btn.grid(row=fila, column=columna, padx=8, pady=8, sticky="nsew")
        ToolTip(btn, texto)
        return btn

    # Configurar grid (matriz 3x3 uniforme)
    for i in range(1, 4):  # Filas 1, 2, 3
        ventana.grid_rowconfigure(i, weight=1, uniform="buttons")
    for i in range(3):  # Columnas 0, 1, 2
        ventana.grid_columnconfigure(i, weight=1, uniform="buttons")

    # Crear botones en matriz ordenada 3x3
    # Fila 1
    btn_iniciar = crear_boton("Iniciar servidor", on_iniciar, iconos["iniciar"], 1, 0)
    btn_apagar = crear_boton("Apagar servidor", on_apagar, iconos["apagar"], 1, 1)
    btn_puertos = crear_boton("Configurar puertos", configurar_puertos, iconos["puertos"], 1, 2)
    
    # Fila 2
    btn_config = crear_boton("Abrir aplicación", on_config, iconos["config"], 2, 0)
    btn_public = crear_boton("Carpeta public", on_public, iconos["public"], 2, 1)
    btn_cert = crear_boton("Carpeta certificados", on_cert, iconos["cert"], 2, 2)
    
    # Fila 3
    btn_info = crear_boton("Info PHP/Certificados", mostrar_info_php_cert, iconos["info"], 3, 0)
    btn_salir = crear_boton("Salir", on_salir, iconos["salir"], 3, 2)

    # Iniciar actualización de estado
    actualizar_estado()
    ventana.mainloop()

# --- pystray: icono en bandeja del sistema ---
def on_clicked(icon, item):
    """Abre la ventana al hacer click en el icono"""
    threading.Thread(target=mostrar_ventana, args=(icon,), daemon=True).start()

# Crear icono de bandeja
icon = pystray.Icon("I-PRINT", crear_icono(estado_servidor()), "I-PRINT")
icon.menu = pystray.Menu(
    pystray.MenuItem('ID-SERVER', on_clicked),
    pystray.MenuItem('SALIR', lambda i, j: salir(icon))
)

# Mostrar ventana automáticamente al iniciar
threading.Thread(target=mostrar_ventana, args=(icon,), daemon=True).start()
icon.run()