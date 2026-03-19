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
payment_methods_folder = os.path.join(base_dir, 'payment_methods_full')
os.makedirs(payment_methods_folder, exist_ok=True)
print(f"Created folder: {payment_methods_folder}")

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
    # Remove invalid filename characters and trim spaces
    return re.sub(r'[^a-zA-Z0-9_\-\. ]', '', name.strip())

def download_image(url, folder, alt_text):
    try:
        print(f"Attempting to download image for {alt_text} from: {url}")
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
            filename = os.path.join(folder, f"{sanitize_filename(alt_text)}{ext}")
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

def get_payment_method_images_from_url(url, folder):
    print(f"\nLoading page: {url}")
    driver.get(url)
    time.sleep(10)  # Wait for content to load
    items = driver.find_elements(By.CSS_SELECTOR, 'li.casino-detail-logos-item')
    print(f"Found {len(items)} payment method items.")
    new_downloads = 0
    for i, item in enumerate(items, 1):
        try:
            img_tag = item.find_element(By.CSS_SELECTOR, 'picture img')
            alt_text = img_tag.get_attribute('alt')
            img_url = img_tag.get_attribute('src')
            if not img_url:
                img_url = img_tag.get_attribute('data-src')
            if not img_url or not alt_text:
                print(f"Item {i}: Missing image URL or alt text, skipping.")
                continue
            if download_image(img_url.split('?')[0], folder, alt_text):
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
            total_new_downloads += get_payment_method_images_from_url(url, payment_methods_folder)
        except Exception as e:
            print(f"Error scraping {url}: {e}")
    print(f"\nDone! Downloaded {total_new_downloads} new payment method images.")

if __name__ == '__main__':
    try:
        main()
    finally:
        driver.quit() 