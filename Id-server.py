

# --- NUEVO: Interfaz moderna con Tkinter ---
import pystray
from PIL import Image, ImageDraw
import subprocess
import os
import sys

# Ruta base portable (soporta PyInstaller)
if getattr(sys, 'frozen', False):
    BASE_DIR = os.path.dirname(sys.executable)
else:
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))
import psutil  # Requiere: pip install psutil
import threading

def estado_servidor():
    procesos = [p.name().lower() for p in psutil.process_iter(['name'])]
    php_activo = any('php.exe' == p for p in procesos)
    caddy_activo = any('caddy.exe' == p for p in procesos)
    return php_activo and caddy_activo

def crear_icono(estado):
    icon_path = os.path.join(BASE_DIR, "icon.png")
    if os.path.exists(icon_path):
        try:
            return Image.open(icon_path)
        except Exception:
            pass
    color = 'green' if estado else 'red'
    imagen = Image.new('RGB', (64, 64), color=color)
    d = ImageDraw.Draw(imagen)
    d.ellipse([16, 16, 48, 48], fill='white')
    return imagen

def actualizar_icono(icon):
    icon.icon = crear_icono(estado_servidor())
    icon.title = f"ID-Server - {'Activo' if estado_servidor() else 'Inactivo'}"

def iniciar_servidor():
    subprocess.Popen(['start', os.path.join(BASE_DIR, 'iniciar_servidor.vbs')], shell=True)
    import time; time.sleep(1)

def apagar_servidor():
    subprocess.Popen(['start', os.path.join(BASE_DIR, 'detener_servidor.vbs')], shell=True)
    import time; time.sleep(1)

def abrir_cert():
    os.startfile(os.path.join(BASE_DIR, 'cert'))

def abrir_configuracion():
    import socket
    import webbrowser
    ip_local = socket.gethostbyname(socket.gethostname())
    url = f"https://{ip_local}"
    webbrowser.open(url)

def salir(icon):
    icon.stop()

