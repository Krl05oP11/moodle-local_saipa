# Running Behat locally for local_saipa

The host machine has no PHP or Composer installed, and these scenarios
exercise `block_saipa`'s UI, not just `local_saipa`'s — so the recipe below
is more than a plain `moodle-plugin-ci behat`. It is fully isolated from the
persistent dev stack (`docker/docker-compose.yml`, `saipa-postgres`,
`saipa-moodle`): everything here runs in a throwaway Moodle site and a
throwaway Postgres container, and never touches the dev DB.

## Known gap: `ci.yml` does not install `blocks/saipa`

`chat_student.feature` and `telegram_linking.feature` render and click on
`block_saipa` UI. `.github/workflows/ci.yml` only installs `local_saipa`
itself (`moodle-plugin-ci install --plugin ./plugin`) — it never checks out
the sibling `blocks/saipa` repo (`Krl05oP11/moodle-block_saipa`, no CI/Behat
of its own). Without it, the block never attaches to the page and every
block-based scenario fails, regardless of how correct the step definitions
are. This recipe works around it locally via `--extra-plugins`; making the
real CI pass needs a `ci.yml` change that checks out that sibling repo
(private — needs a token/deploy key) — a decision for the repo owner, not
made here.

## One-time image build

```bash
mkdir -p ~/Projects/SAIPA/.behat-ci
cat > ~/Projects/SAIPA/.behat-ci/Dockerfile <<'EOF'
FROM php:8.1-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl locales \
        libpq-dev libzip-dev libpng-dev libxml2-dev libxslt1-dev libicu-dev libonig-dev \
        chromium chromium-driver postgresql-client docker-cli \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_pgsql pgsql zip gd soap intl xsl mbstring \
    && docker-php-ext-configure intl

RUN echo "en_AU.UTF-8 UTF-8" >> /etc/locale.gen && locale-gen

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo "max_input_vars=5000" > /usr/local/etc/php/conf.d/max_input_vars.ini \
    && echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/memory_limit.ini

ENV NVM_DIR=/root/.nvm
RUN curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash \
    && . "$NVM_DIR/nvm.sh" && nvm install 18 && nvm alias default 18

WORKDIR /work
EOF
docker build -t saipa-behat-ci ~/Projects/SAIPA/.behat-ci
```

## Throwaway Postgres (separate from `saipa-postgres`)

```bash
docker run -d --name saipa-behat-pg -p 127.0.0.1:5433:5432 \
  -e POSTGRES_USER=moodle -e POSTGRES_PASSWORD=moodle -e POSTGRES_DB=moodle \
  postgres:14
```

## Stage `blocks/saipa` as an extra plugin

`moodle-plugin-ci install --extra-plugins` expects a *flat* directory of
`<component>/version.php` trees (not nested by Moodle plugin type):

```bash
mkdir -p ~/Projects/SAIPA/.behat-ci/extra-plugins/block_saipa
rsync -a --no-perms --no-owner --no-group --exclude='.git' \
  ~/Projects/SAIPA/moodle-plugins/blocks/saipa/ \
  ~/Projects/SAIPA/.behat-ci/extra-plugins/block_saipa/
```

## Install (fresh Moodle core + local_saipa + block_saipa)

Must run with `--network host` and the Docker socket mounted: the
`--profile chrome` Behat run spins up its own `selenium/standalone-chrome`
container, and that container needs host networking to reach the Moodle
site — this is undocumented upstream, found empirically.

```bash
docker run --rm --network host \
  -v ~/Projects/SAIPA/.behat-ci/work:/work \
  -v ~/Projects/SAIPA/moodle-plugins/local_saipa:/work/plugin:ro \
  -v ~/Projects/SAIPA/.behat-ci/extra-plugins:/work/extra-plugins:ro \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -w /work \
  saipa-behat-ci bash -lc '
export PATH="/work/ci/bin:/work/ci/vendor/bin:$PATH"
moodle-plugin-ci install --plugin ./plugin --moodle ./moodle --data ./moodledata \
  --extra-plugins ./extra-plugins --db-type pgsql --db-host 127.0.0.1 --db-port 5433 \
  --db-name moodle --db-user moodle --db-pass moodle --branch MOODLE_404_STABLE -n
'
```

This takes several minutes (full Moodle core clone). The install creates the
`moodle` database itself — don't pre-create it, or `CREATE DATABASE` fails.

## Iterating on plugin/test changes without a full reinstall

Edit files in the real repo, then re-sync into the installed copy (it's a
one-time copy at install, not a live mount) and re-run:

```bash
rsync -a --no-perms --no-owner --no-group --delete --exclude='.git' \
  ~/Projects/SAIPA/moodle-plugins/local_saipa/ \
  ~/Projects/SAIPA/.behat-ci/work/moodle/local/saipa/

docker run --rm --network host \
  -v ~/Projects/SAIPA/.behat-ci/work:/work \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -w /work \
  saipa-behat-ci bash -lc '
export PATH="/work/ci/bin:/work/ci/vendor/bin:$PATH"
moodle-plugin-ci behat --profile chrome --tags @local_saipa -n
'
```

Run one feature at a time while debugging: `--tags @local_saipa_chat`,
`@local_saipa_dashboard`, or `@local_saipa_telegram`.

**If you add or change a `tests/behat/behat_*.php` step-definition class**,
Moodle's Behat context list is cached at install time and does not pick up
new PHP files automatically — re-run init after syncing:

```bash
docker run --rm --network host -v ~/Projects/SAIPA/.behat-ci/work:/work -w /work \
  saipa-behat-ci php /work/moodle/admin/tool/behat/cli/init.php -vvv
```

## Cleanup

```bash
docker rm -f saipa-behat-pg
docker run --rm -v ~/Projects/SAIPA/.behat-ci/work:/work saipa-behat-ci \
  bash -lc 'rm -rf /work/moodle /work/moodledata /work/node_modules'
```
