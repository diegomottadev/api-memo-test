# Memo Test API

Backend del juego [Memo Test](https://github.com/diegomottadev/memo-test): Laravel 8 + Lighthouse (GraphQL) + MySQL 8, todo en Docker. Guarda los memo tests, sus imágenes y las partidas (intentos, pares encontrados y puntaje).

## Requisitos

- Docker con Docker Compose v2 (`docker compose`). Funciona en Linux, macOS (Intel y Apple Silicon) y Windows con WSL2.
- Puertos libres: **82** (API) y **3308** (MySQL). Se pueden cambiar, ver [Configuración](#configuración).

No hace falta tener PHP, Composer ni MySQL instalados.

## Inicio rápido

```bash
git clone https://github.com/diegomottadev/api-memo-test
cd api-memo-test
docker compose up -d
```

Listo. La primera vez tarda unos minutos; las siguientes, segundos. Para ver el avance:

```bash
docker compose logs -f app
```

Cuando aparece `[memo-api] Ready: http://localhost:82/graphql`, la API está lista:

- GraphQL: http://localhost:82/graphql
- GraphiQL (explorador en el navegador): http://localhost:82/graphiql

Prueba rápida:

```bash
curl -X POST http://localhost:82/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ memoTests { id name images { image_url } } }"}'
```

Después levantá el frontend con `VITE_API_URL=http://localhost:82/graphql` (ver el README de [memo-test](https://github.com/diegomottadev/memo-test)).

## Qué hace `docker compose up`

| Servicio | Imagen                     | Qué hace                                                    |
| -------- | -------------------------- | ----------------------------------------------------------- |
| `mysql`  | `mysql:8.0`                | Base de datos `memotest`. Datos en el volumen `mysql-data`. |
| `app`    | PHP 8.1 FPM (se construye) | Corre Laravel. Antes de arrancar, prepara todo (ver abajo). |
| `nginx`  | `nginx:1.27-alpine`        | Sirve la API en el puerto 82.                               |

Cada vez que arranca, el contenedor `app` (`_docker/app/entrypoint.sh`):

1. Crea `.env` desde `.env.example` si no existe.
2. Da permisos de escritura a `storage/` y `bootstrap/cache/`.
3. Instala las dependencias (`composer install`) si falta `vendor/`.
4. Genera `APP_KEY` si no hay una.
5. Espera a que MySQL acepte conexiones.
6. Corre las migraciones y carga los datos de ejemplo (2 memo tests con 4 imágenes cada uno).

Todos los pasos se pueden repetir sin romper nada: reiniciar no duplica datos. `nginx` espera a que `app` termine, y `app` espera a que MySQL esté sano, así que no hay errores por arrancar antes de tiempo.

Los archivos que se crean dentro del repo (`vendor/`, `.env`, logs) quedan a nombre de tu usuario, no de root.

## Comandos útiles

```bash
docker compose ps                                          # estado de los servicios
docker compose logs -f app                                 # logs de Laravel y del arranque
docker compose stop                                        # detener (conserva los datos)
docker compose down                                        # borrar contenedores (conserva los datos)
docker compose down -v                                     # borrar contenedores y la base (empezar de cero)
docker compose exec app php artisan tinker                 # consola de Laravel
docker compose exec app php artisan migrate:fresh --seed   # reiniciar la base con los datos de ejemplo
docker compose exec mysql mysql -uroot -proot memotest     # consola de MySQL
docker compose up -d --build                               # reconstruir la imagen después de cambiar el Dockerfile
```

## Configuración

Variables opcionales al levantar (por ejemplo `APP_PORT=8082 docker compose up -d`):

| Variable       | Por defecto | Uso                                                                                  |
| -------------- | ----------- | ------------------------------------------------------------------------------------ |
| `APP_PORT`     | `82`        | Puerto de la API en tu máquina.                                                      |
| `DB_HOST_PORT` | `3308`      | Puerto de MySQL en tu máquina (para un cliente como DBeaver).                        |
| `UID` / `GID`  | `1000`      | Tu usuario y grupo (`id -u` / `id -g`), para que los archivos generados sean tuyos. |

Credenciales de MySQL (solo para desarrollo): usuario `root`, contraseña `root`, base `memotest`. Desde tu máquina: `127.0.0.1:3308`.

Dentro de Docker, la conexión a la base la definen las variables de `docker-compose.yml`, que tienen prioridad sobre `.env`.

## API GraphQL

```graphql
type Query {
  memoTests: [MemoTest!]! # incluye scoreMax: la sesión de mayor puntaje
  memoTest(id: ID!): MemoTest
  gameSession(id: ID!): GameSession
}

type Mutation {
  createGameSession(memo_test_id: ID!, retries: Int!, number_of_pairs: Int!, state: SessionState!): GameSession
  updateGameSessionCard(id: ID!, retries: Int!, number_of_pairs: Int!): GameSession
  updateGameSession(id: ID!, score: Int!): GameSession
}
```

El schema también declara `createMemoTest`, `updateMemoTest`, `deleteMemoTest` y `endGameSession`, pero **no tienen implementación**: responden "Could not locate a field resolver". Están en el checklist de pendientes.

Schema completo: `graphql/schema.graphql`. CORS permite cualquier origen en `/graphql`.

## Problemas comunes

- **`port is already allocated`**: otro programa usa el 82 o el 3308. Usá otros puertos: `APP_PORT=8082 DB_HOST_PORT=3309 docker compose up -d` (y en el frontend, `VITE_API_URL=http://localhost:8082/graphql`).
- **La API responde 502 los primeros minutos**: `app` todavía está instalando dependencias; mirá `docker compose logs -f app`.
- **Errores de permisos en `storage/`**: si tu usuario no es el 1000, levantá con `UID=$(id -u) GID=$(id -g) docker compose up -d --build`.
- **Quiero empezar de cero**: `docker compose down -v && docker compose up -d`.
- **Venía de la versión anterior (MySQL 5.7)**: la base nueva usa el volumen `mysql-data`; el viejo (`dbdata`) queda sin tocar. Si no lo necesitás: `docker volume rm api-memo-test_dbdata`.

## Limitaciones conocidas

- `endGameSession`, `createMemoTest`, `updateMemoTest` y `deleteMemoTest` están en el schema pero no tienen implementación; las sesiones quedan en estado `Started`.
- `scoreMax` incluye sesiones sin terminar (puntaje 0).
- Las imágenes de ejemplo son links a sitios externos.
- Laravel 8 y PHP 8.1 ya no tienen soporte oficial; actualizar requiere migrar el proyecto a una versión nueva de Laravel.

## Pendiente: checklist para seguir aprendiendo

Tareas ordenadas por dificultad para quien retome el proyecto. Cada una dice dónde mirar y qué se practica. Probá cada cambio con GraphiQL (http://localhost:82/graphiql) y, cuando existan, con tests.

### Nivel inicial

- [ ] **Implementar `endGameSession`** para marcar la sesión como `Completed`. Dónde: `graphql/schema.graphql` (con una directiva como `@update`, o una clase en `app/GraphQL/Mutations` creada con `php artisan lighthouse:mutation EndGameSession`). Practicás: resolvers de Lighthouse.
- [ ] **Implementar `createMemoTest`, `updateMemoTest` y `deleteMemoTest`.** Dónde: igual que el anterior; `createMemoTest` recibe URLs de imágenes y tiene que crear las filas de `memo_test_images`. Practicás: mutations con relaciones.
- [ ] **Validar los datos de las mutations** (puntaje de 0 a 100, intentos y pares no negativos). Dónde: directivas `@rules` en el schema. Practicás: validación en GraphQL.
- [ ] **Hacer que `scoreMax` ignore las partidas sin terminar.** Dónde: relación `scoreMax()` en `app/Models/MemoTest.php`. Practicás: relaciones de Eloquent con condiciones.
- [ ] **Sacar `.env` del repositorio** (está versionado y tiene una `APP_KEY`) y dejar solo `.env.example`; el arranque de Docker ya lo crea si falta. Practicás: manejo de secretos.

### Nivel intermedio

- [ ] **Tests de la API** con PHPUnit y `MakesGraphQLRequests` de Lighthouse (hoy solo están los `ExampleTest`). Dónde: `tests/Feature`. Practicás: tests de integración con base de datos.
- [ ] **Agregar una descripción a cada imagen** (migración, schema y seeder) para que el frontend la use como texto alternativo en lugar de "Picture 1". Practicás: migraciones y cambios de schema sin romper el cliente.
- [ ] **Guardar las imágenes en el proyecto** (`storage/app/public` + `php artisan storage:link`) en lugar de links a sitios externos que se pueden caer. Practicás: archivos en Laravel.
- [ ] **Ordenar la base:** la migración `add_score_to_memo_tests_table` en realidad modifica `game_sessions`, y la columna `user_selections` no se usa. Practicás: migraciones nuevas sin reescribir las viejas.
- [ ] **Factories y datos de prueba** para `MemoTest` y `GameSession` con Faker. Dónde: `database/factories`. Practicás: datos para tests.
- [ ] **CI con GitHub Actions:** levantar MySQL como servicio y correr migraciones y tests en cada Pull Request. Practicás: automatización.

### Nivel avanzado

- [ ] **Actualizar a una versión de Laravel y PHP con soporte** (Laravel 8 y PHP 8.1 ya no tienen), junto con Lighthouse 6. Practicás: actualizaciones mayores paso a paso.
- [ ] **Usuarios y ranking** con Sanctum (ya está instalado). Practicás: autenticación en una API GraphQL.
- [ ] **Imagen de producción** (multi-stage, código copiado en la imagen, OPcache, sin bind mount) y deploy con HTTPS (por ejemplo Render, Fly.io o Railway), para que la demo de GitHub Pages use datos reales. Practicás: Docker para producción.
- [ ] **Seguridad para producción:** CORS limitado al dominio del frontend, límite de pedidos por minuto y `APP_DEBUG=false`. Practicás: endurecer una API pública.