# --- Ventana moderna con Tkinter ---
def mostrar_ventana(icon):

    def mostrar_info_php_cert():
        import tkinter as tk
        from tkinter import scrolledtext
        import subprocess
        import os
        info = ""
        # Info PHP
        php_path = os.path.join(BASE_DIR, "php", "php.exe")
        if os.path.exists(php_path):
            try:
                version = subprocess.check_output([php_path, "-v"], universal_newlines=True, stderr=subprocess.STDOUT)
                info += f"PHP versión:\n{version}\n"
                mods = subprocess.check_output([php_path, "-m"], universal_newlines=True, stderr=subprocess.STDOUT)
                info += f"Extensiones habilitadas:\n{mods}\n"
                ini = subprocess.check_output([php_path, "-i"], universal_newlines=True, stderr=subprocess.STDOUT)
                for line in ini.splitlines():
                    if line.lower().startswith("loaded configuration file"):
                        info += line + "\n"
            except Exception as e:
                info += f"Error obteniendo información de PHP: {e}\n"
        else:
            info += "No se encontró php.exe en la carpeta php.\n"

        # Info Certificado
        cert_path = os.path.join(BASE_DIR, "cert", "certificado.pem")
        if os.path.exists(cert_path):
            try:
                try:
                    from cryptography import x509
                    from cryptography.hazmat.backends import default_backend
                    with open(cert_path, "rb") as f:
                        cert_data = f.read()
                        cert = x509.load_pem_x509_certificate(cert_data, default_backend())
                        info += f"\nCertificado digital:\nEmisor: {cert.issuer.rfc4514_string()}\nVálido desde: {cert.not_valid_before_utc}\nVálido hasta: {cert.not_valid_after_utc}\n"
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

        win = tk.Toplevel(ventana)
        win.title("Información PHP y Certificados")
        win.geometry("600x500")
        txt = scrolledtext.ScrolledText(win, wrap=tk.WORD, font=("Segoe UI", 10))
        txt.insert(tk.END, info)
        txt.config(state="disabled")
        txt.pack(expand=True, fill="both", padx=10, pady=10)
    # Tooltip para botones (simple)
    class ToolTip:
        def __init__(self, widget, text):
            self.widget = widget
            self.text = text
            self.tipwindow = None
            widget.bind("<Enter>", self.show_tip)
            widget.bind("<Leave>", self.hide_tip)

        def show_tip(self, event=None):
            if self.tipwindow or not self.text:
                return
            x, y, cx, cy = self.widget.bbox("insert") if self.widget.winfo_ismapped() else (0,0,0,0)
            x = x + self.widget.winfo_rootx() + 40
            y = y + self.widget.winfo_rooty() + 20
            self.tipwindow = tw = tk.Toplevel(self.widget)
            tw.wm_overrideredirect(True)
            tw.wm_geometry(f"+{x}+{y}")
            label = tk.Label(tw, text=self.text, justify=tk.LEFT,
                             background="#ffffe0", relief=tk.SOLID, borderwidth=1,
                             font=("Segoe UI", 10))
            label.pack(ipadx=6, ipady=2)

        def hide_tip(self, event=None):
            tw = self.tipwindow
            self.tipwindow = None
            if tw:
                tw.destroy()
    import tkinter as tk

    from tkinter import font, messagebox, PhotoImage, filedialog
    import sys
    import glob


    # ... (creación de botones aquí) ...

    def actualizar_estado():
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
        # Llamar de nuevo después de 1 segundo
        ventana.after(1000, actualizar_estado)

    def on_iniciar():
        iniciar_servidor()
        ventana.after(700, actualizar_estado)

    def on_apagar():
        apagar_servidor()
        ventana.after(700, actualizar_estado)

    def on_cert():
        abrir_cert()

    def on_config():
        abrir_configuracion()

    def on_salir():
        salir(icon)
        ventana.destroy()
        sys.exit()

    def on_public():
        # Abre la carpeta public
        public_path = os.path.join(BASE_DIR, "public")
        if os.path.exists(public_path):
            os.startfile(public_path)
        else:
            messagebox.showerror("Error", "No se encontró la carpeta public.")

    ventana = tk.Tk()
    ventana.title("ID-Server")
    ventana.configure(bg="white")
    ventana.resizable(False, False)
    ventana.geometry("400x400")
    try:
        icon_path = os.path.join(os.path.dirname(sys.argv[0]), "icon.png")
        ventana.iconphoto(True, tk.PhotoImage(file=icon_path))
    except Exception:
        pass

    fuente = font.Font(family="Segoe UI", size=12)
    fuente_boton = font.Font(family="Segoe UI", size=11, weight="bold")

    estado_label = tk.Label(ventana, text="", font=fuente, bg="white")
    estado_label.grid(row=0, column=0, columnspan=3, pady=(20, 10))

    # Cargar iconos si existen
    def cargar_icono(nombre, fallback=None):
        ruta = os.path.join(BASE_DIR, nombre)
        if os.path.exists(ruta):
            try:
                return PhotoImage(file=ruta)
            except Exception:
                pass
        return fallback


    def cargar_icono_icons(nombre, fallback=None):
        ruta = os.path.join(BASE_DIR, "icons", nombre)
        if os.path.exists(ruta):
            try:
                from PIL import Image, ImageTk
                img = Image.open(ruta).convert("RGBA")
                img = img.resize((32, 32), Image.LANCZOS)
                return ImageTk.PhotoImage(img)
            except Exception:
                pass
        return fallback

    iconos = {
        "iniciar": cargar_icono_icons("play.png"),
        "apagar": cargar_icono_icons("stop.png"),
        "cert": cargar_icono_icons("cert.png"),
        "config": cargar_icono_icons("cog.png"),
        "public": cargar_icono_icons("public.png"),
        "auto": cargar_icono_icons("win.png"),
        "info": cargar_icono_icons("server.png"),
        "salir": cargar_icono_icons("out.png")
    }

    # Crear botones con iconos y bordes redondeados
    def crear_boton(texto, comando, icono=None, fila=0, columna=0):
        btn = tk.Button(
            ventana,
            image=icono,
            command=comando,
            width=48,
            height=48,
            bg="#f8f8f8",
            relief="flat",
            bd=0,
            highlightthickness=0,
            cursor="hand2"
        )
        btn.configure(highlightbackground="#bdbdbd", highlightcolor="#bdbdbd", highlightthickness=2)
        btn.grid(row=fila, column=columna, padx=10, pady=10, sticky="nsew")
        ToolTip(btn, texto)
        return btn

    # RECOMENDACIÓN PARA ICONOS:
    # Puedes descargar iconos PNG de FontAwesome, Material Icons o icons8.com.
    # Guarda los PNG en la misma carpeta que id-server.py con nombres como icon_iniciar.png, icon_apagar.png, etc.
    # Si tienes SVG, conviértelos a PNG (por ejemplo, usando https://cloudconvert.com/svg-to-png).
    # Así, los iconos se cargarán automáticamente si existen.

    ventana.grid_rowconfigure(1, weight=1)
    ventana.grid_rowconfigure(2, weight=1)
    ventana.grid_rowconfigure(3, weight=1)
    ventana.grid_columnconfigure(0, weight=1)
    ventana.grid_columnconfigure(1, weight=1)
    ventana.grid_columnconfigure(2, weight=1)

    btn_iniciar = crear_boton("Iniciar id-server", on_iniciar, iconos["iniciar"], fila=1, columna=0)
    btn_apagar = crear_boton("Apagar id-server", on_apagar, iconos["apagar"], fila=1, columna=1)
    btn_cert = crear_boton("Abrir cert", on_cert, iconos["cert"], fila=1, columna=2)
    btn_config = crear_boton("Configurar app", on_config, iconos["config"], fila=2, columna=0)
    btn_info = crear_boton("Info PHP/Cert", mostrar_info_php_cert, iconos["info"], fila=2, columna=2)
    btn_public = crear_boton("Abrir public", on_public, iconos["public"], fila=2, columna=1)
    btn_salir = crear_boton("Salir", on_salir, iconos["salir"], fila=3, columna=2)

    actualizar_estado()
    ventana.mainloop()

# --- pystray: solo icono, click abre ventana ---
def on_clicked(icon, item):
    threading.Thread(target=mostrar_ventana, args=(icon,), daemon=True).start()


icon = pystray.Icon("I-PRINT", crear_icono(estado_servidor()), "I-PRINT")
icon.menu = pystray.Menu(
    pystray.MenuItem('ID-SERVER', on_clicked),
    pystray.MenuItem('SALIR', lambda i, j: salir(icon))
)
# Mostrar ventana gestor automáticamente al iniciar
threading.Thread(target=mostrar_ventana, args=(icon,), daemon=True).start()
icon.run()