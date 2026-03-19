import os
import requests
from bs4 import BeautifulSoup
import time
import re

def sanitize_filename(name):
    # Convert to lowercase
    name = name.lower()
    # Remove special characters and spaces
    name = re.sub(r'[^a-z0-9]', '', name)
    return name

def download_logo(url, target_dir):
    try:
        # Add headers to mimic a browser
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        # Get the casino name from URL
        casino_name = url.split('/')[-1].replace('-Casino-review', '')
        print(f"\nChecking {casino_name} at {url}")
        
        # Make the request
        response = requests.get(url, headers=headers)
        response.raise_for_status()
        
        # Parse the HTML
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Print page title to verify we're on the right page
        title = soup.find('title')
        if title:
            print(f"Page title: {title.text.strip()}")
        
        # Look for any images that might be logos
        all_images = soup.find_all('img')
        logo_candidates = []
        
        for img in all_images:
            # Check various attributes that might indicate a logo
            src = img.get('src', '')
            alt = img.get('alt', '')
            class_name = img.get('class', [])
            
            # Print any image that might be a logo
            if any(x in str(img).lower() for x in ['logo', 'brand', casino_name.lower()]):
                logo_candidates.append(img)
                print(f"Found potential logo: src='{src}', alt='{alt}', class='{class_name}'")
        
        if not logo_candidates:
            print(f"No logo candidates found for {casino_name}")
            return False
            
        # Try to find the best logo candidate
        logo_img = None
        for img in logo_candidates:
            # Prefer images with 'logo' in class or alt text
            if 'logo' in str(img).lower():
                logo_img = img
                break
        if not logo_img and logo_candidates:
            logo_img = logo_candidates[0]
            
        # Get the image URL
        img_url = logo_img.get('src')
        if not img_url:
            print(f"No image URL found for {casino_name}")
            return False
            
        # Handle relative URLs
        if img_url.startswith('//'):
            img_url = 'https:' + img_url
        elif img_url.startswith('/'):
            img_url = 'https://casino.guru' + img_url
            
        # Get the image extension
        ext = os.path.splitext(img_url)[1]
        if not ext:
            ext = '.png'  # Default to .png if no extension found
            
        # Create filename
        filename = sanitize_filename(casino_name) + ext
        filepath = os.path.join(target_dir, filename)
        
        # Download the image
        img_response = requests.get(img_url, headers=headers)
        img_response.raise_for_status()
        
        # Save the image
        with open(filepath, 'wb') as f:
            f.write(img_response.content)
            
        print(f"Successfully downloaded logo for {casino_name}")
        return True
        
    except Exception as e:
        print(f"Error downloading logo for {casino_name}: {str(e)}")
        return False

def main():
    # URLs for missing logos
    urls = [
        'https://casino.guru/DBbet-Casino-review',
        'https://casino.guru/GreatSpin-Casino-review',
        'https://casino.guru/Pelican-Casino-review',
        'https://casino.guru/ReSpin-Casino-review',
        'https://casino.guru/Rivalry-Casino-review'
    ]
    
    # Create target directory if it doesn't exist
    target_dir = 'images/logos_remake'
    os.makedirs(target_dir, exist_ok=True)
    
    # Download each logo
    for url in urls:
        success = download_logo(url, target_dir)
        if success:
            # Add a small delay between requests to be polite
            time.sleep(1)
    
    print("\nLogo scraping completed!")

if __name__ == '__main__':
    main() 