import requests
import time
import os
from urllib3.exceptions import InsecureRequestWarning
from requests.exceptions import RequestException
import csv
from tqdm import tqdm

# Suppress only the single InsecureRequestWarning
requests.packages.urllib3.disable_warnings(category=InsecureRequestWarning)

def check_url_status(url):
    try:
        # Add headers to mimic a browser request
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        # Make request with a timeout and verify=False to ignore SSL
        response = requests.get(url, headers=headers, timeout=10, verify=False)
        return response.status_code
    except RequestException as e:
        return f"Error: {str(e)}"

def main():
    # Get current directory
    current_dir = os.getcwd()
    output_file = os.path.join(current_dir, 'casino_urls_status.csv')
    
    print(f"Working directory: {current_dir}")
    print(f"Output will be saved to: {output_file}")
    
    # Read URLs from file
    with open('casino_urls.txt', 'r') as file:
        urls = file.readlines()
    
    # Strip whitespace from URLs
    urls = [url.strip() for url in urls]
    
    # Prepare results file
    try:
        with open(output_file, 'w', newline='') as csvfile:
            writer = csv.writer(csvfile)
            writer.writerow(['URL', 'Status Code'])  # Write header
            
            # Process each URL with progress bar
            results = []  # Store results in memory
            for url in tqdm(urls, desc="Checking URLs"):
                status = check_url_status(url)
                results.append([url, status])
                writer.writerow([url, status])
                csvfile.flush()  # Force write to disk
                # Small delay to be nice to servers
                time.sleep(0.5)
        
        # Verify file was created
        if os.path.exists(output_file):
            print(f"\nFile successfully created at: {output_file}")
            print(f"File size: {os.path.getsize(output_file)} bytes")
            
            # Print first few results
            print("\nFirst 5 results:")
            for url, status in results[:5]:
                print(f"{url}: {status}")
        else:
            print("\nError: File was not created!")
            
    except Exception as e:
        print(f"\nError while writing to file: {str(e)}")

if __name__ == "__main__":
    print("Starting URL status check...")
    main()
    print("\nScript execution completed.") 