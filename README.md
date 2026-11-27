# Gestor de tareas con Docker Compose

Aplicación web de gestión de tareas (To-Do List) desarrollada con **PHP + MySQL**, contenerizada con **Docker** y orquestada con **Docker Compose**. Incluye proxy inverso con **Nginx**, administración con **phpMyAdmin** y persistencia de datos mediante volúmenes.

## Tecnologías

| Tecnología              | Función                         |
| ----------------------- | ------------------------------- |
| HTML5 y CSS3            | Interfaz de usuario             |
| PHP 8.3 + Apache        | Backend y servidor web          |
| MySQL 8.4               | Base de datos                   |
| Nginx                   | Proxy inverso (entrada pública) |
| phpMyAdmin              | Administración de MySQL         |
| Docker / Docker Compose | Contenedores y orquestación     |
| Ubuntu Server           | Servidor de despliegue          |

## Arquitectura

```
Navegador ──► Nginx (puerto 80) ──► Apache + PHP (web:80) ──► MySQL (db:3306)
                                                                  ▲
                         phpMyAdmin (127.0.0.1:8081) ─────────────┘
```

| Servicio     | Imagen                     | Función                              | Puerto publicado            |
| ------------ | -------------------------- | ------------------------------------ | --------------------------- |
| `nginx`      | nginx:1.27-alpine          | Proxy inverso, única entrada pública | 80                          |
| `web`        | Propia (apache/Dockerfile) | Apache + PHP con la aplicación       | Ninguno                     |
| `db`         | mysql:8.4                  | Base de datos                        | Ninguno                     |
| `phpmyadmin` | phpmyadmin:5               | Administración de MySQL              | 127.0.0.1:8081 (solo local) |

### Redes

- **frontend**: red por la que entra el tráfico externo a Nginx (y a phpMyAdmin).
- **backend** (`internal: true`): red interna sin acceso desde fuera. Aquí se comunican Nginx, Apache, MySQL y phpMyAdmin. Los servicios se encuentran por su nombre (`db`, `web`) gracias al DNS interno de Docker.

### Volumen

- **mysql_data**: montado en `/var/lib/mysql`. Los datos se conservan aunque los contenedores se detengan, se eliminen o se reconstruyan.

### Orden de arranque

Mediante `depends_on` con `condition: service_healthy`:
`db` (healthy) → `web` (healthy) → `nginx`. phpMyAdmin espera también a `db`.

## Estructura del proyecto

```
proyecto-docker/
├── app/
│   ├── index.php            # Página principal y operaciones CRUD
│   ├── health.php           # Endpoint de salud para el healthcheck
│   ├── config/database.php  # Conexión PDO a MySQL
│   ├── includes/functions.php
│   ├── css/style.css
│   └── sql/init.sql         # Creación de la base de datos y la tabla
├── apache/Dockerfile        # Imagen PHP + Apache + pdo_mysql
├── nginx/default.conf       # Configuración del proxy inverso
├── compose.yaml             # Definición de los 4 servicios
├── .env.example             # Variables de entorno de ejemplo (sin secretos)
├── .gitignore
└── capturas/                # Capturas del funcionamiento
```

## Seguridad aplicada

- Consultas preparadas con **PDO** (protección frente a inyección SQL).
- Escape de la salida HTML con `htmlspecialchars` (protección XSS).
- Token **CSRF** en todos los formularios.
- Credenciales en `.env`, excluido de Git mediante `.gitignore`.
- MySQL y Apache **no publican puertos** al exterior; solo Nginx.
- phpMyAdmin solo accesible desde `127.0.0.1` (en el servidor, mediante túnel SSH).

## Instalación y ejecución en local

Requisitos: Docker Desktop (Windows/Mac) o Docker Engine + Docker Compose plugin (Linux).

```bash
git clone URL_DEL_REPOSITORIO
cd proyecto-docker
cp .env.example .env        # y editar .env con contraseñas propias
docker compose config       # validar la configuración
docker compose up -d --build
docker compose ps
```

Accesos:

- Aplicación: http://localhost
- phpMyAdmin: http://127.0.0.1:8081 (usuario y contraseña de `MYSQL_USER` / `MYSQL_PASSWORD`)

## Comandos útiles

```bash
docker compose ps                 # estado de los servicios
docker compose logs -f web        # registros de un servicio
docker compose stop / start       # detener / iniciar
docker compose down               # eliminar contenedores (conserva los datos)
docker compose down -v            # ¡elimina también el volumen y los datos!
```

## Persistencia comprobada

1. Se crearon 10 tareas, se completaron 3 y se eliminaron 2.
2. `docker compose stop` + `docker compose start` → los datos se mantienen.
3. `docker compose down` + `docker compose up -d` → los datos se mantienen, porque están en el volumen `mysql_data`.

## Copia de seguridad y restauración

**Backup:**

```bash
mkdir -p backups
docker compose exec -T db sh -c 'mysqldump -uroot -p$MYSQL_ROOT_PASSWORD --single-transaction $MYSQL_DATABASE > /tmp/backup.sql'
docker compose cp db:/tmp/backup.sql backups/tareas-backup.sql
```

**Restauración:**

```bash
docker compose cp backups/tareas-backup.sql db:/tmp/restore.sql
docker compose exec -T db sh -c 'mysql -uroot -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE < /tmp/restore.sql'
```

Los backups se generan dentro del contenedor y se copian fuera con `docker compose cp` para evitar problemas de codificación. La carpeta `backups/` está excluida de Git.

## Despliegue en Ubuntu Server

```bash
# 1. Instalar Docker Engine y el plugin Compose (documentación oficial de Docker)
# 2. Firewall: permitir SSH y HTTP/HTTPS, nunca 3306
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# 3. Obtener el proyecto
git clone URL_DEL_REPOSITORIO
cd proyecto-docker
cp .env.example .env
nano .env          # credenciales de producción distintas a las de local
chmod 600 .env

# 4. Desplegar
docker compose up -d --build
docker compose ps
docker compose logs --tail=100
```

Acceso: `http://IP_DEL_SERVIDOR`

phpMyAdmin en remoto, mediante túnel SSH:

```bash
ssh -L 8081:127.0.0.1:8081 usuario@IP_DEL_SERVIDOR
# y abrir http://127.0.0.1:8081 en el navegador local
```

## Capturas

Las capturas del funcionamiento se encuentran en la carpeta [`capturas/`](capturas/).

## Autor

Raúl — 2º DAW
