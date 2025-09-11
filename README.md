# Psitios - Panel de Gestión Segura y Jerárquica

Un panel de control seguro desarrollado en PHP y MySQL para gestionar el acceso de múltiples usuarios a diferentes sitios o servicios web. El sistema está diseñado con una arquitectura jerárquica de roles y datos (`SuperAdmin` > `Admin` > `Usuario` y `Empresa` > `Sucursal` > `Departamento`) que permite un aislamiento de datos efectivo, ideal para entornos multi-cliente o con múltiples sucursales.

## ✨ Características Principales

*   **Gestión Jerárquica de Roles:**
    *   **SuperAdmin:** Control total sobre el sistema. Gestiona empresas, sucursales, departamentos y todos los usuarios.
    *   **Admin:** Gestiona una empresa/sucursal específica, con visibilidad de datos aislada a su propio ámbito.
    *   **Usuario:** Rol final que accede a los servicios que su administrador le asigna.
*   **Bóveda Segura de Credenciales:**
    *   Las contraseñas de los sitios se almacenan en la base de datos utilizando encriptación fuerte **AES-256-CBC**.
    *   Las contraseñas de los usuarios del panel se hashean con el algoritmo moderno y seguro **BCRYPT** (a través de `password_hash` con `PASSWORD_DEFAULT`).
*   **Aislamiento de Datos (Multi-Tenant):**
    *   Un `Admin` solo puede ver y gestionar los usuarios, sitios y mensajes pertenecientes a su departamento, garantizando la privacidad entre diferentes clientes.
*   **Gestión Avanzada de Sitios:**
    *   Los `Admins` pueden crear sitios y asignarlos a los usuarios de su departamento.
    *   El `SuperAdmin` puede crear sitios y asignarlos a departamentos específicos.
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
*   **Frontend:** JavaScript (ES6+) nativo (Vanilla JS) con un enfoque modular y asíncrono (Fetch API).
*   **Seguridad:**
    *   **Hashing de Contraseñas:** `BCRYPT` (vía `password_hash`).
    *   **Encriptación de Datos:** `AES-256-CBC`
    *   **Protección Web:** CSRF Tokens en todas las peticiones que modifican datos, y Content-Security-Policy (CSP) con Nonce para prevenir ataques XSS.
    *   **Base de Datos:** Uso exclusivo de Prepared Statements (PDO) para prevenir inyección SQL.
    *   **Manejo de Errores:** Gestión centralizada de errores para evitar la exposición de información sensible.

## 🚀 Instalación y Configuración

El proyecto incluye un script de instalación interactivo que automatiza la mayor parte del proceso.

### Requisitos Previos
*   PHP 8.1 o superior (con las extensiones `pdo_mysql`, `openssl`, `mbstring` habilitadas).
*   Composer instalado globalmente.
*   Acceso a un servidor MySQL con un usuario que tenga permisos para crear bases de datos y usuarios (ej. `root`).

### Pasos de Instalación
1.  **Clonar el Repositorio**
    ```bash
    # Navega a tu directorio de proyectos (ej. c:\laragon\www)
    cd c:\laragon\www

    # Clona el proyecto
    git clone https://github.com/sergioecm60/Psitios.git
    cd Psitios
    ```

2.  **Ejecutar el Instalador Interactivo**
    Abre una terminal en la raíz del proyecto y ejecuta el siguiente comando:
    ```bash
    php install.php
    ```
    El script te guiará a través de los siguientes pasos:
    *   Verificará los requisitos del sistema.
    *   Te pedirá las credenciales de tu usuario `root` de MySQL.
    *   Creará la base de datos y un usuario específico para la aplicación.
    *   Generará automáticamente el archivo `.env` con la configuración y una clave de encriptación segura.
    *   Instalará las dependencias de Composer.
    *   Importará la estructura de la base de datos desde `db_actual/secure_panel_db.sql`.

3.  **Configurar el Servidor Web**
    Asegúrate de que tu servidor web (Apache, Nginx, etc.) apunte al directorio raíz del proyecto `Psitios`.

¡Y eso es todo! La aplicación está lista para usarse.

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