import json
import os
import time
import requests
from pathlib import Path
from datetime import datetime
import concurrent.futures
from typing import List, Dict, Tuple
import threading

# Global variables for rate limiting
MAX_CONCURRENT_REQUESTS = 5  # Maximum number of concurrent API requests
REQUEST_LOCK = threading.Lock()
TOKEN_LOCK = threading.Lock()
access_token = None
token_expiry = 0

def get_access_token():
    """Get access token from the authentication server with caching."""
    global access_token, token_expiry
    
    current_time = time.time()
    
    # Check if we have a valid token
    if access_token and current_time < token_expiry:
        return access_token
    
    # Get new token if needed
    with REQUEST_LOCK:
        # Double check after acquiring lock
        if access_token and current_time < token_expiry:
            return access_token
            
        url = os.getenv("TEXT_GENERATOR_AUTH_URL", "")
        data = {
            "grant_type": "client_credentials",
            "client_id": os.getenv("TEXT_GENERATOR_CLIENT_ID", ""),
            "client_secret": os.getenv("TEXT_GENERATOR_CLIENT_SECRET", ""),
            "scope": os.getenv("TEXT_GENERATOR_SCOPE", "")
        }
        headers = {
            "Content-Type": "application/x-www-form-urlencoded"
        }
        response = requests.post(url, data=data, headers=headers)
        token_info = response.json()
        
        # Cache the token with 50 minutes expiry (assuming 1 hour token lifetime)
        access_token = token_info['access_token']
        token_expiry = current_time + 3000  # 50 minutes
        
        return access_token

def submit_content(access_token: str, content: str) -> Tuple[str, str]:
    """Submit content for processing."""
    base_url = os.getenv("TEXT_GENERATOR_API_URL", "")
    url = f"{base_url}/SubmitContent"
    data = {
        "style": "formal",
        "originalContent": content
    }
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {access_token}"
    }
    response = requests.post(url, json=data, headers=headers)
    result = response.json()
    return result['result']['id'], result['result']['version']

def start_processing(access_token, job_id, job_version, content):
    """Start the processing job."""
    base_url = os.getenv("TEXT_GENERATOR_API_URL", "")
    url = f"{base_url}/StartProcessing"
    data = {
        "id": job_id,
        "version": job_version,
        "style": "formal",
        "originalContent": content
    }
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {access_token}"
    }
    response = requests.post(url, json=data, headers=headers)
    return response.json()

def check_job_status(access_token, job_id):
    """Check the status of a processing job."""
    base_url = os.getenv("TEXT_GENERATOR_API_URL", "")
    url = f"{base_url}/CheckStatus"
    headers = {
        "Authorization": f"Bearer {access_token}"
    }
    params = {
        "id": job_id
    }
    response = requests.get(url, headers=headers, params=params)
    return response.json()

def save_job_token(filename: str, job_id: str, job_version: str):
    """Save job token information to a file."""
    with TOKEN_LOCK:
        token_file = Path('job_tokens.txt')
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        with open(token_file, 'a', encoding='utf-8') as f:
            f.write(f"{filename}|{job_id}|{job_version}|{timestamp}\n")

def process_single_file(json_file: Path, save_token_only: bool = False) -> Dict:
    """Process a single JSON file and return its status."""
    try:
        print(f"\nProcessing {json_file.name}...")
        
        # Read the input JSON file
        with open(json_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Extract HTML content
        html_content = data['main_content']
        
        # Get access token
        token = get_access_token()
        
        # Submit content for processing
        job_id, job_version = submit_content(token, html_content)
        
        # Save token information
        save_job_token(json_file.name, job_id, job_version)
        
        if save_token_only:
            return {
                'file': json_file.name,
                'status': 'success',
                'message': 'Token collected',
                'job_id': job_id,
                'job_version': job_version
            }
        
        # Start processing
        start_processing(token, job_id, job_version, html_content)
        
        return {
            'file': json_file.name,
            'status': 'success',
            'message': 'Request sent',
            'job_id': job_id,
            'job_version': job_version
        }
        
    except Exception as e:
        return {
            'file': json_file.name,
            'status': 'error',
            'message': str(e)
        }

def process_batch(files: List[Path], save_token_only: bool = False) -> List[Dict]:
    """Process a batch of files concurrently."""
    results = []
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_CONCURRENT_REQUESTS) as executor:
        # Submit all tasks
        future_to_file = {
            executor.submit(process_single_file, file, save_token_only): file 
            for file in files
        }
        
        # Process results as they complete
        for future in concurrent.futures.as_completed(future_to_file):
            file = future_to_file[future]
            try:
                result = future.result()
                results.append(result)
                print(f"Completed {file.name}: {result['status']} - {result['message']}")
            except Exception as e:
                print(f"Error processing {file.name}: {str(e)}")
                results.append({
                    'file': file.name,
                    'status': 'error',
                    'message': str(e)
                })
    
    return results

