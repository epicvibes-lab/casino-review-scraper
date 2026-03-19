import os
import re

# Special casino names that should keep "casino" in their name
SPECIAL_CASINO_NAMES = {
    'betjili',
    'casinoplanet',
    'luckytiger',
    'plgbet',
    'red'
}

# URLs that should be excluded from unscraped list (found or closed)
EXCLUDED_URLS = {
    'https://casino.guru/marvelbet-casino-review',  # Betjili Casino
    'https://casino.guru/lucky-tiger-casino-review',  # Lucky Tiger Casino
    'https://casino.guru/csgopolygon-casino-review',  # PLGBET Casino
    'https://casino.guru/24play-casino-review',  # Red Casino
    # CasinoPlanet is closed, no URL needed
}

# Paths
JSON_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "json_files")
CASINO_URLS_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "data", "casino_urls_status.csv")
OUTPUT_DIR = os.path.dirname(os.path.abspath(__file__))

def normalize_name(name):
    # Split camelCase into words (e.g. "BetjiliCasinoReview" -> "Betjili Casino Review")
    name = re.sub(r'([a-z])([A-Z])', r'\1 \2', name)
    # Convert to lowercase
    name = name.lower()
    
    # Check if this is one of our special casino names
    base_name = re.sub(r'(casinoreview|casino|review|\.json)', '', name, flags=re.IGNORECASE)
    base_name = re.sub(r'[^a-z0-9]', '', base_name)
    
    if base_name in SPECIAL_CASINO_NAMES:
        # For special names, only remove "review" and keep "casino"
        name = re.sub(r'(review|\.json)', '', name, flags=re.IGNORECASE)
    else:
        # For all other names, remove casino, review, .json suffixes
        name = re.sub(r'(casinoreview|casino|review|\.json)', '', name, flags=re.IGNORECASE)
    
    # Remove all non-alphanumeric characters
    name = re.sub(r'[^a-z0-9]', '', name)
    return name

def extract_casino_name_from_url(url):
    # Get the last part of the URL path
    name = url.rstrip('/').split('/')[-1]
    # Remove -review, -casino, and dashes/underscores
    name = re.sub(r'(-casino|-review|casino|review)', '', name, flags=re.IGNORECASE)
    name = re.sub(r'[^a-z0-9]', '', name.lower())
    return name

def main():
    print(f"Reading JSON files from: {JSON_DIR}")
    print(f"Reading URLs from: {CASINO_URLS_FILE}")

    # Get all JSON files and normalize their names
    json_files = [f for f in os.listdir(JSON_DIR) if f.lower().endswith('.json')]
    scraped_names = {normalize_name(os.path.splitext(f)[0]): f for f in json_files}
    print(f"Found {len(json_files)} JSON files.")

    # Read all URLs and filter out excluded ones
    with open(CASINO_URLS_FILE, "r", encoding="utf-8") as f:
        original_urls = [line.strip() for line in f if line.strip()]
        print(f"\nOriginal URLs in file: {len(original_urls)}")
        print("Excluded URLs:")
        for url in EXCLUDED_URLS:
            print(f"- {url}")
        
        all_urls = [url for url in original_urls if url not in EXCLUDED_URLS]
        print(f"\nURLs after excluding found/closed: {len(all_urls)}")
        print(f"Expected count: {len(original_urls) - len(EXCLUDED_URLS)}")

    url_names = {extract_casino_name_from_url(url): url for url in all_urls}

    # Match URLs to scraped names
    scraped_urls = []
    unscraped_urls = []
    for url in all_urls:
        casino_name = extract_casino_name_from_url(url)
        if casino_name in scraped_names:
            scraped_urls.append(url)
        else:
            unscraped_urls.append(url)

    print(f"\nDetailed counts:")
    print(f"Original URLs: {len(original_urls)}")
    print(f"Excluded URLs: {len(EXCLUDED_URLS)}")
    print(f"Remaining URLs: {len(all_urls)}")
    print(f"Scraped URLs: {len(scraped_urls)}")
    print(f"Unscraped URLs: {len(unscraped_urls)}")

    # Find JSON names that do not match any URL
    unmatched_json_names = [f for f in json_files if normalize_name(os.path.splitext(f)[0]) not in url_names]
    unmatched_json_output = os.path.join(OUTPUT_DIR, "unmatched_json_names.txt")
    with open(unmatched_json_output, "w", encoding="utf-8") as f:
        f.write("=== UNMATCHED JSON FILE NAMES ===\n")
        f.write(f"Total unmatched: {len(unmatched_json_names)}\n\n")
        for name in unmatched_json_names:
            f.write(f"{name}\n")

    # Save results
    scraped_output = os.path.join(OUTPUT_DIR, "scraped_urls.txt")
    unscraped_output = os.path.join(OUTPUT_DIR, "unscraped_urls.txt")

    with open(scraped_output, "w", encoding="utf-8") as f:
        f.write("=== SCRAPED URLs ===\n")
        f.write(f"Total scraped: {len(scraped_urls)}\n\n")
        for url in scraped_urls:
            f.write(f"{url}\n")

    with open(unscraped_output, "w", encoding="utf-8") as f:
        f.write("=== UNSCRAPED URLs ===\n")
        f.write(f"Total unscraped: {len(unscraped_urls)}\n\n")
        for url in unscraped_urls:
            f.write(f"{url}\n")

    print(f"\nTotal URLs in casino_urls.txt (excluding found/closed): {len(all_urls)}")
    print(f"Total JSON files in scraped directory: {len(json_files)}")
    print(f"URLs successfully scraped: {len(scraped_urls)}")
    print(f"URLs not yet scraped: {len(unscraped_urls)}")
    print(f"JSON files not matched to any URL: {len(unmatched_json_names)}")
    if unmatched_json_names:
        print("First unmatched JSON file names:")
        for name in unmatched_json_names[:10]:
            print(f"- {name}")
    print(f"\nUnmatched JSON file names saved to: {unmatched_json_output}")
    print("\nResults have been saved to:")
    print(f"- {scraped_output}")
    print(f"- {unscraped_output}")

if __name__ == "__main__":
    main() 