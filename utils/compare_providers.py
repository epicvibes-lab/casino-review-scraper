import os
import re
import json

# Read provider names from JSON
with open("casino_review.json", "r", encoding="utf-8") as f_json:
    data = json.load(f_json)
providers_json = data["game_providers"]["providers"]

# Normalize provider names (lowercase, replace spaces and special chars with underscore)
normalized_providers_json = [ re.sub(r'[^a-z0-9]', '_', prov.lower()) for prov in providers_json ]

# Read filenames (without extension) from images/game_providers_full folder
folder_path = "images/game_providers_full"
filenames = [ os.path.splitext(os.path.basename(f))[0] for f in os.listdir(folder_path) if os.path.isfile(os.path.join(folder_path, f)) ]

# Normalize filenames (lowercase, replace spaces and special chars with underscore)
normalized_filenames = [ re.sub(r'[^a-z0-9]', '_', f.lower()) for f in filenames ]

# Compare and print full mapping (match or not) for each provider
print("Provider (from JSON) -> Filename (from folder) -> Match?")
for (prov, norm_prov) in zip(providers_json, normalized_providers_json):
    match = norm_prov in normalized_filenames
    print(f"{prov} -> {norm_prov} -> {match}") 