import os
import shutil
import re

def sanitize_filename(filename):
    # Split filename and extension
    name, ext = os.path.splitext(filename)
    
    # Convert to lowercase and remove spaces, parentheses, and underscores
    sanitized_name = name.lower()
    sanitized_name = re.sub(r'[()\s_]', '', sanitized_name)
    
    # Return the sanitized name with original extension
    return sanitized_name + ext

def cleanup_duplicates(folder):
    """Remove duplicate files that only differ by underscores"""
    files = os.listdir(folder)
    processed = set()
    
    for filename in files:
        if os.path.isdir(os.path.join(folder, filename)):
            continue
            
        # Get the base name without extension
        name, ext = os.path.splitext(filename)
        # Create a version without underscores for comparison
        base_name = re.sub(r'_', '', name.lower())
        
        if base_name in processed:
            # This is a duplicate, remove it
            file_path = os.path.join(folder, filename)
            os.remove(file_path)
            print(f"Removed duplicate: {filename}")
        else:
            processed.add(base_name)

def process_game_providers():
    # Define source and destination folders
    source_folder = 'images/game_providers_full'
    dest_folder = 'images/game_providers_changed'
    
    # Create destination folder if it doesn't exist
    os.makedirs(dest_folder, exist_ok=True)
    
    # Get list of files in source folder
    files = os.listdir(source_folder)
    
    # Process each file
    for filename in files:
        # Skip if it's a directory
        if os.path.isdir(os.path.join(source_folder, filename)):
            continue
            
        # Create new filename
        new_filename = sanitize_filename(filename)
        
        # Source and destination paths
        source_path = os.path.join(source_folder, filename)
        dest_path = os.path.join(dest_folder, new_filename)
        
        # Copy file with new name
        shutil.copy2(source_path, dest_path)
        print(f"Processed: {filename} -> {new_filename}")
    
    # Clean up any duplicate files
    cleanup_duplicates(dest_folder)

if __name__ == '__main__':
    process_game_providers()
    print("\nProcessing complete! Check the 'images/game_providers_changed' folder for results.") 