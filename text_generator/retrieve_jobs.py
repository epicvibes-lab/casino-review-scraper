import json
import os
from pathlib import Path
from datetime import datetime
from typing import Dict, List
import re
import requests
from pprint import pprint

class TextGeneratorAPI:
    def __init__(self):
        self.base_auth_url = os.getenv("TEXT_GENERATOR_AUTH_URL", "")
        self.base_api_url = os.getenv("TEXT_GENERATOR_API_URL", "")
        self.client_id = os.getenv("TEXT_GENERATOR_CLIENT_ID", "")
        self.client_secret = os.getenv("TEXT_GENERATOR_CLIENT_SECRET", "")
        self.access_token = None
        self.session = requests.Session()
        self.session.timeout = 10  # 10 seconds timeout

    def get_access_token(self) -> str:
        """Get or refresh access token"""
        if self.access_token:
            return self.access_token

        try:
            data = {
                "grant_type": "client_credentials",
                "client_id": self.client_id,
                "client_secret": self.client_secret,
                "scope": os.getenv("TEXT_GENERATOR_SCOPE", "")
            }
            headers = {"Content-Type": "application/x-www-form-urlencoded"}
            
            response = self.session.post(self.base_auth_url, data=data, headers=headers)
            response.raise_for_status()
            
            token_info = response.json()
            self.access_token = token_info['access_token']
            return self.access_token
        except Exception as e:
            print(f"Error getting access token: {str(e)}")
            raise

    def inspect_job_response(self, job_id: str, job_version: str) -> Dict:
        """Get and inspect the full API response for a job"""
        try:
            url = f"{self.base_api_url}/CheckStatus"
            headers = {
                "Content-Type": "application/json",
                "Authorization": f"Bearer {self.get_access_token()}"
            }
            data = {
                "id": job_id,
                "version": job_version
            }
            
            response = self.session.post(url, json=data, headers=headers)
            response.raise_for_status()
            
            return response.json()
        except Exception as e:
            print(f"Error inspecting job {job_id}: {str(e)}")
            return None

def extract_job_info(job_key: str) -> tuple:
    """Extract ID and version from job key"""
    match = re.search(r'ID: (\d+), Version: (\d+)', job_key)
    if match:
        return match.group(1), match.group(2)
    return None, None

def main():
    # Read completed jobs
    try:
        with open("completed_jobs.json", 'r', encoding='utf-8') as f:
            data = json.load(f)
    except FileNotFoundError:
        print("Error: completed_jobs.json not found!")
        return
    except json.JSONDecodeError:
        print("Error: Invalid JSON in completed_jobs.json!")
        return
    
    # Filter for completed jobs
    completed_jobs = [
        job for job in data['jobs']
        if job['state'] == 'REWRITE_STATE_COMPLETED'
    ]
    
    if not completed_jobs:
        print("No completed jobs found!")
        return
    
    # Create API client
    api = TextGeneratorAPI()
    
    # Create output directory for inspection results
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    output_dir = Path("text_generator") / f"api_inspection_{timestamp}"
    output_dir.mkdir(parents=True, exist_ok=True)
    
    print(f"\nInspecting {len(completed_jobs)} completed jobs...")
    
    # Inspect first 3 jobs in detail
    for i, job in enumerate(completed_jobs[:3]):
        job_key = job['job_key']
        job_id, job_version = extract_job_info(job_key)
        
        if not job_id or not job_version:
            print(f"Could not extract ID/version from {job_key}")
            continue
        
        print(f"\nInspecting job {i+1}/3: {job_key}")
        print("-" * 80)
        
        # Get full API response
        response = api.inspect_job_response(job_id, job_version)
        if not response:
            continue
        
        # Save full response to file
        output_file = output_dir / f"job_{job_id}_inspection.json"
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(response, f, indent=2, ensure_ascii=False)
        
        # Print response structure
        print("\nAPI Response Structure:")
        print("=" * 40)
        if 'result' in response:
            result = response['result']
            print("\nTop-level keys in 'result':")
            for key in result.keys():
                print(f"- {key}")
            
            if 'variants' in result:
                print("\nFirst variant structure:")
                if result['variants']:
                    variant = result['variants'][0]
                    print("\nVariant keys:")
                    for key in variant.keys():
                        print(f"- {key}")
                    
                    # Print content preview if available
                    if 'content' in variant:
                        content = variant['content']
                        print("\nContent preview (first 200 chars):")
                        print("-" * 40)
                        print(content[:200] + "..." if len(content) > 200 else content)
                else:
                    print("No variants found")
        else:
            print("No 'result' key in response")
            print("\nFull response keys:")
            for key in response.keys():
                print(f"- {key}")
        
        print(f"\nFull response saved to: {output_file}")
        print("=" * 80)
    
    print(f"\nInspection complete. Results saved to: {output_dir}")
    print("\nPlease check the saved files to see the complete API response structure.")

if __name__ == "__main__":
    main() 