import os
import shutil
import re

VALID_EXTENSIONS = ['.png', '.svg', '.jpg', '.jpeg', '.webp']

def strip_extension(name):
    # Remove any valid extension from the end of the name
    for ext in VALID_EXTENSIONS:
        if name.lower().endswith(ext):
            return name[:-len(ext)]
    return name

def sanitize_filename(name):
    # Remove "logo_" prefix if present
    name = name.replace("logo_", "")
    # Remove "Casino Review" suffix if present
    name = name.replace(" Casino Review", "")
    # Remove any extension from the name
    name = strip_extension(name)
    # Convert to lowercase
    name = name.lower()
    # Remove spaces, dashes, underscores, parentheses
    name = re.sub(r'[ \-_()]', '', name)
    return name

def main():
    # Create new directory
    source_dir = 'images/logos'
    target_dir = 'images/logos_remake'
    os.makedirs(target_dir, exist_ok=True)
    print(f"Created directory: {target_dir}")

    # Process each file
    for filename in os.listdir(source_dir):
        if filename.startswith('logo_'):
            source_path = os.path.join(source_dir, filename)
            # Get base name and extension separately
            base_name = os.path.splitext(filename)[0]  # Get name without extension
            ext = os.path.splitext(filename)[1]  # Get extension with dot
            # Create new filename
            new_name = sanitize_filename(base_name) + ext
            target_path = os.path.join(target_dir, new_name)
            
            # Copy file with new name
            shutil.copy2(source_path, target_path)
            print(f"Renamed: {filename} -> {new_name}")

    # Handle special cases for missing logos
    special_cases = {
        'DBbet Casino': 'dbbet',
        'GreatSpin Casino': 'greatspin',
        'Pelican Casino': 'pelican',
        'ReSpin Casino': 'respin',
        'Rivalry Casino': 'rivalry',
        'Vegasino Casino': 'vegasino'
    }

    # Check if any of the special cases exist in the source directory
    for original_name, new_name in special_cases.items():
        # Try different variations of the filename
        possible_names = [
            f"logo_{original_name} Casino Review",
            f"logo_{original_name}",
            f"logo_{original_name.replace(' ', '')} Casino Review",
            f"logo_{original_name.replace(' ', '')}"
        ]
        
        for possible_name in possible_names:
            for ext in VALID_EXTENSIONS:
                source_path = os.path.join(source_dir, possible_name + ext)
                if os.path.exists(source_path):
                    target_path = os.path.join(target_dir, new_name + ext)
                    shutil.copy2(source_path, target_path)
                    print(f"Found and copied special case: {possible_name + ext} -> {new_name + ext}")
                    break

if __name__ == '__main__':
    main() 