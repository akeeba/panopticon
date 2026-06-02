# Docker Build & manual GHCR Release Instructions

> [!IMPORTANT]
> Docker images are built and published to GitHub Container Repository (GHCR) automatically when we tag a new release. This is taken care of by the `.github/workflows/docker.yml` file.
> 
> The instructions below are only meant to be used when building the image locally (e.g. for development reasons), or if the workflow breaks for any reason and a manual release of the Docker image is necessary. 

## Prerequisites

### 1. Docker with Buildx

Buildx is required for multi-architecture builds (`linux/amd64` + `linux/arm64`).

```bash
# Verify Docker is installed and running
docker version

# Verify buildx is available (included in Docker Desktop ≥ 2.1 and Docker Engine ≥ 19.03)
docker buildx version
```

For manual GHCR releases we need multi-arch builds. Therefore, we must create a builder that can cross-compile:

```bash
docker buildx create --name panopticon-builder --use
docker buildx inspect --bootstrap
```

### 2. GitHub CLI (`gh`)

This step is only necessary if we are doing a manual GHCR release.

```bash
gh --version          # must be ≥ 2.x
gh auth status        # must show "Logged in to github.com"
```

If not logged in: `gh auth login`.

### 3. GHCR write access

This step is only necessary if we are doing a manual GHCR release.

Your GitHub account (or token) needs `write:packages` scope on `akeeba/panopticon`.

```bash
gh auth status --show-token 2>&1 | grep -i packages
# Expected output includes "write:packages"
```

If the scope is missing, re-authenticate:

```bash
gh auth login --scopes "write:packages,read:packages,repo"
```

### 4. Docker logged in to GHCR

This step is only necessary if we are doing a manual GHCR release.

```bash
echo $(gh auth token) | docker login ghcr.io -u $(gh api user --jq .login) --password-stdin
# Expected: "Login Succeeded"
```

### 5. Repository is clean and built

The `.dockerignore` excludes `node_modules/`, build artefacts, and dev files, but `vendor/` and `media/` (compiled assets) **must be present** because the image copies them in.

```bash
# From the repo root
composer install --no-dev --optimize-autoloader
# This also runs npm install, SCSS compilation, and JS transpilation
```

---

## Creating the Image

The `Dockerfile` at the repo root produces an Apache + PHP image. The PHP version used is set up at the top of the `Dockerfile`, in the `PHP_VERSION` argument.

### Single-arch build (local, fast)

This is only meant to be used for local development and testing.

```bash
# From the repo root
docker build \
  --tag ghcr.io/akeeba/panopticon:latest \
  --tag ghcr.io/akeeba/panopticon:$(git describe --tags --abbrev=0) \
  .
```

Replace `$(git describe --tags --abbrev=0)` with the version tag manually if not on a tagged commit (e.g. `1.2.3`).

### Multi-arch build (amd64 + arm64, for release)

This is meant to be used for a manual GHCR release.

```bash
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  --tag ghcr.io/akeeba/panopticon:latest \
  --tag ghcr.io/akeeba/panopticon:$(git describe --tags --abbrev=0) \
  --provenance false \
  .
  # Add --push here only when ready to release (see Deploying section)
```

`--provenance false` avoids a Docker manifest quirk that breaks `docker pull` for some clients when using Buildx without pushing.

### Build-time PHP version override

Only use this for development purposes, e.g. testing Panopticon with a newer PHP version without having to set up a local dev server.

The `Dockerfile` exposes a `PHP_VERSION` build argument. You can override it at build time:

```bash
docker build --build-arg PHP_VERSION=8.5 --tag ghcr.io/akeeba/panopticon:8.5 .
```

---

## Testing the Image

### 1. Create the test directory structure

When doing a local test you typically don't want your test data to mix with your regular Panopticon data. You can use the file `docker-compose.override.yml` (in the repo root) to bind `./docker-testing/` subdirectories as persistent data volumes instead of using named Docker volumes, making data easily inspectable and disposable.

