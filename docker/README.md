# Your own MPGram S Web in 5 minutes

## Requirements

1. Docker Engine (see [instructions](https://docs.docker.com/engine/install/ubuntu/))
2. [docker-compose](https://github.com/docker/compose).

## Configuration

Generate your Telegram `api_id` and `api_hash` at https://my.telegram.org/apps, then run one of:

```
sh ../setup-client.sh public
sh ../setup-client.sh mytelegram
sh ../setup-client.sh dual
```

Manual setup is still simple:

- Create `../api_values.php` from `../api_values.php.example`
- For public Telegram MTProto, create `../config.php` from `../config.public.php.example`; this loads `connection.mtproto.php`
- For MyTelegram/MTG-server mode, create `../config.php` from `../config.mytelegram.php.example`; this loads `connection.mtg-server.php`

Then:

```
cp .env.example .env
```

For public mode you can also start from `.env.public.example`; for MyTelegram/MTG mode start from `.env.mytelegram.example`. Edit ports, bind IP, and MyTelegram host as needed. Prefer a direct LAN IP, for example `10.0.0.10`, over `host.docker.internal` so MadelineProto does not retry Docker-only DNS names through DoH.

### HTTP (default)

You are all set!

```
docker-compose up --build -d
```

Your MPGram S Web instance will await you on the configured HTTP port.

### Dual public/MyTelegram check

To run both connection modes at once:

```
sh ../setup-client.sh dual
docker-compose --env-file .env.dual -f docker-compose.dual.yml up --build -d
```

Defaults:

- Public Telegram MTProto: http://127.0.0.1:8081
- MyTelegram/MTG-server mode: http://127.0.0.1:8082

### HTTPS (recommended)

* Place your SSL chain certificate (named `fullchain.pem`) and private key (named `privkey.pem`) into `nginx/ssl`
* Set `PROTO=https` in `.env`

If you want to create a self-signed certificate instead of obtaining one from a CA, run:

```
openssl req -x509 -nodes -days 365 -subj "/C=CA/ST=QC/O=Company, Inc./CN=mydomain.com" -addext "subjectAltName=DNS:mydomain.com" -newkey rsa:2048 -keyout nginx/ssl/privkey.pem -out nginx/ssl/fullchain.pem
```

Add a `-sha1` option if you are targeting a retro platform like Symbian S60. **Warning**: [SHA1 is insecure!](https://en.wikipedia.org/wiki/SHA-1#Attacks).

Run with `docker-compose` as per above.

Happy MPGram'ing!
