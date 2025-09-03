# Psitios - Panel de Gestión Segura

Un panel de control seguro desarrollado en PHP y MySQL para gestionar el acceso de múltiples usuarios a diferentes sitios o servicios web, centralizando y protegiendo las credenciales.

## Características Principales

*   **Gestión de Usuarios:** Creación de usuarios con roles de `administrador` o `usuario`. Los administradores pueden asignar usuarios y ver a todos los clientes.
*   **Gestión de Sitios:** Almacena de forma centralizada los datos de acceso (URL, usuario, contraseña) a diferentes plataformas (ej. Proxmox, Cloud Panels, etc.).
*   **Credenciales Seguras:** Las contraseñas de los sitios se almacenan **encriptadas** en la base de datos para máxima seguridad.
*   **Control de Acceso:** Asigna permisos a los usuarios para que solo puedan ver y acceder a los sitios que les corresponden a través de "Servicios".
*   **Sistema de Notificaciones:** Alerta a los usuarios sobre eventos importantes, como la asignación de un nuevo servicio o problemas reportados.
*   **Mensajería Interna:** Permite la comunicación directa y segura entre usuarios del panel.
*   **Registro de Auditoría:** Guarda un log de las acciones importantes realizadas en el panel (quién, qué, cuándo y desde dónde) para un seguimiento completo.

## Stack Tecnológico

*   **Backend:** PHP 8.3+
*   **Base de Datos:** MySQL 8.x (o compatible, como Percona Server)
*   **Gestión de Dependencias:** Composer
*   **Variables de Entorno:** vlucas/phpdotenv

## Instalación y Configuración

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