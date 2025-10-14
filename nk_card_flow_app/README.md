# NK Card Flow PHP Implementation

This module is a PHP port of the workflow described in `docs/nk_card_flow.md`. It fetches product data from MoySklad, builds a National Catalogue (NK) card payload, submits it through the NK API, polls for the final status, and writes the issued GTIN back to MoySklad.

## Installation

1. Copy `config.example.php` to `config.php` and fill in the credentials:
   ```bash
   cp config.example.php config.php
   ```
2. Provide MoySklad credentials (API token or basic auth) and the NK API key.
3. Make sure PHP 8.1+ and the cURL extension are installed.

## Usage

The `bin/send_card.php` script orchestrates the entire flow:

```bash
php bin/send_card.php \
  --product-id=<uuid> \
  [--variant-id=<uuid>] \
  [--live-gtin] \
  [--producer-inn=7700000000] \
  [--name-options=article,color,size]
```

Arguments:
- `--product-id` – MoySklad product UUID. Required.
- `--variant-id` – Variation UUID if you want to upload a specific SKU. Optional.
- `--live-gtin` – Request a battle GTIN via `/v3/generate-gtins` before sending the card.
- `--producer-inn` – INN used when generating a technical GTIN. Required if `--live-gtin` is not set.
- `--name-options` – Comma separated list of fields that must be included in the composed product name (`article`, `color`, `size`).

The script logs every HTTP call to `logs/nk_api.log` and prints a compact status summary to STDOUT.

## Project Structure

- `config.php` – local credentials and attribute bindings (ignored by Git, copy from the example file).
- `src/Config` – configuration loaders and value objects.
- `src/Http` – thin HTTP client with logging.
- `src/Logger` – simple PSR-3-like logger used across services.
- `src/MoySklad` – API wrapper and attribute extraction helpers.
- `src/Nk` – NK API client, card builder, polling logic.
- `src/Services` – orchestration service that glues MoySklad and NK together.
- `bin/send_card.php` – CLI entry point.

## Logs

The default log file is `logs/nk_api.log`. Rotate or truncate it manually if needed.

## Unit Tests

The repository does not ship with automated tests yet. The code is structured so that services can be unit-tested by injecting fake HTTP clients and loggers.
