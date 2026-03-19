# Casino Guru Parsing — Full Documentation

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Architecture and Data Flow](#2-architecture-and-data-flow)
3. [Required Software and Dependencies](#3-required-software-and-dependencies)
4. [Stage 1 — URL List Preparation](#4-stage-1--url-list-preparation)
5. [Stage 2 — Main Scraper (main.py)](#5-stage-2--main-scraper-mainpy)
6. [Stage 2a — Lightweight Parser (parser.py)](#6-stage-2a--lightweight-parser-parserpy)
7. [Stage 3 — Image Scraping](#7-stage-3--image-scraping)
8. [Stage 4 — Content Rewriting](#8-stage-4--content-rewriting)
9. [Stage 5 — Publishing to WordPress](#9-stage-5--publishing-to-wordpress)
10. [Utilities and Helper Scripts](#10-utilities-and-helper-scripts)
11. [Output JSON Structure](#11-output-json-structure)
12. [Casino Guru CSS Selectors (Detailed Map)](#12-casino-guru-css-selectors-detailed-map)
13. [Known Issues and Limitations](#13-known-issues-and-limitations)
14. [Project File Structure](#14-project-file-structure)

---

## 1. Project Overview

The project performs a full pipeline:

```
Casino URL list → Selenium scraping of casino.guru → JSON files → Content rewriting → WordPress (REST API)
```

Casino Guru is a website with online casino reviews. Review pages are rendered dynamically via JavaScript, so the main scraper uses **Selenium** (controlling a real Chrome browser) rather than plain HTTP requests.

All scraping is done with Selenium + BeautifulSoup.

---

## 2. Architecture and Data Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PREPARATION                                     │
│                                                                         │
│  casino_names_extracted.txt                                             │
│        │                                                                │
│        ▼                                                                │
│  transform_casino_urls.py  →  casino_urls.txt                          │
│        │                                                                │
│        ▼                                                                │
│  match_scraped_urls.py  →  unscraped_urls.txt  (URLs to scrape)        │
│                            scraped_urls.txt     (already processed)     │
└─────────────────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       SCRAPING (main.py)                                │
│                                                                         │
│  Chrome (Selenium WebDriver)                                            │
│    → Loads casino.guru/{name}-review                                    │
│    → Waits 10 sec (for VPN)                                             │
│    → Extracts 9 data blocks                                             │
│    → Saves to JSON: json_files/{name}CasinoReview.json                  │
└─────────────────────────────────────────────────────────────────────────┘
                    │
            ┌───────┴───────┐
            ▼               ▼
┌──────────────────┐ ┌──────────────────────────────┐
│ IMAGE SCRAPING   │ │ CONTENT REWRITING             │
│                  │ │                               │
│ scrape_logos.py  │ │ text_generator/ (Text Gen API)│
│ scrape_payment_*.│ │ gpt api/gpt_rewrite.py (GPT)  │
│ scrape_game_*.py │ │                               │
│    ↓             │ │    ↓                          │
│ images/logos/    │ │ processed_jsons/              │
│ images/payment_* │ │ text_generator/completed_*    │
│ images/game_*    │ │                               │
└──────────────────┘ └──────────────────────────────┘
            │               │
            └───────┬───────┘
                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    WORDPRESS                                            │
│                                                                         │
│  send_to_wp.py / parser.py                                              │
│    → POST to REST API (custom/v1/create-entry or casino/v1/add/)       │
│    → Template: casino_review_template.php                               │
│    → Data from JSON is injected into PHP template                       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Required Software and Dependencies

### Local Software

| Software | Purpose | Configuration |
|----------|---------|---------------|
| **Google Chrome** (portable) | Browser for Selenium | Set via `CHROME_PATH` env variable |
| **ChromeDriver** | WebDriver to control Chrome | Set via `CHROME_DRIVER_PATH` env variable |
| **Python 3.x** | Script runtime | System-installed |
| **VPN** | Access to casino.guru (script is adapted) | Any VPN client |

**IMPORTANT**: The ChromeDriver version must exactly match the Chrome version. When Chrome is updated, ChromeDriver must also be updated.

### Python Dependencies

Full list (not all are listed in requirements.txt):

```
selenium                  # Main scraping tool (WebDriver)
webdriver-manager         # Auto-managed ChromeDriver (used in main.py)
requests                  # HTTP requests (image downloads, API calls)
beautifulsoup4>=4.9.3     # HTML parsing (lightweight parser, rewriting)
openai>=1.0.0             # GPT API for content rewriting
tqdm==4.66.1              # Progress bar (check_casino_urls.py)
pathlib>=1.0.1            # File path handling
```

### Installation

```bash
pip install selenium webdriver-manager requests beautifulsoup4 openai tqdm
```

### External APIs (with keys)

| API | Purpose | Authentication |
|-----|---------|----------------|
| **WordPress REST API** | Page publishing | Bearer token (see `.env`) |
| **WordPress Custom API** | Casino entry creation | Same |
| **Text Generator API** | Content rewriting | OAuth2 client_credentials (see `.env`) |
| **OpenAI GPT-4** | Alternative rewriting | API key (see `.env`) |

---

## 4. Stage 1 — URL List Preparation

### Data Source

File `casino_names_extracted.txt` — a list of casino names (one per line).

### Script: `utils/transform_casino_urls.py`

Transforms casino names into Casino Guru URLs:

```
Casino name:  "Bitcasino.io"
URL:          https://casino.guru/Bitcasino-io-review
```

Algorithm:
1. Trim whitespace
2. Replace spaces with hyphens
3. Prepend `https://casino.guru/` and append `-review`

Output: `casino_urls.txt`

### Script: `utils/match_scraped_urls.py`

Compares already scraped JSONs against the full URL list:

1. Reads JSON files from `json_files/`
2. Normalizes names (CamelCase → lowercase, removes "Casino", "Review")
3. Compares with URLs from `casino_urls.txt`
4. Generates:
   - `scraped_urls.txt` — already scraped
   - `unscraped_urls.txt` — not yet scraped (input for main.py)
   - `unmatched_json_names.txt` — JSONs with no matching URL

Format of `unscraped_urls.txt` (first 3 lines are header, skipped when reading):
```
=== UNSCRAPED URLs ===
Total unscraped: 42

https://casino.guru/casino-name-review
...
```

### Script: `utils/check_casino_urls.py`

Checks URL availability (HTTP status codes). Saves results to `casino_urls_status.csv`.

---

## 5. Stage 2 — Main Scraper (`main.py`)

### General Process

1. Reads URLs from `unscraped_urls.txt` (skipping 3 header lines)
2. Checks if a JSON for this casino already exists
3. Initializes Chrome via Selenium
4. For each URL:
   - Loads the page, waits 10 sec (VPN)
   - Extracts 9 data blocks
   - Saves JSON
   - Optionally sends to WordPress API
   - Pauses 5–10 sec between requests
5. Up to 3 retries per URL

### Chrome Initialization (Details)

```python
options = Options()
options.add_argument("--disable-gpu")
options.add_argument("--window-size=1920x1080")
options.add_argument("--no-sandbox")
options.add_argument("--disable-dev-shm-usage")
options.add_argument("--disable-blink-features=AutomationControlled")  # anti-detection

# VPN-specific settings
options.add_argument("--dns-prefetch-disable")
options.add_argument("--disable-features=NetworkService")
options.add_argument("--disable-features=NetworkServiceInProcess")
options.add_argument("--disable-features=IsolateOrigins,site-per-process")
options.add_argument("--disable-site-isolation-trials")

# Additional stability settings
options.add_argument("--disable-extensions")
options.add_argument("--disable-popup-blocking")
options.add_argument("--disable-notifications")
options.add_argument("--disable-infobars")
options.add_argument("--disable-web-security")
options.add_argument("--ignore-certificate-errors")

# Custom user data directory (to preserve cache/cookies)
options.add_argument(f"--user-data-dir={user_data_dir}")

# Page load timeout — 30 seconds
driver.set_page_load_timeout(30)
```

Mode is **NOT headless** (browser is visible). Headless mode is used for image scraping.

### Data Extraction — 9 Blocks

#### Block 1: `detail_info` — General Casino Information

| Field | CSS Selector / XPath | Type |
|-------|---------------------|------|
| `casino_name` | `h1` inside `.casino-detail-info-col` | text |
| `safety_index` | `.rating` | text ("9.0/10") |
| `safety_rating` | `.text-reputation` | text ("SAFETY INDEX VERY HIGH...") |
| `user_feedback` | `.text-bold.text-uppercase.fs-l` | text ("BAD", "MIXED") |
| `user_reviews_count` | XPath `//p[contains(text(), 'Rated by')]` → split()[-2] | text |
| `accepts_vietnam` | `.middle` | text |
| `payment_methods` | XPath `//ul[@class='flex flex-wrap']//img` → `alt` attribute | list |
| `owner` | XPath `//label[contains(text(), 'Owner')]/following-sibling::b` | text |
| `established` | XPath `//label[contains(text(), 'Established')]/following-sibling::b` | text |
| `estimated_annual_revenues` | XPath `.//div[contains(@class, 'info-col-section-revenues')]//label[...]` | text |
| `licensing_authorities` | `.license-list` | list |

**Withdrawal limits** — extracted with separate logic:
1. Find the section via XPath `//div[contains(text(), 'Withdrawal limits')]/parent::div`
2. For each `.fs-m` element:
   - Period (`.fs-xs`): "per day", "per week", "per month" → "per " is removed
   - Amount (`.neo-fs-20`)
3. If period-based limits are not found → fallback to `.fs-m.text-bold`
4. Result: `{"day": "$1,000", "week": "$2,500", "month": "$15,000"}` or a string

#### Block 2: `main_content` — Review HTML

1. Find `.casino-detail-box-description`
2. **Scroll to element**: `scrollIntoView()`
3. **Expand "Read more"**: click `span:contains('Read more')` via JS
4. Wait 2 sec
5. Get `outerHTML` — the full review HTML block

**Limitation**: If the "Read more" button is not found, the content will be truncated (only first paragraphs).

#### Block 3: `bonuses` — Casino Bonuses

1. Find `.casino-detail-box-bonuses`
2. Get all `.casino-detail-bonus-card`
3. For each card:
   - **Bonus type** (`.bonus-type`): "NO DEPOSIT BONUS", "DEPOSIT BONUS"
   - **Name** (`.bonus-name-1`): "75% up to €200..."
   - **Additional info** (`.bonus-name-2`)
   - **Availability**: check for `not-available` class
4. For available bonuses — **extract T&C** (Terms & Conditions):
   - Click the `.info` button (info icon)
   - Wait 1 sec for the tooltip to appear
   - Find `[data-tippy-root] .bonus-lines-tooltip`
   - For each `.bonus-conditions-line` → parse text:
     - "Minimum deposit:" → `minimum_deposit`
     - "Wagering requirements:" → `wagering_requirements`
     - "Maximum bet:" → `maximum_bet`
     - "Bonus expiration:" → `expiration`
     - "Free spins:" → `free_spins_details`
     - "Free spins conditions:" → `free_spins_conditions`
     - "Value of free spins:" → `free_spins_value`
     - "Maximum amount that can be won" → `maximum_win`
     - Contains "18+" or "Terms apply" → `additional_terms`
   - Close tooltip: JS removal of `[data-tippy-root]`

**Limitation**: The tooltip uses the **Tippy.js** library — the popup is attached to `[data-tippy-root]`, not to the element itself. 5-second timeout for it to appear.

#### Block 4: `games` — Available Games

Two-step strategy:

**Step 1 — Initial view** (backup):
1. Find `.casino-detail-box-games`
2. All `.casino-game-genre-item` → for each:
   - Name from `span`
   - Availability: check for `c-grey-7` class on span
   - If name starts with "No " → unavailable

**Step 2 — Expanded view** (preferred):
1. Click `[data-ga-id='casDet_overview_btn_allGames']` ("Show all")
2. Wait 2 sec
3. Look for popup: `[data-tippy-root] .casino-card-available-games-ul`
4. If not found → fallback: `.casino-card-available-games-ul` within the section
5. For each `.casino-open-icons-item`:
   - Name from text
   - Availability: `active` class + `c-green` on SVG
6. Close popup (`.js-tippy-close` or JS removal)
7. Deduplication and sorting

#### Block 5: `language_options` — Languages

1. Find `.casino-detail-box-languages`
2. For each `.language-option`:
   - Determine type from `.middle` text: "website", "customer support", "live chat"
   - Click `[data-toggle='popover-with-header']` ("All languages")
   - Wait 1 sec
   - Find popup: `[data-tippy-root] .popover-languages`
   - Extract all `.flex.items-center` → `span:last-child` text
   - Close popup
   - Pause 0.5 sec between language sections

#### Block 6: `game_providers` — Game Providers

1. Find `.casino-detail-box-game-providers`
2. Click `[data-toggle='popover-with-header']` ("Show all")
3. Wait 2 sec
4. Find `[data-tippy-root] .casino-detail-logos-item`
5. For each: `a[title]` → `title` attribute = provider name
6. Fallback: `.casino-detail-logos-item` within the section
7. Deduplication and sorting

#### Block 7: `screenshots` — Casino Screenshots

XPath: `//div[contains(@class, 'screenshot')]//img` → `src` attribute

#### Block 8: `pros_cons` — Pros, Cons, and Facts

1. Find `.casino-detail-box-pros`
2. For each `.col`:
   - Header from `h3` → determines type: "positive", "negative", "interesting fact"
   - All `li > div` → text content

### File Naming

All non-alphanumeric characters are removed from the casino name:
```
"0x.bet Casino Review"  →  "0xbetCasinoReview.json"
"1win Casino Review"    →  "1winCasinoReview.json"
```

### Logging

- Log file: `logs/scraping_YYYYMMDD_HHMMSS.log`
- Console: only the current URL
- File: detailed information about each step

### Retry Logic

- Page loading: on timeout — refresh + wait 10 sec
- Each URL: up to 3 retries with a 5-sec pause
- JSON saving: up to 3 retries with a 2-sec pause

---

## 6. Stage 2a — Lightweight Parser (`parser.py`)

An alternative parser without Selenium — **requests + BeautifulSoup**.

### Differences from main.py

| Feature | main.py | parser.py |
|---------|----------|-----------|
| Engine | Selenium (Chrome) | requests + BeautifulSoup |
| JS rendering | Yes (full) | No |
| Popups/tooltips | Yes (clicks, waits) | No |
| Data completeness | Full (9 blocks) | Minimal |
| Bonus T&C | Yes | Empty arrays |
| Languages (from popup) | Yes | Empty arrays |
| Providers (from popup) | Yes | Empty arrays |
| Speed | Slower (browser) | Faster |

### What parser.py Extracts

- `detail_info`: only `casino_name` (h1) and `safety_index` (.rating)
- `main_content`: HTML from `.casino-detail-box-description`
- Other blocks: empty templates

### WordPress Integration

parser.py has built-in integration:
- Custom API: `POST /wp-json/casino/v1/add/`
- Standard REST API: `POST /wp-json/wp/v2/pages` (fallback)
- Mapping `casino_name → page_id` is saved to `casino_name_to_page_id.json`

---

## 7. Stage 3 — Image Scraping

### 7.1 Casino Logos (`scrapers/scrape_logos.py`)

- **Mode**: Selenium headless
- **Input**: `casino_urls.txt`
- **Process**: For each URL:
  1. Load the page, wait 5 sec
  2. Find `div.casino-detail-logo img.casino-logo`
  3. Get `src` or `data-src`
  4. Casino name from `.h1-wrapper`
  5. Download and save as `logo_{casinoName}.{ext}`
- **Output**: `images/logos/`

### 7.2 Payment Methods (`scrapers/scrape_payment_methods.py`)

- **Mode**: Selenium headless
- **Source**: a single page — `https://casino.guru/Bitcasino-io-review` (large casino with many methods)
- **Process**:
  1. Load the page, wait 10 sec
  2. Find all `li.casino-detail-logos-item`
  3. For each: `picture img` → `alt` (name) + `src` (image)
  4. Download without query string (`url.split('?')[0]`)
- **Output**: `images/payment_methods/`
- **Full version**: `scrapers/scrape_payment_methods_full.py` — same but for the complete list

### 7.3 Game Providers (`scrapers/scrape_game_providers.py`)

- **Mode**: Selenium headless
- **Source**: `https://casino.guru/Bitcasino-io-review`
- **Process**:
  1. Load the page, wait 10 sec
  2. Find all `li.casino-detail-logos-item`
  3. For each: `data-ga-param` → provider name, `picture img` → image
  4. Download
- **Output**: `images/game_providers/`
- **Full version**: `scrapers/scrape_game_providers_full.py`

### 7.4 Missing Logos (`scrapers/scrape_missing_logos.py`)

Uses requests + BeautifulSoup (no Selenium). Downloads logos for specific casinos listed manually.

### File Format Detection

All scripts determine the extension from Content-Type:
```
'svg'  → .svg
'png'  → .png
'jpeg' → .jpg
else   → extension from URL or .img
```

---

## 8. Stage 4 — Content Rewriting

### 8.1 Text Generator API (`text_generator/text_generator.py`)

Corporate API for text rewriting.

**Workflow:**
1. **Authentication**: OAuth2 client_credentials → access_token (cached for 50 min)
   - URL: see `.env` (`TEXT_GENERATOR_AUTH_URL`)
2. **Submit content**: `SubmitContent` → receive `job_id` + `version`
3. **Start processing**: `StartProcessing` → begin processing
4. **Check status**: `CheckStatus` (POST, not GET!) → statuses:
   - `REWRITE_STATE_CHECKING_UNIQUENESS`
   - `REWRITE_STATE_COMPLETED`
   - `REWRITE_STATE_ERROR`
5. **Get result**: from `response['variants'][0]['content']`

**Processing parameters:**
- Style: `"formal"`
- Concurrency: 5 threads
- Batch size: 10 files
- Pause between batches: 2 sec

**Launch modes:**
```bash
python text_generator.py --collect-tokens   # Only collect job IDs
python text_generator.py --fetch-results    # Fetch completed results
python text_generator.py --batch-size 20    # Process with a different batch size
```

**Tokens are saved** to `job_tokens.txt`:
```
filename|job_id|version|timestamp
```

### 8.2 GPT Content Processing (`gpt api/gpt_rewrite.py`)

Alternative rewriting via OpenAI API.

**Process:**
1. Reads JSON
2. Extracts the `content` field
3. Cleans HTML via BeautifulSoup → plain text
4. Sends to GPT-4 Turbo with a prompt:
   > (system prompt for content processing — see source code)
5. Temperature: 0.7, max_tokens: 2000
6. Saves to `processed_jsons/processed_{name}.json`

---

## 9. Stage 5 — Publishing to WordPress

### API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /wp-json/custom/v1/create-entry` | Create entry via custom plugin |
| `POST /wp-json/casino/v1/add/` | Add casino via dedicated plugin |
| `POST /wp-json/wp/v2/pages` | Standard WordPress REST API (fallback) |

### Script: `wordpress/send_to_wp.py`

Main script for sending JSON to WordPress:

1. Reads all JSONs from `json_files/`
2. For each:
   - Extracts `casino_name`, removes "Review"
   - Generates slug: `casino-name` (lowercase + hyphens)
   - Template: `casino_review_template.php`
   - `json_data` — full casino JSON
3. POST request with Bearer token

### PHP Templates

| Template | Description |
|----------|-------------|
| `casino_review_template.php` | Main template (clean, data-driven from JSON) |
| `casinos_cards.php` | Casino list (cards) |
| `casino_cards_v2.php` | Cards v2 |

---

## 10. Utilities and Helper Scripts

| Script | Purpose |
|--------|---------|
| `utils/transform_casino_urls.py` | Casino names → Casino Guru URLs |
| `utils/match_scraped_urls.py` | Compare scraped JSONs against full URL list |
| `utils/check_casino_urls.py` | Check HTTP status codes of URLs → CSV |
| `utils/compare_logos.py` | Find casinos missing a downloaded logo |
| `utils/compare_providers.py` | Compare providers in JSON vs downloaded images |
| `utils/rename_logos.py` | Rename logo files (remove "logo_" prefix) |
| `utils/process_game_providers.py` | Sanitize provider file names |

---

## 11. Output JSON Structure

```json
{
    "detail_info": {
        "casino_name": "0x.bet Casino Review",
        "safety_index": "9.0/10",
        "safety_rating": "SAFETY INDEX VERY HIGH...",
        "user_feedback": "BAD",
        "user_reviews_count": "15",
        "accepts_vietnam": "Accepts players from...",
        "payment_methods": ["VISA", "Mastercard", "..."],
        "withdrawal_limits": {
            "day": "$1,000",
            "week": "$2,500",
            "month": "$15,000"
        },
        "owner": "SkyGrow Group Limitada",
        "established": "2022",
        "estimated_annual_revenues": "> 10,000,000...",
        "licensing_authorities": ["Costa Rica"]
    },

    "main_content": "<div class=\"casino-detail-box-description\">...full review HTML...</div>",

    "bonuses": {
        "NO DEPOSIT BONUS": [{
            "name": "75% up to €200...",
            "additional_info": "",
            "is_available": true,
            "terms_and_conditions": {
                "minimum_deposit": "€25, Maximum cashout: €400",
                "wagering_requirements": "40x bonus...",
                "maximum_bet": "€5 or 10%...",
                "expiration": "3 days",
                "free_spins_details": "100 spins on...",
                "free_spins_conditions": "40x WR...",
                "additional_terms": "18+ • New players..."
            }
        }]
    },

    "games": {
        "available_games": ["Baccarat", "Blackjack", "Slots", "..."],
        "unavailable_games": ["poker"]
    },

    "language_options": {
        "website_languages": ["English", "Spanish", "..."],
        "customer_support_languages": ["English", "..."],
        "live_chat_languages": ["English", "..."]
    },

    "game_providers": {
        "providers": ["1X2 Gaming", "Amatic", "..."]
    },

    "screenshots": [
        "https://static.casino.guru/pict/967390/..."
    ],

    "pros_cons": {
        "positives": ["Extensive collection of games..."],
        "negatives": ["Limited responsible gaming..."],
        "interesting_facts": ["Live chat support uses..."]
    }
}
```

---

## 12. Casino Guru CSS Selectors (Detailed Map)

### Main Page Blocks

| Block | Selector |
|-------|----------|
| Info column | `.casino-detail-info-col` |
| Description/review | `.casino-detail-box-description` |
| Bonuses | `.casino-detail-box-bonuses` |
| Games | `.casino-detail-box-games` |
| Languages | `.casino-detail-box-languages` |
| Providers | `.casino-detail-box-game-providers` |
| Pros/cons | `.casino-detail-box-pros` |

### Elements Within Blocks

| Element | Selector |
|---------|----------|
| Casino name | `h1` inside `.casino-detail-info-col` |
| Safety rating | `.rating` |
| Reputation text | `.text-reputation` |
| User feedback | `.text-bold.text-uppercase.fs-l` |
| Payment icons | `ul.flex.flex-wrap img[alt]` |
| Casino logo | `div.casino-detail-logo img.casino-logo` |
| Bonus card | `.casino-detail-bonus-card` |
| Bonus type | `.bonus-type` |
| Bonus name | `.bonus-name-1` |
| T&C button | `.info` (inside bonus card) |
| T&C tooltip | `[data-tippy-root] .bonus-lines-tooltip` |
| Condition line | `.bonus-conditions-line` |
| Game genre | `.casino-game-genre-item` |
| "Show all" games | `[data-ga-id='casDet_overview_btn_allGames']` |
| Games popup list | `[data-tippy-root] .casino-card-available-games-ul` |
| Game item in popup | `.casino-open-icons-item` |
| Language option | `.language-option` |
| "All languages" button | `[data-toggle='popover-with-header']` |
| Languages popup | `[data-tippy-root] .popover-languages` |
| Provider item | `.casino-detail-logos-item` |
| Provider name | `.casino-detail-logos-item a[title]` |
| Screenshot | `div[class*='screenshot'] img[src]` |
| Pros/cons column | `.casino-detail-box-pros .col` |
| "Read more" button | `span:contains('Read more')` |
| Close popup | `.js-tippy-close` |

### Popup System (Tippy.js)

Casino Guru uses Tippy.js for all popups. Key details:
- Popups render inside `[data-tippy-root]` (outside the button's DOM element)
- Close via: `.js-tippy-close` or JS: `document.querySelector('[data-tippy-root]').remove()`
- Requires JS click (not Selenium `.click()`): `driver.execute_script("arguments[0].click();", element)`

---

## 13. Known Issues and Limitations

### Scraping

1. **VPN is required** — casino.guru blocks certain regions. The script includes special Chrome flags for VPN compatibility.

2. **Timeouts** — pages are heavy (JS, images). Hardcoded `time.sleep(10)` for initial page load. May not be enough with a slow VPN.

3. **Tippy.js popups** — game, language, and provider data is hidden inside popups. Requires JS click → wait → parse → close. If the popup doesn't appear within 5 sec, the data is skipped.

4. **"Read more" button** — if not found or unclickable, `main_content` will be truncated (only first paragraphs).

5. **Anti-bot protection** — `--disable-blink-features=AutomationControlled` is used, but mass scraping may trigger blocks. Random 5–10 sec pause between requests.

6. **Payment methods duplicates** — JSONs may contain duplicates (deposit + withdrawal sections are separate but collected together). Deduplication is not implemented for payment_methods.

7. **withdrawal_limits inconsistency** — can be an object `{day, week, month}` or a string `"Not Limited for VND"`. The PHP template must handle both variants.

8. **Providers "No providers found"** — if the popup didn't open, the JSON contains `["No providers found"]`.

### Chrome/ChromeDriver

9. **Chrome paths** — Chrome and ChromeDriver paths are configured via `CHROME_PATH` and `CHROME_DRIVER_PATH` environment variables. Set them in `.env` or ensure the executables are in your system PATH.

10. **Chrome/ChromeDriver versions** — must match. If Chrome is updated, scraping will break until ChromeDriver is also updated.

11. **User data directory** — Chrome creates a profile in `~/chrome_automation_data`. It may accumulate data over time.

### WordPress

12. **Bearer token** — a single token for all requests with no expiry in code. When changed, it must be updated in the `.env` file.

13. **Template** — tied to `casino_review_template.php`. If the template is not installed in WordPress, pages will be created without styling.

### Text Generator API

14. **CheckStatus method** — documentation says GET, but only POST actually works.

15. **Result in variants** — Processed content is in `response['variants'][0]['content']`, not in `rewrittenContent` as originally expected.

---

## 14. Project File Structure

```
casino-guru-main/
│
├── utils/
│   ├── main.py                       # [MAIN] Selenium casino scraper
│   ├── transform_casino_urls.py      # Casino names → Casino Guru URLs
│   ├── match_scraped_urls.py         # Compare scraped JSONs vs URL list
│   ├── check_casino_urls.py          # Check HTTP status codes → CSV
│   ├── compare_logos.py              # Find casinos missing logos
│   ├── compare_providers.py          # Compare providers: JSON vs images
│   ├── rename_logos.py               # Rename logo files
│   └── process_game_providers.py     # Sanitize provider filenames
│
├── scrapers/
│   ├── parser.py                     # [ALT] Lightweight requests parser
│   ├── scrape_logos.py               # Scrape casino logos
│   ├── scrape_missing_logos.py       # Download missing logos
│   ├── scrape_payment_methods.py     # Scrape payment method icons
│   ├── scrape_payment_methods_full.py# Same, full list
│   ├── scrape_game_providers.py      # Scrape game provider logos
│   └── scrape_game_providers_full.py # Same, full list
│
├── wordpress/
│   ├── send_to_wp.py                 # Send JSON to WordPress
│   ├── casino-review-importer.php    # WP plugin for importing
│   ├── ex_wp_api.php                 # WP API example
│   ├── functions.php                 # Theme functions
│   ├── single-casino_review.php      # Single review template
│   ├── casino-review-styles.css      # Review page styles
│   └── carousel_script.js            # Carousel JS
│
├── text_generator/
│   ├── text_generator.py             # Text Generator API client
│   ├── fetch_rewrites.py             # Fetch completed results
│   ├── retrieve_jobs.py              # Inspect jobs
│   └── check_existing_jobs.py        # Check job statuses
│
├── gpt api/
│   ├── gpt_rewrite.py                # GPT-4 content processing
│   ├── requirements.txt              # Python dependencies
│   └── README.md                     # Plugin documentation
│
├── data/
│   ├── casino_names.txt              # Casino names list
│   ├── casino_names_extracted.txt    # Extracted names
│   ├── casino_urls_status.csv        # URL status check results
│   └── ...                           # Other data files
│
├── images/
│   ├── logos/                        # Casino logos
│   ├── payment_methods/              # Payment method icons
│   ├── payment_methods_full/         # Extended set
│   ├── game_providers/               # Provider logos
│   └── game_providers_full/          # Extended set
│
├── json_files/                       # Scraping results (JSON per casino)
├── processed_jsons/                  # GPT/API rewrite results
├── logs/                             # Scraping logs
│
├── README.md                         # Project README
└── PARSING_DOCUMENTATION.md          # ← YOU ARE HERE
```

---

## Quick Start

### 1. Setup

```bash
# Install dependencies
pip install selenium webdriver-manager requests beautifulsoup4 openai tqdm

# Download Chrome and ChromeDriver (matching versions)
# Set CHROME_PATH and CHROME_DRIVER_PATH in .env
```

### 2. Prepare URLs

```bash
# If you have a list of casino names:
python utils/transform_casino_urls.py

# To determine what hasn't been scraped yet:
python utils/match_scraped_urls.py
```

### 3. Scraping

```bash
# Enable VPN

# Start scraping
python utils/main.py
```

### 4. Image Scraping

```bash
python scrapers/scrape_logos.py
python scrapers/scrape_payment_methods.py
python scrapers/scrape_game_providers.py
```

### 5. Content Rewriting (optional)

```bash
# Text Generator API:
cd text_generator
python text_generator.py --collect-tokens
python text_generator.py --fetch-results

# Or GPT:
cd "gpt api"
python gpt_rewrite.py
```

### 6. Publish to WordPress

```bash
python wordpress/send_to_wp.py
```
