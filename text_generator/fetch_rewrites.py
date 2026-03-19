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

def check_api_status():
    """Check if the API is operational and get any status messages."""
    try:
        print("\nChecking API Status...")
        print("=" * 50)
        
        # First get authentication token
        print("Getting authentication token...")
        auth_        url = os.getenv("TEXT_GENERATOR_AUTH_URL", "")
        auth_data = {
            "grant_type": "client_credentials",
            "client_id": os.getenv("TEXT_GENERATOR_CLIENT_ID", ""),
            "client_secret": os.getenv("TEXT_GENERATOR_CLIENT_SECRET", ""),
            "scope": os.getenv("TEXT_GENERATOR_SCOPE", "")
        }
        auth_headers = {
            "Content-Type": "application/x-www-form-urlencoded"
        }
        
        try:
            auth_response = requests.post(auth_url, data=auth_data, headers=auth_headers)
            if auth_response.status_code != 200:
                print(f"ERROR: Authentication failed: {auth_response.text}")
                return False
                
            access_token = auth_response.json()['access_token']
            print("Authentication successful")
            
        except requests.exceptions.RequestException as e:
            print(f"ERROR: Authentication service error: {str(e)}")
            return False
            
        # Now check the actual API endpoint we'll be using
        print("\nChecking API endpoint...")
        status_url = os.getenv("TEXT_GENERATOR_API_URL", "") + "/CheckStatus"
        headers = {
            "Authorization": f"Bearer {access_token}",
            "Content-Type": "application/json"
        }
        
        try:
            # Make a minimal POST request to the actual endpoint we'll use
            status_response = requests.post(status_url, json={"id": ""}, headers=headers)
            print(f"API Endpoint Status: {status_response.status_code}")
            
            try:
                error_data = status_response.json()
                print("API Response Details:")
                print(f"Error Code: {error_data.get('code', 'N/A')}")
                print(f"Message: {error_data.get('message', 'N/A')}")
                if error_data.get('details'):
                    print(f"Details: {error_data['details']}")
                    
                # A 400 or 404 with code 5 means the API is working but needs a valid ID
                if status_response.status_code in [400, 404] and error_data.get('code') == 5:
                    print("\nAPI Status: The endpoint is operational but requires a valid ID")
                    return True
                else:
                    print(f"\nUnexpected API response: {status_response.status_code}") 
                    return False
                    
            except json.JSONDecodeError:
                print(f"Raw Response: {status_response.text[:500]}")
                return False
                
        except requests.exceptions.ConnectionError:
            print("ERROR: Could not connect to API endpoint. Please check your network connection.")
            return False
        except requests.exceptions.RequestException as e:
            print(f"ERROR: API endpoint error: {str(e)}")
            return False
            
        print("=" * 50 + "\n")
        
    except Exception as e:
        print(f"ERROR: Unexpected error during API status check: {str(e)}")
        return False

def check_job_status(access_token: str, job_id: str) -> Dict:
    """Check the status of a processing job."""
    url = os.getenv("TEXT_GENERATOR_API_URL", "") + "/CheckStatus"
    headers = {
        "Authorization": f"Bearer {access_token}",
        "Content-Type": "application/json"
    }
    try:
        id_value = int(job_id) if job_id.isdigit() else job_id
        data = {
            "id": id_value
        }
    except ValueError:
        print(f"Warning: Job ID '{job_id}' is not a valid integer")
        data = {
            "id": job_id
        }
    
    try:
        response = requests.post(url, json=data, headers=headers)
        response_data = response.json()
        
        # Get the state from the main response
        state = response_data.get('result', {}).get('state')
        
        # Check variants for state if not found in main response
        if not state and 'result' in response_data and 'variants' in response_data['result']:
            for variant in response_data['result']['variants']:
                if variant.get('state'):
                    state = variant['state']
                    break
        
        # Print only the state information
        print(f"\nJob ID: {job_id}")
        print(f"State: {state}")
        print("=" * 50)
        
        response.raise_for_status()
        return response_data
        
    except requests.exceptions.RequestException as e:
        error_msg = f"API request failed: {str(e)}"
        if hasattr(e.response, 'text'):
            try:
                error_json = e.response.json()
                error_msg += f"\nAPI Error: {error_json.get('message', '')}"
            except:
                error_msg += f"\nResponse: {e.response.text[:200]}"
        print(f"\nJob ID: {job_id}")
        print(f"State: ERROR - {error_msg}")
        print("=" * 50)
        return {"status": "ERROR", "error": error_msg}
    except json.JSONDecodeError as e:
        error_msg = f"Invalid JSON response: {str(e)}"
        print(f"\nJob ID: {job_id}")
        print(f"State: ERROR - {error_msg}")
        print("=" * 50)
        return {"status": "ERROR", "error": error_msg}
    except Exception as e:
        error_msg = f"Unexpected error: {str(e)}"
        print(f"\nJob ID: {job_id}")
        print(f"State: ERROR - {error_msg}")
        print("=" * 50)
        return {"status": "ERROR", "error": error_msg}

