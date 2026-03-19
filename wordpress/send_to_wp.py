import os
import json
import requests
import re

WP_BASE_URL = os.getenv("WP_BASE_URL", "https://example.com")
API_URL = f"{WP_BASE_URL}/wp-json/casino/v1/add/"
API_TOKEN = os.getenv("WP_API_TOKEN", "")

# Directory configuration
ARTICLES_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "json_files")

def read_json_file(file_path):
    """Read and parse a JSON file."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            print(f"\nSuccessfully read JSON file: {file_path}")
            return data
    except json.JSONDecodeError as e:
        print(f"Error parsing JSON file {file_path}: {e}")
        return None
    except Exception as e:
        print(f"Error reading file {file_path}: {e}")
        return None

def prepare_post_data(data):
    """Prepare post data in the format expected by ex_wp_api.php (casino/v1/add/)."""
    if 'detail_info' not in data:
        print("Missing detail_info field")
        return None
    
    casino_name = data['detail_info'].get('casino_name', '')
    casino_name = re.sub(r'\s*Review\s*$', '', casino_name, flags=re.IGNORECASE)
    data['detail_info']['casino_name'] = casino_name
    
    return data

def send_to_wordpress(data):
    """Send data to WordPress API."""
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {API_TOKEN}"
    }
    
    post_data = prepare_post_data(data)
    if not post_data:
        return False, "No valid post data prepared"
    
    try:
        print("\nSending request to:", API_URL)
        response = requests.post(API_URL, json=post_data, headers=headers)
        print("\nResponse status code:", response.status_code)
        
        if response.status_code == 400:
            try:
                error_data = response.json()
                print("\nError details:")
                print(json.dumps(error_data, indent=2))
            except:
                print("Could not parse error response")
        
        response.raise_for_status()
        response_data = response.json() if response.text else None
        
        if response_data and response_data.get('status') == 'success':
            print(f"\nSuccessfully created page with ID: {response_data.get('page_id')}")
            print(f"Permalink: {response_data.get('permalink')}")
            return True, response_data
        else:
            return False, response_data or "No response data"
            
    except requests.exceptions.RequestException as e:
        print(f"\nRequest error: {str(e)}")
        return False, str(e)
    except Exception as e:
        print(f"\nUnexpected error: {str(e)}")
        return False, str(e)

if __name__ == "__main__":
    # Process all JSON files in the directory
    for filename in os.listdir(ARTICLES_DIR):
        if filename.endswith('.json'):
            file_path = os.path.join(ARTICLES_DIR, filename)
            print(f"\nProcessing {filename}...")
            data = read_json_file(file_path)
            if data:
                success, resp = send_to_wordpress(data)
                print(f"File: {filename}")
                print("Success:", success)
                print("Response:", resp)
                print("-" * 50)
