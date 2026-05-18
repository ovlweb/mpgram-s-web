# MPGram S Web

Modified lightweight Telegram Web Client based on MadelineProto, that could connect to MyTelegram based servers.

## Setup

1. Install the requirements for your deployment type:
   - Docker deployment: Docker Engine and Docker Compose.
   - Manual deployment: PHP 8.4+, Composer v2+, nginx or another web server, and the PHP extensions listed below.
2. Create a Telegram app at [https://my.telegram.org/apps](https://my.telegram.org/apps) and get your `api_id` and `api_hash`.
3. Run the setup script from the project root:

```bash
sh setup-client.sh public
```

Available modes:

- `public` - normal Telegram MTProto mode.
- `mytelegram` - MyTelegram/MTG-server-compatible mode.
- `dual` - Docker mode that runs public Telegram MTProto and MyTelegram/MTG clients side by side.

The script creates `api_values.php`, copies the correct `config.php` template, and prepares Docker environment files when needed.

For non-interactive setup, pass values as environment variables:

```bash
API_ID=12345 API_HASH=your_api_hash sh setup-client.sh public
API_ID=12345 API_HASH=your_api_hash MYTELEGRAM_HOST=10.0.0.10 MYTELEGRAM_PORT=30444 sh setup-client.sh mytelegram
```

For MyTelegram/MTG mode, prefer a direct LAN IP for `MYTELEGRAM_HOST`, for example `10.0.0.10`, instead of `host.docker.internal`.

## Deployment

### Docker

After running setup, start the Docker stack:

```bash
cd docker
docker compose up --build -d
```

Default ports:

- `public` mode: configured by `docker/.env`, usually `http://127.0.0.1:8081`.
- `mytelegram` mode: configured by `docker/.env`, usually `http://127.0.0.1:8082`.

To run both modes at once:

```bash
sh setup-client.sh dual
cd docker
docker compose --env-file .env.dual -f docker-compose.dual.yml up --build -d
```

Dual mode defaults:

- Public Telegram MTProto: `http://127.0.0.1:8081`
- MyTelegram/MTG-server mode: `http://127.0.0.1:8082`

Useful Docker files:

- `docker/.env.public.example` - public Telegram MTProto defaults.
- `docker/.env.mytelegram.example` - MyTelegram/MTG-server defaults.
- `docker/docker-compose.yml` - single client.
- `docker/docker-compose.dual.yml` - public and MyTelegram clients together.

HTTPS is supported by setting `PROTO=https` in the Docker env file and placing `fullchain.pem` and `privkey.pem` into `docker/nginx/ssl/`.

### Manual deployment

1. Run setup:

```bash
sh setup-client.sh public
```

or:

```bash
sh setup-client.sh mytelegram
```

2. Install PHP extensions:

```text
gd mbstring xml json fileinfo gmp iconv ffi
```

3. Install dependencies:

```bash
composer install
```

If you are updating dependencies or installing from a fresh MadelineProto version, apply the bundled patches:

```bash
patch -p0 < patches/InternalDoc.php.patch
patch -p0 < patches/Files.php.patch
patch -p0 < patches/UpdateHandler.php.patch
```

4. Make sure the web server user can write to the configured session directory:

- `s/` for the default config.
- `s-public/` for public mode in Docker-style config.
- `s-mytelegram/` for MyTelegram mode in Docker-style config.

5. Deny public access to session folders and `MadelineProto.log`.
6. Recommended PHP settings:

```ini
session.gc_maxlifetime = 8640000
browscap = /path/to/browscap.ini
```

7. Point your web server document root to this project directory.

For MyTelegram/MTG mode, edit `config.php` or set environment variables:

```bash
MPGRAM_CONNECTION=mytelegram
PRIVATE_SERVER_HOST=10.0.0.10
PRIVATE_SERVER_PORT=30444
PRIVATE_SERVER_USE_WSS=false
```

For public Telegram mode:

```bash
MPGRAM_CONNECTION=public
PRIVATE_SERVER_HOST=
```

## Animated stickers conversion

Docker builds include `gifski` and `lottie-converter` automatically. Animated sticker and animated emoji/status conversion is enabled through environment variables in the Docker Compose files:

```bash
CONVERT_TGS_STICKERS=true
LOTTIE_DIR=/opt/lottie/bin
LOTTIE_TO_GIF=true
```

For manual deployment:

1. Install `gifski`.
2. Download and unpack [lottie-converter](https://github.com/ed-asriyan/lottie-converter/releases).
3. Make sure the web server user, usually `www-data`, can execute the converter files.
4. Edit `lottie_to_gif.sh` and `lottie_to_png.sh`, and add this as the first line if it is missing:

```bash
#!/usr/bin/env bash
```

5. Set conversion options in `config.php`:

```php
define('CONVERT_TGS_STICKERS', true);
define('LOTTIE_DIR', '/opt/lottie/bin/');
define('LOTTIE_TO_GIF', true);
```

`LOTTIE_DIR` must point to the directory that contains `lottie_to_gif.sh` and `lottie_to_png.sh`.

## Browser support

This fork is based on the original lightweight client, but MPGram S Web now uses newer layout and UI features such as flexbox, sticky headers, `querySelector/querySelectorAll`, standard event handlers with old fallbacks, XHR/ActiveX fallbacks, and iframes. Because of that, the original retro browser list is now split into real support levels.

### Supported

- Modern Chromium/Chrome
- Modern Firefox
- Modern Safari
- Modern Microsoft Edge
- Modern mobile browsers based on Chromium or WebKit
- Legacy Edge 15-18 / Windows 10 Mobile Edge, including Lumia devices

### Expected to work

These browsers should run the client, but are not part of the regular test loop:

- WebPositive on recent Haiku builds
- BlackBerry 10 Browser
- Legacy Edge 12-14
- Firefox 59 and newer
- Opera 78 and newer
- Safari 9 and newer

### Degraded/basic support

These browsers may load the app and basic pages, but the modern two-pane layout, sticky bars, animations, or some settings UI may be broken or simplified:

- Internet Explorer 10-11
- Internet Explorer 9
- Opera 12.1 / Opera Mobile 12.1
- Firefox 28-58
- Nokia Browser for Symbian / S60
- BlackBerry OS7 Browser
- S40 6th Edition

For old mobile platforms, HTTP may work better than HTTPS because many devices no longer have usable modern TLS certificates.

### Not supported

- Internet Explorer 8 and older
- Firefox 2.0-3.0
- Opera 9.0-12.0
- Opera Mini, all versions
- S40 5th Edition or older
- Internet Explorer Mobile
- NetFront Browser 4.1 for Samsung
