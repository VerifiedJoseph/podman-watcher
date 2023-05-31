# podman-watcher

PHP script for being notified when podman container images have updates using [Skopeo](https://github.com/containers/skopeo) and [Gotify](https://gotify.net/).

## Configuration

Use `config.php` (copied from [`config.example.php`](config.example.php)) to configure.

| Name                |Required | Description                             |
| --------------------|-------- | --------------------------------------- |
| `GOTIFY_SERVER`     | Yes     | Gotify server address                   |
| `GOTIFY_TOKEN`      | Yes     | Gotify application token                |
| `IGNORE_IMAGES`     | No      | Array of container images to not check. |
| `IGNORE_REGISTRIES` | No      | Array of image registries to not check. |

## Requirements

- [podman](https://github.com/containers/podman) >= 3.4.2
- [skopeo](https://github.com/containers/skopeo) >= 1.5.0
- [gotify/server](https://github.com/gotify/server) >= 2.2.4
- PHP >= 8.0
- PHP Extensions:
  - [`JSON`](https://www.php.net/manual/en/book.json.php)
  - [`cURL`](https://secure.php.net/manual/en/book.curl.php)