def process_single_token(token_info: Tuple[str, str, str, str]) -> Dict:
    """Process a single token and fetch its completed content."""
    filename, job_id, job_version, timestamp = token_info
    
    try:
        token = get_access_token()
        status = check_job_status(token, job_id)
        
        # Get relevant status information without the content
        status_info = {
            'status': status.get('status'),
            'state': status.get('state'),
            'error': status.get('error', ''),
            'progress': status.get('progress', 0),
            'version': status.get('version', ''),
            'createdAt': status.get('createdAt', ''),
            'updatedAt': status.get('updatedAt', ''),
            'jobId': status.get('jobId', ''),
            'uniqueness': status.get('uniqueness', 0)
        }
        
        # Check variants for state
        variants = status.get('variants', [])
        if variants:
            for variant in variants:
                if variant.get('state'):
                    status_info['state'] = variant['state']
                    break
        
        # Print status info with clear formatting
        print("\n" + "=" * 50)
        print(f"File: {filename}")
        print(f"Job ID: {job_id}")
        if status_info['state']:
            print(f"State: {status_info['state']}")
        if status_info['error']:
            print(f"Error: {status_info['error']}")
        if status_info['progress'] > 0:
            print(f"Progress: {status_info['progress']}%")
        if status_info['version']:
            print(f"Version: {status_info['version']}")
        if status_info['jobId']:
            print(f"Job ID: {status_info['jobId']}")
        if status_info['uniqueness'] > 0:
            print(f"Uniqueness: {status_info['uniqueness']}%")
        if status_info['createdAt']:
            print(f"Created: {status_info['createdAt']}")
        if status_info['updatedAt']:
            print(f"Updated: {status_info['updatedAt']}")
        print("=" * 50 + "\n")
        
        # If we got an error from the API, return it
        if status_info.get('error'):
            return {
                'file': filename,
                'status': 'error',
                'message': status_info['error'],
                'status_info': status_info
            }
        
        current_state = status_info.get('state')
        
        if current_state == 'JOB_STATE_COMPLETED':
            processed_content = status.get('processedContent', '')
            if not processed_content:
                return {
                    'file': filename,
                    'status': 'error',
                    'message': 'No processed content received',
                    'status_info': status_info
                }
            
            # Read original JSON
            json_file = Path(__file__).parent / 'json_files' / filename
            if not json_file.exists():
                return {
                    'file': filename,
                    'status': 'error',
                    'message': f'Original JSON file not found: {json_file}',
                    'status_info': status_info
                }
                
            try:
                with open(json_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
            except Exception as e:
                return {
                    'file': filename,
                    'status': 'error',
                    'message': f'Error reading JSON file: {str(e)}',
                    'status_info': status_info
                }
            
            # Update content
            data['main_content'] = processed_content
            
            # Save new JSON
            output_dir = Path('processed_jsons')
            output_dir.mkdir(exist_ok=True)
            output_file = output_dir / filename
            
            try:
                with open(output_file, 'w', encoding='utf-8') as f:
                    json.dump(data, f, ensure_ascii=False, indent=4)
            except Exception as e:
                return {
                    'file': filename,
                    'status': 'error',
                    'message': f'Error saving processed content: {str(e)}',
                    'status_info': status_info
                }
            
            return {
                'file': filename,
                'status': 'success',
                'message': 'Content processed and saved',
                'status_info': status_info
            }
            
        elif current_state == 'JOB_STATE_FAILED':
            return {
                'file': filename,
                'status': 'error',
                'message': f'Job failed: {status_info["error"]}',
                'status_info': status_info
            }
        else:
            return {
                'file': filename,
                'status': 'pending',
                'message': f'State: {current_state}',
                'status_info': status_info
            }
            
    except Exception as e:
        error_msg = f"Unexpected error processing {filename}: {str(e)}"
        print(error_msg)
        return {
            'file': filename,
            'status': 'error',
            'message': error_msg,
            'status_info': {'status': 'ERROR', 'error': str(e)}
        }

def process_tokens_batch(tokens: List[Tuple[str, str, str, str]]) -> List[Dict]:
    """Process a batch of tokens concurrently."""
    results = []
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_CONCURRENT_REQUESTS) as executor:
        # Submit all tasks
        future_to_token = {
            executor.submit(process_single_token, token): token 
            for token in tokens
        }
        
        # Process results as they complete
        for future in concurrent.futures.as_completed(future_to_token):
            token = future_to_token[future]
            try:
                result = future.result()
                results.append(result)
                # Simplified status print since we now print detailed info in process_single_token
                print(f"Completed {token[0]}: {result['status']}")
            except Exception as e:
                print(f"Error processing {token[0]}: {str(e)}")
                results.append({
                    'file': token[0],
                    'status': 'error',
                    'message': str(e),
                    'status_info': {'status': 'ERROR', 'error': str(e)}
                })
    
    return results

