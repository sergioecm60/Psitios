# Psitios - Panel de Gestión Segura y Jerárquica

Un panel de control seguro desarrollado en PHP y MySQL para gestionar el acceso de múltiples usuarios a diferentes sitios o servicios web. El sistema está diseñado con una arquitectura jerárquica de roles (`SuperAdmin`, `Admin`, `Usuario`) que permite un aislamiento de datos efectivo, ideal para entornos multi-cliente o con múltiples sucursales.

## ✨ Características Principales

*   **Gestión Jerárquica de Roles:**
    *   **SuperAdmin:** Control total sobre el sistema, incluyendo la gestión de empresas, sucursales y todos los usuarios.
    *   **Admin:** Gestiona una empresa/sucursal específica, con visibilidad de datos aislada a su propio ámbito.
    *   **Usuario:** Rol final que accede a los servicios que su administrador le asigna.
*   **Bóveda Segura de Credenciales:**
    *   Las contraseñas de los sitios se almacenan en la base de datos utilizando encriptación fuerte **AES-256-CBC**.
    *   Las contraseñas de los usuarios del panel se hashean con el algoritmo moderno y seguro **Argon2ID**.
*   **Aislamiento de Datos (Multi-Tenant):**
    *   Un `Admin` solo puede ver y gestionar los usuarios, sitios y mensajes pertenecientes a su sucursal, garantizando la privacidad entre diferentes clientes o departamentos.
*   **Gestión Avanzada de Sitios:**
    *   Los `Admins` pueden crear sitios `privados` para su uso exclusivo.
    *   El `SuperAdmin` puede crear sitios `compartidos` que pueden ser asignados por cualquier `Admin` a sus usuarios.
*   **Comunicación Integrada:**
    *   Sistema de chat directo y seguro entre los `Usuarios` y su `Admin` creador.
*   **Sistema de Notificaciones y Alertas:**
    *   Los usuarios pueden reportar problemas de acceso o notificar sobre contraseñas expiradas directamente a su administrador.
    *   Panel de notificaciones centralizado para que los administradores gestionen las incidencias.
*   **Auditoría Completa:**
    *   Registro detallado de acciones críticas (creación, edición, eliminación) para un seguimiento de seguridad completo.

## 🛠️ Stack Tecnológico

*   **Backend:** PHP 8.3+
*   **Base de Datos:** MySQL 8.x / Percona Server
*   **Frontend:** JavaScript (ES6+) asíncrono con Fetch API.
*   **Seguridad:**
    *   **Hashing de Contraseñas:** `Argon2ID`
    *   **Encriptación de Datos:** `AES-256-CBC`
    *   **Protección Web:** CSRF Tokens en todas las peticiones que modifican datos, y Content-Security-Policy (CSP) con Nonce en el panel de usuario.
    *   **Base de Datos:** Uso exclusivo de Prepared Statements (PDO) para prevenir inyección SQL.

## 🚀 Instalación y Configuración

Sigue estos pasos para poner en marcha el proyecto en tu entorno de desarrollo (como Laragon).

### 1. Clonar el Repositorio (si usas Git)
```bash
# Navega a tu directorio de proyectos (ej. c:\laragon\www)
cd c:\laragon\www

# Clona el proyecto (si está en GitHub)
git clone <URL_DEL_REPOSITORIO> Psitios

# Entra en el directorio del proyecto
cd Psitios
```

### 2. Instalar Dependencias
Asegúrate de tener Composer instalado y ejecuta el siguiente comando en la raíz del proyecto:
```bash
composer install
```
Esto instalará `phpdotenv` y cualquier otra dependencia definida en `composer.json`.

### 3. Configurar la Base de Datos
1.  Abre tu gestor de base de datos (como phpMyAdmin o HeidiSQL).
2.  Crea una nueva base de datos. Se recomienda el nombre `secure_panel_db`.
3.  Importa el archivo `db_actual/secure_panel_db.sql` en la base de datos que acabas de crear. Esto creará todas las tablas y cargará los datos iniciales.

### 4. Configurar las Variables de Entorno
1.  En la raíz del proyecto, crea una copia del archivo `.env.example` y renómbrala a `.env`.
2.  Abre el archivo `.env` y edita las variables con tus datos:

    ```ini
    # Configuración de tu base de datos local
    DB_HOST=localhost
    DB_NAME=secure_panel_db
    DB_USER=root
    DB_PASS=tu_contraseña_de_bd

    # ¡IMPORTANTE! Genera una clave de encriptación segura.
    # Debe ser una cadena aleatoria de 32 bytes.
    ENCRYPTION_KEY=tu_clave_secreta_de_32_caracteres
    ```
    > **Nota de Seguridad:** La `ENCRYPTION_KEY` es crucial para la seguridad de las contraseñas. Asegúrate de que sea una clave fuerte y única para tu instalación.

## Uso

Una vez configurado, puedes acceder al panel. Los datos de acceso por defecto son:

*   **Usuario:** `admin`
*   **Contraseña:** `admin`

> **¡ADVERTENCIA!** Se recomienda encarecidamente cambiar la contraseña del usuario `admin` inmediatamente después del primer inicio de sesión.

## Estructura del Proyecto

```
/Psitios
├── api/                # Contiene los endpoints PHP de la API (backend)
├── db_actual/          # Scripts y volcados de la base de datos
├── vendor/             # Dependencias de Composer (ignorado por Git)
├── .env                # Archivo de configuración local (ignorado por Git)
├── .env.example        # Plantilla para el archivo .env
├── .gitignore          # Archivos y carpetas ignorados por Git
├── composer.json       # Definición de dependencias de Composer
└── ...                 # Otros archivos (frontend, etc.)
```