def fetch_completed_content():
    """Fetch completed content for all saved tokens."""
    token_file = Path('job_tokens.txt')
    if not token_file.exists():
        print("No token file found. Run the script in token collection mode first.")
        return
    
    # Read tokens
    with open(token_file, 'r', encoding='utf-8') as f:
        tokens = [line.strip().split('|') for line in f if line.strip()]
    
    access_token = get_access_token()
    output_dir = Path('processed_jsons')
    output_dir.mkdir(exist_ok=True)
    
    print(f"Found {len(tokens)} tokens to process")
    
    for filename, job_id, job_version, timestamp in tokens:
        try:
            print(f"Processing {filename}...")
            status = check_job_status(access_token, job_id)
            
            if status.get('status') == 'COMPLETED':
                processed_content = status.get('processedContent', '')
                
                # Read original JSON
                json_file = Path(__file__).parent / 'json_files' / filename
                with open(json_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                
                # Update content
                data['main_content'] = processed_content
                
                # Save new JSON
                output_file = output_dir / filename
                with open(output_file, 'w', encoding='utf-8') as f:
                    json.dump(data, f, ensure_ascii=False, indent=4)
                
                print(f"Successfully processed {filename}")
            else:
                print(f"Job not completed for {filename}: {status.get('status')}")
            
        except Exception as e:
            print(f"Error processing {filename}: {str(e)}")
        
        time.sleep(1)  # Small delay between requests

def main():
    """Main function to process JSON files."""
    import argparse
    parser = argparse.ArgumentParser(description='Process JSON files through Text Generator API')
    parser.add_argument('--collect-tokens', action='store_true', 
                      help='Only collect tokens without waiting for results')
    parser.add_argument('--fetch-results', action='store_true',
                      help='Fetch results for previously collected tokens')
    parser.add_argument('--batch-size', type=int, default=10,
                      help='Number of files to process in each batch')
    args = parser.parse_args()
    
    if args.fetch_results:
        fetch_completed_content()
        return
    
    # Get all JSON files
    json_dir = Path(__file__).parent / 'json_files'
    json_files = list(json_dir.glob('*.json'))
    
    if not json_files:
        print("No JSON files found in the json_files directory")
        return
    
    total_files = len(json_files)
    print(f"\nFound {total_files} JSON files to process")
    print(f"Mode: {'Token collection' if args.collect_tokens else 'Full processing'}")
    print(f"Concurrent requests: {MAX_CONCURRENT_REQUESTS}")
    print(f"Batch size: {args.batch_size}")
    print(f"Starting at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*50}\n")
    
    # Process files in batches
    for i in range(0, total_files, args.batch_size):
        batch = json_files[i:i + args.batch_size]
        print(f"\nProcessing batch {i//args.batch_size + 1} of {(total_files + args.batch_size - 1)//args.batch_size}")
        print(f"Files {i+1} to {min(i+args.batch_size, total_files)} of {total_files}")
        
        results = process_batch(batch, args.collect_tokens)
        
        # Print batch summary
        success_count = sum(1 for r in results if r['status'] == 'success')
        error_count = len(results) - success_count
        print(f"\nBatch summary:")
        print(f"Successfully processed: {success_count}")
        print(f"Errors: {error_count}")
        print(f"Progress: {min(i+args.batch_size, total_files)}/{total_files} files ({(min(i+args.batch_size, total_files)/total_files)*100:.1f}%)")
        print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"{'='*50}")
        
        # Small delay between batches to avoid overwhelming the API
        if i + args.batch_size < total_files:
            time.sleep(2)

if __name__ == "__main__":
    main()
