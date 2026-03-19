import json
import os
from bs4 import BeautifulSoup
from openai import OpenAI
from pathlib import Path
import logging
from datetime import datetime

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('gpt_rewrite.log'),
        logging.StreamHandler()
    ]
)

class ContentProcessor:
    def __init__(self, api_key):
        """Initialize the ContentProcessor with OpenAI API key."""
        self.client = OpenAI(api_key=api_key)
        self.output_dir = Path("rewritten_jsons")
        self.output_dir.mkdir(exist_ok=True)
        
    def extract_html_content(self, html_text):
        """Extract text content from HTML, removing HTML tags."""
        soup = BeautifulSoup(html_text, 'html.parser')
        return soup.get_text(separator=' ', strip=True)
    
    def rewrite_content(self, text):
        """Send text to ChatGPT for rewriting."""
        try:
            response = self.client.chat.completions.create(
                model="gpt-4-turbo-preview",
                messages=[
                    {"role": "system", "content": "You are a professional content editor. Transform the following text to be unique and original while maintaining the same meaning and information."},
                    {"role": "user", "content": text}
                ],
                temperature=0.7,
                max_tokens=2000
            )
            return response.choices[0].message.content
        except Exception as e:
            logging.error(f"Error in ChatGPT API call: {str(e)}")
            raise
    
    def process_json_file(self, input_file):
        """Process a single JSON file: extract content, transform, and save."""
        try:
            # Read input JSON
            with open(input_file, 'r', encoding='utf-8') as f:
                data = json.load(f)
            
            # Extract and process content
            content_field = 'main_content' if 'main_content' in data else 'content'
            if content_field in data and isinstance(data[content_field], str):
                original_text = self.extract_html_content(data[content_field])
                rewritten_text = self.rewrite_content(original_text)
                
                data[content_field] = rewritten_text
                data['rewritten_at'] = datetime.now().isoformat()
                
                # Generate output filename
                input_path = Path(input_file)
                output_file = self.output_dir / f"rewritten_{input_path.name}"
                
                # Save rewritten JSON
                with open(output_file, 'w', encoding='utf-8') as f:
                    json.dump(data, f, ensure_ascii=False, indent=2)
                
                logging.info(f"Successfully processed {input_file} -> {output_file}")
                return True
            else:
                logging.warning(f"No 'main_content' or 'content' field found in {input_file}")
                return False
                
        except Exception as e:
            logging.error(f"Error processing {input_file}: {str(e)}")
            return False

def main():
    """Main function to process JSON files."""
    api_key = os.getenv("OPENAI_API_KEY", "")
    processor = ContentProcessor(api_key)
    
    # Process all JSON files in the current directory
    json_files = [f for f in os.listdir('.') if f.endswith('.json')]
    
    if not json_files:
        logging.warning("No JSON files found in the current directory")
        return
    
    for json_file in json_files:
        logging.info(f"Processing {json_file}")
        processor.process_json_file(json_file)

if __name__ == "__main__":
    main()
