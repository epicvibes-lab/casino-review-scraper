import os
import time
import json
import re
import traceback
import requests
from bs4 import BeautifulSoup
import shutil

# Configuration
JSON_SAVE_DIR = os.path.join(os.getcwd(), "json_files")
try:
    os.makedirs(JSON_SAVE_DIR, exist_ok=True)
    print(f"[INFO] Created/verified JSON directory at: {JSON_SAVE_DIR}")
except Exception as e:
    print(f"[ERROR] Failed to create JSON directory: {e}")
    raise  # Re-raise the exception since we can't proceed without a valid directory

WP_BASE_URL = os.getenv("WP_BASE_URL", "https://example.com")
WP_API_URL = f"{WP_BASE_URL}/wp-json/casino/v1/add/"
WP_PAGES_API_URL = f"{WP_BASE_URL}/wp-json/wp/v2/pages"
BEARER_TOKEN = os.getenv("WP_API_TOKEN", "")

# Casino name to page ID mapping
MAPPING_FILE = os.path.join(JSON_SAVE_DIR, "casino_name_to_page_id.json")
try:
    with open(MAPPING_FILE, "r", encoding="utf-8") as f:
        casino_mapping = json.load(f)
except FileNotFoundError:
    casino_mapping = {}

# Clean text helper function
def clean_text(text):
    if text:
        return " ".join(text.split())
    return ""

# Function to scrape casino data (simplified for brevity)
def scrape_casino_data(url):
    print(f"[INFO] Scraping: {url}")
    data = {}
    try:
        session = requests.Session()
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.9',
            'Connection': 'keep-alive'
        }
        response = session.get(url, headers=headers, timeout=30)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        
        data["detail_info"] = {
            "casino_name": soup.select_one('h1').text if soup.select_one('h1') else "Not found",
            "safety_index": soup.select_one('.rating').text if soup.select_one('.rating') else "Not found",
        }
        data["main_content"] = str(soup.select_one('.casino-detail-box-description')) or "<div>No content</div>"
        data["bonuses"] = {"NO DEPOSIT BONUS": [], "DEPOSIT BONUS": []}
        data["games"] = {"available_games": []}
        data["language_options"] = {"website_languages": [], "customer_support_languages": [], "live_chat_languages": []}
        data["game_providers"] = {"providers": []}
        data["pros_cons"] = {"positives": [], "negatives": [], "interesting_facts": []}
        return data
    except Exception as e:
        print(f"[ERROR] Failed to scrape {url}: {e}")
        traceback.print_exc()
        return {
            "error": f"Failed to scrape {url}: {str(e)}",
            "casino_name": url.split('/')[-1].replace('-review', '').replace('-', ' ').title()
        }

# Function to extract casino name from URL
def extract_casino_name(url):
    casino_name = url.split('/')[-1].replace('-review', '').replace('-', ' ').title()
    return casino_name

# Function to save data as JSON
def save_as_json(data, filename, copy_to_theme=False):
    filepath = os.path.join(JSON_SAVE_DIR, filename)
    try:
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=4, ensure_ascii=False)
        if not os.path.exists(filepath):
            print(f"[ERROR] File was not created: {filepath}")
            return None
        file_size = os.path.getsize(filepath)
        print(f"[INFO] Saved JSON to {filepath} (size: {file_size} bytes)")
        if copy_to_theme:
            theme_json_dir = os.getenv("WP_THEME_JSON_DIR", os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "json_files"))
            try:
                os.makedirs(theme_json_dir, exist_ok=True)
                shutil.copy(filepath, os.path.join(theme_json_dir, filename))
                print(f"[INFO] Copied JSON to {theme_json_dir}/{filename}")
            except Exception as e:
                print(f"[ERROR] Failed to copy to theme directory: {e}")
        return filepath
    except PermissionError as e:
        print(f"[ERROR] Permission denied when saving {filename}: {e}")
        return None
    except Exception as e:
        print(f"[ERROR] Failed to save JSON: {e}")
        return None

