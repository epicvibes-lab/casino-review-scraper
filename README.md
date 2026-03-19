# Casino Review Scraper

End-to-end pipeline for scraping online casino reviews, extracting structured data, rewriting content via AI, and publishing to WordPress.

## What It Does

```
Casino URLs → Selenium scraping → Structured JSON → AI rewrite → WordPress pages
```

The scraper extracts **9 data blocks** from each casino review page:

| Block | Description |
|-------|-------------|
| `detail_info` | Name, safety rating, owner, licenses, payment methods, withdrawal limits |
| `main_content` | Full review HTML |
| `bonuses` | Deposit / no-deposit bonuses with T&C details |
| `games` | Available and unavailable game types |
| `language_options` | Website, support, and live chat languages |
| `game_providers` | List of game providers (NetEnt, Pragmatic Play, etc.) |
| `screenshots` | Casino website screenshots |
| `pros_cons` | Positives, negatives, interesting facts |

See [`examples/sample_casino_review.json`](examples/sample_casino_review.json) for the full output structure.

## Project Structure

```
├── utils/
│   ├── main.py                    # Main Selenium scraper
│   ├── transform_casino_urls.py   # Casino names → review URLs
│   ├── match_scraped_urls.py      # Track scraping progress
│   └── check_casino_urls.py       # Validate URLs (HTTP status)
│
├── scrapers/
│   ├── parser.py                  # Lightweight requests-based parser
│   ├── scrape_logos.py            # Casino logo images
│   ├── scrape_payment_methods.py  # Payment method icons
│   └── scrape_game_providers.py   # Game provider logos
│
├── text_generator/                # Text Generator API integration
│   ├── text_generator.py          # Submit content for rewriting
│   ├── fetch_rewrites.py          # Fetch completed rewrites
│   └── check_existing_jobs.py     # Monitor job statuses
│
├── gpt api/
│   └── gpt_rewrite.py             # Alternative rewriting via OpenAI GPT-4
│
├── wordpress/
│   ├── send_to_wp.py              # Publish JSON data to WordPress
│   └── ex_wp_api.php              # Custom WP REST API plugin
│
├── wp templates/
│   ├── casino_cards_v2.php        # Casino review page template
│   └── casinos_cards.php          # Casino list page template
│
├── examples/
│   └── sample_casino_review.json  # Example scraper output
│
├── .env.example                   # Required environment variables
└── PROJECT_DOCUMENTATION.md       # Detailed technical documentation
```

## Quick Start

### 1. Install dependencies

```bash
pip install selenium webdriver-manager requests beautifulsoup4 openai tqdm
```

### 2. Configure environment

```bash
cp .env.example .env
```

Edit `.env` with your values. See [`.env.example`](.env.example) for the full list of variables.

You also need **Google Chrome** and a matching **ChromeDriver** version.

### 3. Prepare URLs

```bash
python utils/transform_casino_urls.py    # Generate URLs from casino names
python utils/match_scraped_urls.py       # Find unscraped URLs
```

### 4. Run the scraper

```bash
# VPN may be required depending on your region
python utils/main.py
```

Output: one JSON file per casino in `json_files/`.

### 5. Rewrite content (optional)

```bash
# Via Text Generator API
python text_generator/text_generator.py --collect-tokens
python text_generator/text_generator.py --fetch-results

# Or via OpenAI GPT-4
python "gpt api/gpt_rewrite.py"
```

### 6. Publish to WordPress

```bash
python wordpress/send_to_wp.py
```

## Tech Stack

- **Python** — Selenium, BeautifulSoup, Requests
- **OpenAI API** / **Text Generator API** — content rewriting
- **WordPress REST API** — page publishing
- **PHP** — WordPress templates (Astra theme)

## Documentation

For detailed technical documentation (CSS selectors, scraping logic, API integration, known issues), see [`PROJECT_DOCUMENTATION.md`](PROJECT_DOCUMENTATION.md).
