# Memo Test API

The backend for the [Memo Test](https://github.com/diegomottadev/memo-test) game. It's Laravel 8 with Lighthouse (GraphQL) and MySQL 8, and it all runs in Docker.

It stores the memo tests, their pictures and every game (tries, pairs found and score).

## What you need

Docker with Docker Compose v2 (`docker compose`). It works on Linux, macOS (Intel and Apple Silicon) and Windows with WSL2.

Ports **82** (API) and **3308** (MySQL) must be free. You can change them, see [Configuration](#configuration).

PHP, Composer and MySQL all run inside Docker, so you don't install them on your machine.

## Quick start

```bash
git clone https://github.com/diegomottadev/api-memo-test
cd api-memo-test
docker compose up -d
```

That's it. The first start takes a few minutes; after that it takes seconds. To follow it:

```bash
docker compose logs -f app
```

When you see `[memo-api] Ready: http://localhost:82/graphql`, the API is up:

- GraphQL: http://localhost:82/graphql
- GraphiQL (a GraphQL explorer in the browser): http://localhost:82/graphiql

Quick test:

```bash
curl -X POST http://localhost:82/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ memoTests { id name images { image_url } } }"}'
```

Then start the frontend with `VITE_API_URL=http://localhost:82/graphql` (see the [memo-test](https://github.com/diegomottadev/memo-test) README).

## What `docker compose up` does

| Service | Image                    | Job                                                                 |
| ------- | ------------------------ | ------------------------------------------------------------------- |
| `mysql` | `mysql:8.0`              | The `memotest` database. Its data lives in the `mysql-data` volume. |
| `app`   | PHP 8.1 FPM (built here) | Runs Laravel. It gets everything ready before it starts.            |
| `nginx` | `nginx:1.27-alpine`      | Serves the API on port 82.                                          |

Every time the `app` container starts, `_docker/app/entrypoint.sh`:

1. Creates `.env` from `.env.example` if it's missing.
2. Lets Laravel write to `storage/` and `bootstrap/cache/`.
3. Runs `composer install` if `vendor/` is missing.
4. Creates `APP_KEY` if there isn't one.
5. Waits until MySQL accepts connections.
6. Runs the migrations and adds the sample data (2 memo tests with 4 pictures each).

You can run every step again and again. A restart never duplicates data.

`nginx` waits for `app`, and `app` waits for a healthy MySQL, so nothing starts too early.

Files created inside the repo (`vendor/`, `.env`, logs) belong to your user, so you can edit or delete them without `sudo`.

## Useful commands

```bash
docker compose ps                                          # status of each service
docker compose logs -f app                                 # Laravel and start-up logs
docker compose stop                                        # stop (keeps the data)
docker compose down                                        # remove the containers (keeps the data)
docker compose down -v                                     # remove the containers and the database (clean start)
docker compose exec app php artisan tinker                 # Laravel console
docker compose exec app php artisan migrate:fresh --seed   # reset the database with the sample data
docker compose exec mysql mysql -uroot -proot memotest     # MySQL console
docker compose up -d --build                               # rebuild the image after you change the Dockerfile
```

## Configuration

Optional variables when you start it (for example `APP_PORT=8082 docker compose up -d`):

| Variable       | Default | Use                                                                         |
| -------------- | ------- | --------------------------------------------------------------------------- |
| `APP_PORT`     | `82`    | API port on your machine.                                                   |
| `DB_HOST_PORT` | `3308`  | MySQL port on your machine (for a client like DBeaver).                     |
| `UID` / `GID`  | `1000`  | Your user and group (`id -u` / `id -g`), so the files it creates are yours. |

MySQL login (for development only): user `root`, password `root`, database `memotest`. From your machine, connect to `127.0.0.1:3308`.

Inside Docker, the variables in `docker-compose.yml` set the database connection, and they win over `.env`.

## GraphQL API

```graphql
type Query {
  memoTests: [MemoTest!]! # includes scoreMax: the session with the best score
  memoTest(id: ID!): MemoTest
  gameSession(id: ID!): GameSession
}

type Mutation {
  createGameSession(memo_test_id: ID!, retries: Int!, number_of_pairs: Int!, state: SessionState!): GameSession
  updateGameSessionCard(id: ID!, retries: Int!, number_of_pairs: Int!): GameSession
  updateGameSession(id: ID!, score: Int!): GameSession
}
```

The schema also lists `createMemoTest`, `updateMemoTest`, `deleteMemoTest` and `endGameSession`. They have no resolver yet, so they answer "Could not locate a field resolver". They're in the checklist at the end.

Full schema: `graphql/schema.graphql`. CORS lets any origin call `/graphql`.

## Common problems

- **`port is already allocated`**: another program uses port 82 or 3308. Pick other ports with `APP_PORT=8082 DB_HOST_PORT=3309 docker compose up -d`, and set `VITE_API_URL=http://localhost:8082/graphql` in the frontend.
- **The API answers 502 for the first few minutes**: `app` is still installing packages. Watch `docker compose logs -f app`.
- **Permission errors in `storage/`**: if your user id isn't 1000, start with `UID=$(id -u) GID=$(id -g) docker compose up -d --build`.
- **I want a clean start**: `docker compose down -v && docker compose up -d`.
- **I used the old version (MySQL 5.7)**: the new database lives in the `mysql-data` volume, and the old `dbdata` volume stays as it was. To delete it, run `docker volume rm api-memo-test_dbdata`.

## Known limits

- `endGameSession`, `createMemoTest`, `updateMemoTest` and `deleteMemoTest` have no resolver. Sessions stay `Started`.
- `scoreMax` counts unfinished sessions (score 0).
- The sample pictures are links to other websites.
- Laravel 8 and PHP 8.1 are out of support. A newer version means upgrading the whole project.

## To do: a checklist for learning

Tasks sorted by level, for whoever picks up this project next. Each one says where to look and what you'll learn.

Try every change in GraphiQL (http://localhost:82/graphiql), and with tests once there are some.

### Beginner

- [ ] **Add `endGameSession`**, so a session can be marked `Completed`. Where: `graphql/schema.graphql` (with a directive like `@update`), or a class in `app/GraphQL/Mutations` made with `php artisan lighthouse:mutation EndGameSession`. You'll learn: Lighthouse resolvers.
- [ ] **Add `createMemoTest`, `updateMemoTest` and `deleteMemoTest`.** Where: same as above. `createMemoTest` gets picture URLs and has to create the `memo_test_images` rows. You'll learn: mutations with relations.
- [ ] **Check the input of each mutation** (score from 0 to 100, tries and pairs never below 0). Where: `@rules` directives in the schema. You'll learn: validation in GraphQL.
- [ ] **Make `scoreMax` skip unfinished games.** Where: the `scoreMax()` relation in `app/Models/MemoTest.php`. You'll learn: Eloquent relations with conditions.
- [ ] **Take `.env` out of the repo.** It's tracked and has an `APP_KEY`. Keep only `.env.example`; the Docker start creates `.env` when it's missing. You'll learn: how to keep secrets out of git.

### Intermediate

- [ ] **API tests** with PHPUnit and Lighthouse's `MakesGraphQLRequests`. Today there are only the 2 `ExampleTest` files. Where: `tests/Feature`. You'll learn: integration tests with a database.
- [ ] **Add a description to each picture** (migration, schema and seeder), so the frontend can use it as alt text in place of "Picture 1". You'll learn: schema changes that keep old clients working.
- [ ] **Store the pictures in the project** (`storage/app/public` plus `php artisan storage:link`), so they keep working when another website changes. You'll learn: file storage in Laravel.
- [ ] **Tidy up the database.** The migration `add_score_to_memo_tests_table` changes `game_sessions`, and nothing uses the `user_selections` column. You'll learn: how to fix things with new migrations and leave the old ones alone.
- [ ] **Factories and fake data** for `MemoTest` and `GameSession` with Faker. Where: `database/factories`. You'll learn: test data.
- [ ] **CI with GitHub Actions**: start MySQL as a service and run migrations and tests on every pull request. You'll learn: automation.

### Advanced

- [ ] **Upgrade to a supported Laravel and PHP**, with Lighthouse 6. You'll learn: major upgrades, one step at a time.
- [ ] **Users and a ranking** with Sanctum (it's already installed). You'll learn: login in a GraphQL API.
- [ ] **A production image** (multi-stage, code copied into the image, OPcache, no bind mount) and a deploy with HTTPS (for example Render, Fly.io or Railway), so the GitHub Pages demo can use real data. You'll learn: Docker for production.
- [ ] **Production security**: limit CORS to the frontend's domain, limit requests per minute and set `APP_DEBUG=false`. You'll learn: how to lock down a public API.