# Function to test WordPress API connection
def test_wp_api():
    print("[INFO] Testing WordPress API connection...")
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {BEARER_TOKEN}"
    }
    payload = {"test": True, "message": "API Connection Test"}
    try:
        response = requests.post(WP_API_URL, json=payload, headers=headers, timeout=10)
        if response.status_code == 200:
            print("[INFO] WordPress API connection successful!")
            return True
        else:
            print(f"[ERROR] WordPress API test failed with status code {response.status_code}")
            print(f"Response: {response.text}")
            return False
    except Exception as e:
        print(f"[ERROR] WordPress API test failed: {e}")
        return False

# Function to send data to WordPress custom API
def send_to_wp_api(data, casino_name):
    print(f"[INFO] Sending {casino_name} data to WordPress API...")
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {BEARER_TOKEN}"
    }
    slug = casino_name.lower().replace(' ', '-')
    payload = {
        "casino_name": casino_name,
        "data": data,
        "force_publish": True,
        "slug": slug,
        "post_title": f"{casino_name} Review - Complete Guide & Ratings",
        "post_status": "publish",
        "page_template": "Casino Full JSON Template"
    }
    try:
        response = requests.post(WP_API_URL, json=payload, headers=headers, timeout=20)
        print(f"[DEBUG] Custom API response status: {response.status_code}")
        print(f"[DEBUG] Custom API response: {response.text}")
        if response.status_code in (200, 201):
            try:
                response_data = response.json()
                page_id = response_data.get('id') or response_data.get('page_id')
                permalink = response_data.get('permalink') or response_data.get('link')
                if page_id:
                    print(f"[INFO] Page ID: {page_id}")
                    # Save with just the casino name, no page ID
                    json_filename = f"{casino_name.replace(' ', '_')}.json"
                    save_as_json(data, json_filename, copy_to_theme=True)
                    casino_mapping[casino_name] = {"page_id": page_id, "permalink": permalink or f"/{slug}"}
                    with open(MAPPING_FILE, "w", encoding="utf-8") as f:
                        json.dump(casino_mapping, f, indent=2, ensure_ascii=False)
                    return page_id
                else:
                    print("[ERROR] Page ID not found in response")
                    return None
            except ValueError:
                print("[ERROR] Invalid JSON response from API")
                return None
        else:
            print(f"[ERROR] Failed to send {casino_name} data. Status code: {response.status_code}")
            print(f"Response: {response.text}")
            return None
    except Exception as e:
        print(f"[ERROR] Failed to send {casino_name} data to WordPress API: {e}")
        return None

# Fallback: Create page using standard WordPress REST API
def create_wp_page(data, casino_name):
    print(f"[INFO] Creating WordPress page for {casino_name} using standard REST API...")
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {BEARER_TOKEN}"
    }
    slug = casino_name.lower().replace(' ', '-')
    content = format_content_from_data(data, casino_name)
    page_data = {
        "title": f"{casino_name} Review - Complete Guide & Ratings",
        "slug": slug,
        "status": "publish",
        "content": content,
        "template": "Casino Full JSON Template"
    }
    try:
        response = requests.post(WP_PAGES_API_URL, json=page_data, headers=headers, timeout=20)
        print(f"[DEBUG] Standard API response status: {response.status_code}")
        print(f"[DEBUG] Standard API response: {response.text}")
        if response.status_code == 201:
            response_data = response.json()
            page_id = response_data.get('id')
            permalink = response_data.get('link')
            if page_id:
                print(f"[INFO] Page ID: {page_id}, Permalink: {permalink}")
                # Save with just the casino name, no page ID
                json_filename = f"{casino_name.replace(' ', '_')}.json"
                save_as_json(data, json_filename, copy_to_theme=True)
                casino_mapping[casino_name] = {"page_id": page_id, "permalink": permalink}
                with open(MAPPING_FILE, "w", encoding="utf-8") as f:
                    json.dump(casino_mapping, f, indent=2, ensure_ascii=False)
                return page_id
            return None
        else:
            print(f"[ERROR] Failed to create page. Status code: {response.status_code}")
            print(f"Response: {response.text}")
            return None
    except Exception as e:
        print(f"[ERROR] Failed to create WordPress page: {e}")
        return None

