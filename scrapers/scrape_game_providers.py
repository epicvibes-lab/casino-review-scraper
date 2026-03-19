from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
import os
import requests
import time
import re

# Base directory for images
base_dir = 'images'
game_providers_folder = os.path.join(base_dir, 'game_providers')
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

url = 'https://casino.guru/Bitcasino-io-review'

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
            with open(filename, 'wb') as f:
                f.write(response.content)
            print(f"Saved: {filename}")
            return True
        else:
            print(f"Failed to download {url} (status {response.status_code})")
    except Exception as e:
        print(f"Error downloading {url}: {e}")
    return False

try:
    print(f"Loading page: {url}")
    driver.get(url)
    time.sleep(10)  # Wait longer for all images to load
    print("Finding all game provider logo items...")
    items = driver.find_elements(By.CSS_SELECTOR, 'li.casino-detail-logos-item')
    print(f"Found {len(items)} game provider items.")

    # Print HTML of first 3 items for debugging
    for idx, item in enumerate(items[:3], 1):
        print(f"\nHTML of item {idx}:")
        print(item.get_attribute('outerHTML'))

    total_downloaded = 0
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
            if download_image(img_url.split('?')[0], game_providers_folder, provider_name):
                total_downloaded += 1
        except Exception as e:
            print(f"Error processing item {i}: {e}")
    print(f"\nDone! Downloaded {total_downloaded} provider images.")
finally:
    driver.quit() 