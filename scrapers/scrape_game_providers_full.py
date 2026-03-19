import os
import re
import requests
import time
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By

# Base directory for images
base_dir = 'images'
game_providers_folder = os.path.join(base_dir, 'game_providers_full')
os.makedirs(game_providers_folder, exist_ok=True)
print(f"Created folder: {game_providers_folder}")

# Set up Chrome options
chrome_options = Options()
chrome_options.add_argument('--headless')
chrome_options.add_argument('--disable-gpu')
chrome_options.add_argument('--window-size=1920,1080')
chrome_options.add_argument('--ignore-certificate-errors')
chrome_options.add_argument('--allow-running-insecure-content')
chrome_options.add_argument('--disable-extensions')
chrome_options.add_argument('--proxy-server="direct://"')
chrome_options.add_argument('--proxy-bypass-list=*')
chrome_options.add_argument('--start-maximized')
chrome_options.add_argument('--no-sandbox')
chrome_options.add_argument('--disable-dev-shm-usage')

# Set paths for Chrome and ChromeDriver
chrome_path = os.getenv("CHROME_PATH", "chrome.exe")
chrome_driver_path = os.getenv("CHROME_DRIVER_PATH", "chromedriver.exe")
chrome_options.binary_location = chrome_path
service = Service(executable_path=chrome_driver_path)
driver = webdriver.Chrome(service=service, options=chrome_options)

def sanitize_filename(name):
    # Remove invalid filename characters
    return re.sub(r'[^a-zA-Z0-9_\-\. ]', '', name.replace(' ', '_'))

def download_image(url, folder, provider_name):
    try:
        print(f"Attempting to download image for {provider_name} from: {url}")
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers)
        if response.status_code == 200:
            content_type = response.headers.get('Content-Type', '')
            if 'svg' in content_type.lower():
                ext = '.svg'
            elif 'png' in content_type.lower():
                ext = '.png'
            elif 'jpeg' in content_type.lower() or 'jpg' in content_type.lower():
                ext = '.jpg'
            else:
                ext = os.path.splitext(url.split('?')[0])[1] or '.img'
            filename = os.path.join(folder, f"{sanitize_filename(provider_name)}{ext}")
            if os.path.exists(filename):
                print(f"File already exists, skipping: {filename}")
                return False
            with open(filename, 'wb') as f:
                f.write(response.content)
            print(f"Saved: {filename}")
            return True
        else:
            print(f"Failed to download {url} (status {response.status_code})")
    except Exception as e:
        print(f"Error downloading {url}: {e}")
    return False

def get_provider_images_from_url(url, folder):
    print(f"\nLoading page: {url}")
    driver.get(url)
    time.sleep(10)  # Wait for all images to load
    print("Finding all game provider logo items...")
    items = driver.find_elements(By.CSS_SELECTOR, 'li.casino-detail-logos-item')
    print(f"Found {len(items)} game provider items.")
    new_downloads = 0
    for i, item in enumerate(items, 1):
        try:
            provider_name = item.get_attribute('data-ga-param')
            if not provider_name:
                print(f"Item {i}: No provider name found, skipping.")
                continue
            img_tag = item.find_element(By.CSS_SELECTOR, 'picture img')
            img_url = img_tag.get_attribute('src')
            if not img_url:
                img_url = img_tag.get_attribute('data-src')
            if not img_url:
                print(f"Item {i}: No image URL found for {provider_name}, skipping.")
                continue
            if download_image(img_url.split('?')[0], folder, provider_name):
                new_downloads += 1
        except Exception as e:
            print(f"Error processing item {i}: {e}")
    return new_downloads

def main():
    # Read all URLs from casino_urls.txt
    urls_file = 'casino_urls.txt'
    if not os.path.exists(urls_file):
        print(f"File not found: {urls_file}")
        return
    with open(urls_file, 'r', encoding='utf-8') as f:
        urls = [line.strip() for line in f if line.strip()]
    print(f"Loaded {len(urls)} URLs from {urls_file}")
    total_new_downloads = 0
    for url in urls:
        try:
            total_new_downloads += get_provider_images_from_url(url, game_providers_folder)
        except Exception as e:
            print(f"Error scraping {url}: {e}")
    print(f"\nDone! Downloaded {total_new_downloads} new provider images.")

if __name__ == '__main__':
    try:
        main()
    finally:
        driver.quit() 