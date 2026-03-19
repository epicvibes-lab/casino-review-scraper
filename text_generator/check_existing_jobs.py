import json
import os
import time
from datetime import datetime, timedelta
import requests
import threading
from queue import Queue
import queue

def get_access_token():
    """Get access token from the authentication server."""
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
    return token_info['access_token']

def check_job_status(access_token: str, job_id: str) -> dict:
    """Check the status of a processing job."""
    url = os.getenv("TEXT_GENERATOR_API_URL", "") + "/CheckStatus"
    headers = {
        "Authorization": f"Bearer {access_token}"
    }
    data = {
        "id": job_id
    }
    response = requests.post(url, json=data, headers=headers)
    return response.json()

def monitor_job(access_token: str, job_id: str, job_type: str, status_queue: Queue, max_wait_minutes: int = 30):
    """Monitor a single job's status and put updates in the queue."""
    start_time = datetime.now()
    end_time = start_time + timedelta(minutes=max_wait_minutes)
    check_interval = 30  # seconds
    status_print_interval = 60  # seconds
    
    last_state = None
    last_status_print = start_time
    first_check = True
    
    while datetime.now() < end_time:
        try:
            status = check_job_status(access_token, job_id)
            current_state = status.get('result', {}).get('state')
            current_time = datetime.now()

            # Always print the very first status immediately
            if first_check:
                update = {
                    'type': 'status',
                    'job_type': job_type,
                    'job_id': job_id,
                    'state': current_state,
                    'elapsed': current_time - start_time,
                    'remaining': end_time - current_time,
                    'content': None
                }
                if current_state == 'REWRITE_STATE_COMPLETED':
                    variants = status.get('result', {}).get('variants', [])
                    if variants:
                        content = variants[0].get('content', '')
                        update['content'] = content
                elif current_state == 'REWRITE_STATE_ERROR':
                    update['error'] = status.get('result', {}).get('error', 'No error details')
                status_queue.put(update)
                # Print full response if state is None
                if current_state is None:
                    print(f"\nDEBUG: Full API response for {job_type} job (ID: {job_id}):\n{json.dumps(status, indent=2, ensure_ascii=False)}\n")
                first_check = False
                # If already terminal, exit
                if current_state in ['REWRITE_STATE_COMPLETED', 'REWRITE_STATE_ERROR']:
                    break

            # Queue status update every minute
            if (current_time - last_status_print).total_seconds() >= status_print_interval:
                elapsed = current_time - start_time
                remaining = end_time - current_time
                update = {
                    'type': 'status',
                    'job_type': job_type,
                    'job_id': job_id,
                    'state': current_state,
                    'elapsed': elapsed,
                    'remaining': remaining,
                    'content': None
                }
                if current_state == 'REWRITE_STATE_COMPLETED':
                    variants = status.get('result', {}).get('variants', [])
                    if variants:
                        content = variants[0].get('content', '')
                        update['content'] = content
                elif current_state == 'REWRITE_STATE_ERROR':
                    update['error'] = status.get('result', {}).get('error', 'No error details')
                status_queue.put(update)
                last_status_print = current_time

            # Queue state changes immediately
            if current_state != last_state:
                update = {
                    'type': 'state_change',
                    'job_type': job_type,
                    'job_id': job_id,
                    'state': current_state,
                    'content': None
                }
                if current_state == 'REWRITE_STATE_COMPLETED':
                    variants = status.get('result', {}).get('variants', [])
                    if variants:
                        content = variants[0].get('content', '')
                        update['content'] = content
                elif current_state == 'REWRITE_STATE_ERROR':
                    update['error'] = status.get('result', {}).get('error', 'No error details')
                status_queue.put(update)
                if current_state in ['REWRITE_STATE_COMPLETED', 'REWRITE_STATE_ERROR']:
                    break

            last_state = current_state
            time.sleep(check_interval)
        except Exception as e:
            status_queue.put({
                'type': 'error',
                'job_type': job_type,
                'job_id': job_id,
                'error': str(e)
            })
            break

def print_status_update(update: dict):
    """Print a status update in a consistent format."""
    if update['type'] == 'status':
        print(f"\n{update['job_type']} job (ID: {update['job_id']}) status update:")
        print(f"Current state: {update['state']}")
        print(f"Elapsed time: {update['elapsed'].seconds//60}m {update['elapsed'].seconds%60}s")
        print(f"Remaining time: {update['remaining'].seconds//60}m {update['remaining'].seconds%60}s")
        if update['content']:
            print(f"Content length: {len(update['content'])} characters")
            print(f"Content preview: {update['content'][:200]}...")
        print("-" * 50)
    
    elif update['type'] == 'state_change':
        print(f"\n{update['job_type']} job (ID: {update['job_id']}) state changed to: {update['state']}")
        if update['state'] == 'REWRITE_STATE_COMPLETED' and update['content']:
            print(f"Content length: {len(update['content'])} characters")
            print(f"Content preview: {update['content'][:200]}...")
        elif update['state'] == 'REWRITE_STATE_ERROR':
            print(f"Error details: {update.get('error', 'No error details')}")
    
    elif update['type'] == 'error':
        print(f"\nError checking {update['job_type']} job (ID: {update['job_id']}): {update['error']}")

def main():
    """Check status of existing job jobs."""
    try:
        # ONLY these two specific jobs we want to check
        html_job_id = "890"  # HTML job
        plain_text_job_id = "891"  # Plain text job
        
        print("ONLY checking these existing jobs:")
        print(f"HTML job ID: {html_job_id}")
        print(f"Plain text job ID: {plain_text_job_id}")
        
        # Get access token
        access_token = get_access_token()
        
        # Create a queue for status updates
        status_queue = Queue()
        
        # Create and start monitoring threads
        html_thread = threading.Thread(
            target=monitor_job,
            args=(access_token, html_job_id, "HTML", status_queue)
        )
        plain_thread = threading.Thread(
            target=monitor_job,
            args=(access_token, plain_text_job_id, "PLAIN TEXT", status_queue)
        )
        
        html_thread.start()
        plain_thread.start()
        
        # Process updates from both jobs
        active_jobs = 2
        while active_jobs > 0:
            try:
                update = status_queue.get(timeout=1)
                print_status_update(update)
                
                if update['type'] in ['state_change', 'error']:
                    if update['state'] in ['REWRITE_STATE_COMPLETED', 'REWRITE_STATE_ERROR']:
                        active_jobs -= 1
                
            except queue.Empty:
                continue
        
        # Wait for threads to finish
        html_thread.join()
        plain_thread.join()
        
    except KeyboardInterrupt:
        print("\nStopping...")
    except Exception as e:
        print(f"\nUnexpected error: {str(e)}")
    finally:
        print("\nExiting...")

if __name__ == "__main__":
    main() 