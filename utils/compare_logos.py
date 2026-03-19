import os
import re

def normalize_name(name):
    # Convert to lowercase
    name = name.lower()
    # Remove special characters and spaces
    name = re.sub(r'[^a-z0-9]', '', name)
    # Remove common suffixes
    name = re.sub(r'(casino|review)$', '', name)
    return name

# Get list of logo files
logo_dir = "images/logos_remake"
logo_files = [f for f in os.listdir(logo_dir) if os.path.isfile(os.path.join(logo_dir, f))]
logo_names = {normalize_name(os.path.splitext(f)[0]) for f in logo_files}

# Read casino URLs
with open("casino_urls.txt", "r") as f:
    urls = f.readlines()

# Extract casino names from URLs
casino_names = set()
for url in urls:
    # Extract casino name from URL
    match = re.search(r'casino\.guru/([^/]+)-Casino-review', url)
    if match:
        casino_name = normalize_name(match.group(1))
        casino_names.add(casino_name)

# Find missing logos
missing_logos = casino_names - logo_names

print(f"Total casinos in URLs: {len(casino_names)}")
print(f"Total logos found: {len(logo_names)}")
print(f"Missing logos: {len(missing_logos)}")
print("\nMissing casino logos:")
for name in sorted(missing_logos):
    print(f"- {name}") 