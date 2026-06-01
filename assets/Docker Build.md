# Docker Build & GHCR Release Instructions

## Prerequisites

### 1. Docker with Buildx

Buildx is required for multi-architecture builds (`linux/amd64` + `linux/arm64`).

```bash
# Verify Docker is installed and running
docker version

# Verify buildx is available (included in Docker Desktop ≥ 2.1 and Docker Engine ≥ 19.03)
docker buildx version
```

If you need multi-arch builds, create a builder that can cross-compile:

```bash
docker buildx create --name panopticon-builder --use
docker buildx inspect --bootstrap
```

### 2. GitHub CLI (`gh`)

```bash
gh --version          # must be ≥ 2.x
gh auth status        # must show "Logged in to github.com"
```

If not logged in: `gh auth login`.

### 3. GHCR write access

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

The `Dockerfile` at the repo root produces an Apache + PHP 8.4 image.

### Single-arch build (local, fast)

```bash
# From the repo root
docker build \
  --tag ghcr.io/akeeba/panopticon:latest \
  --tag ghcr.io/akeeba/panopticon:$(git describe --tags --abbrev=0) \
  .
```

Replace `$(git describe --tags --abbrev=0)` with the version tag manually if not on a tagged commit (e.g. `1.2.3`).

### Multi-arch build (amd64 + arm64, for release)

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

The `Dockerfile` exposes a `PHP_VERSION` build argument (default `8.4`):

```bash
docker build --build-arg PHP_VERSION=8.3 --tag ghcr.io/akeeba/panopticon:8.3 .
```

---

## Testing the Image

### 1. Create the test directory structure

`docker-compose.override.yml` (in the repo root) binds `./docker-testing/` as persistent data volumes instead of named Docker volumes, making data easily inspectable and disposable.

```bash
mkdir -p docker-testing/user_code docker-testing/config docker-testing/db
```

### 2. Create `.env.docker`

The entrypoint reads this file. Create it from the distributed example:

```bash
cp .env.dist .env.docker
```

Then edit `.env.docker` with at minimum:

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

GHCR packages are private by default. To make it publicly pullable:

1. Go to `https://github.com/akeeba/panopticon/pkgs/container/panopticon`
2. Click **Package settings**
3. Under **Danger Zone**, change visibility to **Public**

### Automate with GitHub Actions (optional)

To trigger a build-and-push automatically on every new tag, add `.github/workflows/docker.yml`:

```yaml
name: Publish Docker image

on:
  push:
    tags: ['*']

jobs:
  push:
    runs-on: ubuntu-latest
    permissions:
      packages: write
      contents: read
    steps:
      - uses: actions/checkout@v4

      - name: Set up QEMU (for arm64 emulation)
        uses: docker/setup-qemu-action@v3

      - name: Set up Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Install PHP dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Extract tags
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/akeeba/panopticon
          tags: |
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=raw,value=latest

      - name: Build and push
        uses: docker/build-push-action@v6
        with:
          context: .
          platforms: linux/amd64,linux/arm64
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          provenance: false
```

Push this file, then the next `git push --tags` will trigger it automatically.
