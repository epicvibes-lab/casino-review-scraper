def transform_casino_name_to_url(casino_name):
    # Add prefix
    prefix = "https://casino.guru/"
    # Add suffix
    suffix = "-review"
    
    # Remove any trailing/leading whitespace
    casino_name = casino_name.strip()
    
    # Replace spaces with hyphens
    url = casino_name.replace(" ", "-")
    
    # Combine all parts
    full_url = f"{prefix}{url}{suffix}"
    
    return full_url

def main():
    input_file = "casino_names_extracted.txt"
    output_file = "casino_urls.txt"
    
    try:
        # Read all casino names
        with open(input_file, 'r', encoding='utf-8') as f:
            casino_names = f.readlines()
        
        # Transform and write URLs
        with open(output_file, 'w', encoding='utf-8') as f:
            for casino_name in casino_names:
                if casino_name.strip():  # Skip empty lines
                    url = transform_casino_name_to_url(casino_name)
                    f.write(f"{url}\n")
        
        print(f"Successfully processed {len(casino_names)} casino names.")
        print(f"URLs have been written to {output_file}")
        
    except FileNotFoundError:
        print(f"Error: Could not find the input file {input_file}")
    except Exception as e:
        print(f"An error occurred: {str(e)}")

if __name__ == "__main__":
    main() 



    