def main():
    """Main function to fetch completed content."""
    # First check API status
    if not check_api_status():
        print("API status check failed. Please check the API service status.")
        return
        
    token_file = Path(__file__).parent / 'job_tokens.txt'
    if not token_file.exists():
        print("No token file found. Run the script in token collection mode first.")
        return

    # Read tokens
    with open(token_file, 'r', encoding='utf-8') as f:
        tokens = [line.strip().split('|') for line in f if line.strip()]

    if not tokens:
        print("No tokens found in the token file.")
        return

    # Create output directory
    output_dir = Path('processed_jsons')
    output_dir.mkdir(exist_ok=True)
    print(f"Created output directory: {output_dir.absolute()}")
    
    total_tokens = len(tokens)
    print(f"\nFound {total_tokens} tokens to process")
    print(f"Starting at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"Concurrent requests: {MAX_CONCURRENT_REQUESTS}")
    print(f"{'='*50}\n")
    
    # Process tokens in batches
    batch_size = 10
    all_results = []
    
    for i in range(0, total_tokens, batch_size):
        batch = tokens[i:i + batch_size]
        print(f"\nProcessing batch {i//batch_size + 1} of {(total_tokens + batch_size - 1)//batch_size}")
        print(f"Files {i+1} to {min(i+batch_size, total_tokens)} of {total_tokens}")
        
        # Add a small delay between batches to avoid overwhelming the API
        if i > 0:
            print("Waiting 2 seconds before next batch...")
            time.sleep(2)
        
        batch_results = process_tokens_batch(batch)
        all_results.extend(batch_results)
        
        # Print batch summary
        success_count = sum(1 for r in batch_results if r['status'] == 'success')
        error_count = sum(1 for r in batch_results if r['status'] == 'error')
        pending_count = sum(1 for r in batch_results if r['status'] == 'pending')
        
        print(f"\nBatch summary:")
        print(f"Successfully processed: {success_count}")
        print(f"Errors: {error_count}")
        print(f"Pending: {pending_count}")
        print(f"Progress: {min(i+batch_size, total_tokens)}/{total_tokens} files ({(min(i+batch_size, total_tokens)/total_tokens)*100:.1f}%)")
        print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"{'='*50}")

    # Print final summary
    total_success = sum(1 for r in all_results if r['status'] == 'success')
    total_errors = sum(1 for r in all_results if r['status'] == 'error')
    total_pending = sum(1 for r in all_results if r['status'] == 'pending')
    
    print(f"\n{'='*50}")
    print("Final Summary:")
    print(f"Total files processed: {total_tokens}")
    print(f"Successfully processed: {total_success}")
    print(f"Errors: {total_errors}")
    print(f"Still pending: {total_pending}")
    print(f"Output directory: {output_dir.absolute()}")
    print(f"Completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'='*50}")
    
    # Save results summary
    summary_file = output_dir / 'job_summary.txt'
    with open(summary_file, 'w', encoding='utf-8') as f:
        f.write(f"Job Summary - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write(f"{'='*50}\n")
        f.write(f"Total files processed: {total_tokens}\n")
        f.write(f"Successfully processed: {total_success}\n")
        f.write(f"Errors: {total_errors}\n")
        f.write(f"Still pending: {total_pending}\n\n")
        f.write("Detailed Results:\n")
        f.write(f"{'='*50}\n")
        for result in all_results:
            f.write(f"{result['file']}: {result['status']} - {result['message']}\n")

if __name__ == "__main__":
    main() 