# Function to format page content from JSON data
def format_content_from_data(data, casino_name):
    content = f"<!-- wp:heading --><h2>{casino_name} Review</h2><!-- /wp:heading -->"
    if "detail_info" in data:
        details = data["detail_info"]
        if "safety_index" in details:
            content += f"\n<!-- wp:paragraph --><p><strong>Safety Index:</strong> {details['safety_index']}</p><!-- /wp:paragraph -->"
    return content

# Main function
def main():
    print("[INFO] Starting Casino Guru Scraper")
    if not os.path.exists(JSON_SAVE_DIR):
        print(f"[ERROR] JSON directory does not exist: {JSON_SAVE_DIR}")
        return
        
    if not test_wp_api():
        print("[WARNING] WordPress API test failed. Will try standard REST API.")
    urls_to_process = [
        "https://casino.guru/RioBet-Casino-review",
        "https://casino.guru/Gamdom-Casino-review",
        "https://casino.guru/EnergyCasino-review",
        "https://casino.guru/Megapari-Casino-review",
    ]
    successful_uploads = 0
    successful_scrapes = 0
    for i, url in enumerate(urls_to_process, 1):
        print(f"\n[INFO] Processing URL {i}/{len(urls_to_process)}: {url}")
        casino_name = extract_casino_name(url)
        json_filename = f"{casino_name.replace(' ', '_')}.json"
        json_filepath = os.path.join(JSON_SAVE_DIR, json_filename)

        # Load or scrape data
        if os.path.exists(json_filepath):
            print(f"[INFO] Loading existing JSON for {casino_name}")
            try:
                with open(json_filepath, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                print(f"[INFO] Successfully loaded JSON from {json_filepath}")
            except Exception as e:
                print(f"[ERROR] Failed to load existing JSON: {e}")
                data = None
        else:
            print(f"[INFO] Scraping new data for {casino_name}")
            data = scrape_casino_data(url)
            
        if not data:
            print(f"[ERROR] No data available for {casino_name}, skipping...")
            continue
            
        # Save the scraped data
        saved_path = save_as_json(data, json_filename)
        if not saved_path:
            print(f"[ERROR] Failed to save JSON for {casino_name}, skipping...")
            continue
        successful_scrapes += 1

        # Check if page already exists in mapping
        if casino_name in casino_mapping and "page_id" in casino_mapping[casino_name]:
            print(f"[INFO] Page already exists for {casino_name}, Page ID: {casino_mapping[casino_name]['page_id']}")
            if save_as_json(data, json_filename, copy_to_theme=True):
                successful_uploads += 1
            continue

        # Try custom API first
        page_id = send_to_wp_api(data, casino_name)
        if not page_id:
            print(f"[INFO] Custom API failed, trying standard REST API for {casino_name}")
            page_id = create_wp_page(data, casino_name)
        if page_id:
            successful_uploads += 1
        time.sleep(2)

    print(f"\n[INFO] Processing completed!")
    print(f"Successfully scraped: {successful_scrapes}/{len(urls_to_process)}")
    print(f"Successfully published: {successful_uploads}/{len(urls_to_process)}")
    print(f"JSON files location: {JSON_SAVE_DIR}")
    print("Files in directory:")
    try:
        for file in os.listdir(JSON_SAVE_DIR):
            size = os.path.getsize(os.path.join(JSON_SAVE_DIR, file))
            print(f"- {file} ({size} bytes)")
    except Exception as e:
        print(f"[ERROR] Failed to list directory contents: {e}")

if __name__ == "__main__":
    main()