```bash
mkdir -p docker-testing/user_code docker-testing/config docker-testing/db
```

The contents of the `docker-compose.override.yml` file are:

```yaml
services:
  php:
    volumes:
      - type: bind
        source: ./docker-testing/user_code
        target: /var/www/html/user_code
      - type: bind
        source: ./docker-testing/config
        target: /var/www/html/config
  mysql:
    volumes:
      - type: bind
        source: ./docker-testing/db
        target: /var/lib/mysql
```

### 2. Create `.env.docker`

The entrypoint reads this file. Create it from the distributed example:

```bash
cp .env.dist .env.docker
```

Then edit `.env.docker` with at minimum the following settings:

```ini
# Database (must match what the mysql container uses)
MYSQL_DATABASE=panopticon
MYSQL_USER=panopticon
MYSQL_PASSWORD=panopticon
MYSQL_ROOT_PASSWORD=root

# Panopticon bootstrap
PANOPTICON_DB_HOST=mysql
PANOPTICON_DB_PREFIX=pnptc_
PANOPTICON_USING_ENV=0

# Number of parallel task:run CRON workers
PANOPTICON_CRON_JOBS=2

# Admin account auto-created on first run
ADMIN_USERNAME=admin
ADMIN_PASSWORD=adminpassword
ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com
```

### 3. Start the stack

```bash
# docker-compose.override.yml is automatically merged by docker compose
docker compose up --build
```

Watch the logs until you see `Starting Apache` — the entrypoint has finished setup.

### 4. Verify

```bash
# Web UI
open http://localhost:4280

# CLI smoke test
docker exec panopticon_php php cli/panopticon.php list

# Check cron is running
docker exec panopticon_php crontab -u panopticon -l
```

### 5. Test the pre-built image (without rebuilding)

Edit `docker-compose.yml`: comment out the `build:` block and uncomment the `image:` line:

```yaml
php:
  image: ghcr.io/akeeba/panopticon:latest
  # build: ...
```

Then:

```bash
docker compose up
```

This confirms the image works end-to-end without any local source code.

### 6. Tear down

```bash
docker compose down
rm -rf docker-testing/db docker-testing/config
```

---

## Deploying the Image to GHCR

This entire section is only relevant when doing a manual GHCR build.

### Tag conventions

| Tag | Meaning |
|---|---|
| `latest` | Most recent stable release |
| `1.2.3` | Exact version |
| `1.2` | Latest patch for minor series (optional) |

### Push a single-arch image (quick path)

```bash
VERSION=$(git describe --tags --abbrev=0)   # e.g. 1.2.3

docker build \
  --tag ghcr.io/akeeba/panopticon:${VERSION} \
  --tag ghcr.io/akeeba/panopticon:latest \
  .

docker push ghcr.io/akeeba/panopticon:${VERSION}
docker push ghcr.io/akeeba/panopticon:latest
```

### Push a multi-arch image (release path)

Buildx builds and pushes in one step. Use this for all public releases:

```bash
VERSION=$(git describe --tags --abbrev=0)

docker buildx build \
  --platform linux/amd64,linux/arm64 \
  --tag ghcr.io/akeeba/panopticon:${VERSION} \
  --tag ghcr.io/akeeba/panopticon:latest \
  --provenance false \
  --push \
  .
```

`--push` sends the multi-arch manifest directly to GHCR without loading it into the local Docker daemon.

### Verify the push

```bash
# Inspect the manifest
docker buildx imagetools inspect ghcr.io/akeeba/panopticon:latest

# Pull and run a quick sanity check
docker run --rm ghcr.io/akeeba/panopticon:latest php --version
```

### Make the package public (first time only)

> [!NOTE]
> This step is already complete for our published image. This section only serves historical purposes.

GHCR packages are private by default. To make it publicly pullable:

1. Go to `https://github.com/akeeba/panopticon/pkgs/container/panopticon`
2. Click **Package settings**
3. Under **Danger Zone**, change visibility to **Public**
