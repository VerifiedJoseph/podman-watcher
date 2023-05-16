# podman-watcher

PHP script for checking podman container images for updates and then sending push notifications via Gotify.

## Configuration

Use `config.php` (copied from [`config.example.php`](config.example.php)) to configure `podman-watcher`.

| Name                | Description                             |
| ------------------- | --------------------------------------- |
| `GOTIFY_SERVER`     | Gotify server address                   |
| `GOTIFY_TOKEN`      | Gotify application token                |
| `IGNORE_REGISTRIES` | Array of image registries to not check. |

## Requirements

- [podman](https://github.com/containers/podman)
- [skopeo](https://github.com/containers/skopeo)
- PHP >= 8.0
- PHP Extensions:
  - [`JSON`](https://www.php.net/manual/en/book.json.php)
  - [`cURL`](https://secure.php.net/manual/en/book.curl.php)