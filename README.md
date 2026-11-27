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

> Si el puerto 8081 de tu equipo está ocupado (por ejemplo, por el entorno local), usa otro puerto local:
> `ssh -L 9081:127.0.0.1:8081 usuario@IP_DEL_SERVIDOR` y abre http://127.0.0.1:9081

## Capturas

### 1. Estructura y código

|                        |                                          |
| ---------------------- | ---------------------------------------- |
| Estructura de carpetas | ![](capturas/02-estructura-carpetas.png) |
| init.sql               | ![](capturas/02b-init-sql.png)           |
| .env.example           | ![](capturas/02d-env-example.png)        |
| .gitignore             | ![](capturas/02e-gitignore.png)          |
| database.php           | ![](capturas/03-database-php.png)        |
| index.php              | ![](capturas/03c-index-php.png)          |
| Estructura de app/     | ![](capturas/03f-estructura-app.png)     |
| Dockerfile             | ![](capturas/04-dockerfile.png)          |
| nginx/default.conf     | ![](capturas/05-nginx-default-conf.png)  |
| compose.yaml           | ![](capturas/06-compose-yaml.png)        |

### 2. Ejecución en local (Windows + Docker Desktop)

![Construcción de imágenes](capturas/08-compose-up-build.png)
![Servicios healthy](capturas/08b-compose-up-healthy.png)
![docker compose ps](capturas/09-compose-ps.png)
![Docker Desktop](capturas/10-docker-desktop.png)
![Aplicación en local](capturas/11-app-local.png)
![phpMyAdmin en local](capturas/12-phpmyadmin-local.png)
![Pruebas CRUD](capturas/13-pruebas-crud.png)

### 3. Persistencia

![stop / start](capturas/14-stop-start.png)
![Datos tras reinicio](capturas/15-app-tras-reinicio.png)
![down / up](capturas/16-down-up.png)
![Datos tras recrear contenedores](capturas/17-persistencia.png)
![Volumen mysql_data](capturas/18-volumen.png)

### 4. Backup y restauración

![Backup](capturas/19-backup.png)
![Contenido del backup](capturas/19b-backup-sql.png)
![Simulación de pérdida](capturas/20-tareas-eliminadas.png)
![Comandos de restauración](capturas/21b-restore-comandos.png)
![Datos restaurados](capturas/21-restore.png)

### 5. GitHub

![git push](capturas/23-git-push.png)
![Repositorio](capturas/23b-github-repo.png)

### 6. Despliegue en Ubuntu Server

![IP del servidor](capturas/24-ip-servidor.png)
![Servicio SSH](capturas/24b-ssh-status.png)
![Conexión SSH con clave](capturas/25-ssh-clave.png)
![Docker en Ubuntu](capturas/26-docker-ubuntu.png)
![Firewall UFW](capturas/27-ufw.png)
![git clone](capturas/28-git-clone.png)
![Permisos del .env](capturas/28c-permisos-env.png)
![Despliegue](capturas/29b-compose-up-servidor.png)
![Servicios healthy en el servidor](capturas/29c-compose-up-healthy-servidor.png)
![docker compose ps en el servidor](capturas/29-compose-ps-servidor.png)
![Aplicación desplegada](capturas/30-app-servidor.png)
![phpMyAdmin no accesible desde fuera](capturas/31-pma-bloqueado.png)
![phpMyAdmin mediante túnel SSH](capturas/32-pma-tunel.png)

## Autor

Ramón Sáenz Corcuera — 2º DAW · Contenedores
