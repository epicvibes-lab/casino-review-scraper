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
logos_folder = os.path.join(base_dir, 'logos')
os.makedirs(logos_folder, exist_ok=True)
print(f"Created folder: {logos_folder}")

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

def download_image(url, folder, name, prefix=''):
    try:
        print(f"Attempting to download image for {name} from: {url}")
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
            filename = os.path.join(folder, f"{prefix}{sanitize_filename(name)}{ext}")
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

def get_casino_logo(url, folder):
    print(f"\nLoading page: {url}")
    driver.get(url)
    time.sleep(5)  # Wait for content to load
    
    try:
        # Use the improved selector for the logo
        logo_elements = driver.find_elements(By.CSS_SELECTOR, 'div.casino-detail-logo img.casino-logo')
        print(f"Found {len(logo_elements)} logo elements")
        
        for logo_element in logo_elements:
            try:
                logo_url = logo_element.get_attribute('src')
                if not logo_url:
                    logo_url = logo_element.get_attribute('data-src')
                
                if logo_url:
                    # Try to get casino name from the closest .h1-wrapper after the logo
                    casino_name = None
                    try:
                        parent = logo_element.find_element(By.XPATH, './ancestor::div[contains(@class, "casino-detail-info-col")]')
                        name_element = parent.find_element(By.CSS_SELECTOR, '.h1-wrapper')
                        casino_name = name_element.text.strip()
                    except Exception as e:
                        print(f"Could not find .h1-wrapper for casino name: {e}")
                        casino_name = f"casino_{int(time.time())}"
                    if download_image(logo_url.split('?')[0], folder, casino_name, 'logo_'):
                        return True
            except Exception as e:
                print(f"Error processing logo element: {e}")
                continue
    except Exception as e:
        print(f"Error getting casino logo: {e}")
    return False

def main():
    # Read URLs from file
    with open('casino_urls.txt', 'r') as f:
        urls = [line.strip() for line in f if line.strip()]
    
    total_urls = len(urls)
    successful_downloads = 0
    
    print(f"Starting to process {total_urls} casino URLs...")
    
    for i, url in enumerate(urls, 1):
        print(f"\nProcessing URL {i}/{total_urls}: {url}")
        try:
            if get_casino_logo(url, logos_folder):
                successful_downloads += 1
        except Exception as e:
            print(f"Error processing {url}: {e}")
        
        # Add a small delay between requests to be nice to the server
        time.sleep(2)
    
    print(f"\nProcessing complete!")
    print(f"Successfully downloaded {successful_downloads} out of {total_urls} casino logos")

if __name__ == '__main__':
    try:
        main()
    finally:
        driver.quit() 