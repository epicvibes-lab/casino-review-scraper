import os
import time
import json
import requests
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, NoSuchElementException
from webdriver_manager.chrome import ChromeDriverManager
import random
import logging
from datetime import datetime

# Paths for ChromeDriver and Chrome (relative to project or auto-managed)
chrome_driver_path = os.getenv("CHROME_DRIVER_PATH", "chromedriver.exe")
chrome_path = os.getenv("CHROME_PATH", "chrome.exe")

# Folder to save JSON files
JSON_SAVE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "json_files")

# Path to unscraped URLs file
UNSCRAPED_URLS_FILE = "unscraped_urls.txt"  # Using relative path in the same directory

WP_BASE_URL = os.getenv("WP_BASE_URL", "https://example.com")
WP_API_URL = f"{WP_BASE_URL}/wp-json/casino/v1/add/"

# Set this to True to always process all URLs (ignore progress file)
FORCE_RESCRAPE_ALL = True

# Initialize WebDriver
def initialize_driver():
    options = Options()
    
    # Basic options
    options.add_argument("--disable-gpu")
    options.add_argument("--window-size=1920x1080")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--disable-blink-features=AutomationControlled")
    
    # VPN-specific options
    options.add_argument("--dns-prefetch-disable")  # Disable DNS prefetching
    options.add_argument("--disable-features=NetworkService")  # Disable network service
    options.add_argument("--disable-features=NetworkServiceInProcess")  # Disable in-process network service
    options.add_argument("--disable-features=IsolateOrigins,site-per-process")  # Disable site isolation
    options.add_argument("--disable-site-isolation-trials")  # Disable site isolation trials
    
    # Set Chrome binary location
    options.binary_location = chrome_path
    
    # Add user data directory in a writable location
    user_data_dir = os.path.join(os.path.expanduser("~"), "chrome_automation_data")
    if not os.path.exists(user_data_dir):
        os.makedirs(user_data_dir)
    options.add_argument(f"--user-data-dir={user_data_dir}")
    
    # Additional stability options
    options.add_argument("--disable-extensions")
    options.add_argument("--disable-popup-blocking")
    options.add_argument("--disable-notifications")
    options.add_argument("--disable-infobars")
    options.add_argument("--disable-web-security")
    options.add_argument("--ignore-certificate-errors")
    
    try:
        service = Service(chrome_driver_path)
        driver = webdriver.Chrome(service=service, options=options)
        # Set page load timeout to 30 seconds for VPN
        driver.set_page_load_timeout(30)
        return driver
    except Exception as e:
        print(f"[ERROR] Failed to initialize Chrome driver: {e}")
        raise

# Function to clean and format text
def clean_text(text):
    if text:
        return " ".join(text.split())
    return ""

# Function to safely extract text from an element with increased timeout for VPN
def safe_extract_text(driver, selector, selector_type=By.CLASS_NAME, timeout=15, error_message="Not found"):
    try:
        element = WebDriverWait(driver, timeout).until(
            EC.presence_of_element_located((selector_type, selector))
        )
        return clean_text(element.text)
    except (TimeoutException, NoSuchElementException) as e:
        print(f"[WARNING] Could not extract text with selector '{selector}': {e}")
        return error_message

# Function to safely extract elements and their text with increased timeout for VPN
def safe_extract_elements(driver, selector, selector_type=By.CLASS_NAME, timeout=15):
    try:
        elements = WebDriverWait(driver, timeout).until(
            EC.presence_of_all_elements_located((selector_type, selector))
        )
        return [clean_text(element.text) for element in elements if clean_text(element.text)]
    except (TimeoutException, NoSuchElementException) as e:
        print(f"[WARNING] Could not extract elements with selector '{selector}': {e}")
        return []

# Function to safely extract elements and their attributes with increased timeout for VPN
def safe_extract_attributes(driver, selector, attribute, selector_type=By.XPATH, timeout=15):
    try:
        elements = WebDriverWait(driver, timeout).until(
            EC.presence_of_all_elements_located((selector_type, selector))
        )
        return [element.get_attribute(attribute) for element in elements if element.get_attribute(attribute)]
    except (TimeoutException, NoSuchElementException) as e:
        print(f"[WARNING] Could not extract attributes with selector '{selector}': {e}")
        return []

# Scrape data
def scrape_data(driver, url):
    data = {}
    try:
        driver.get(url)
        # Increased wait time for VPN
        time.sleep(10)  # Allow more time for page to fully load with VPN
    except TimeoutException:
        print(f"[WARNING] Page load timeout for {url}, retrying...")
        try:
            driver.refresh()
            time.sleep(10)
        except Exception as e:
            print(f"[ERROR] Failed to refresh page: {e}")
            return None
    
    # Extract detail info
    try:
        detail_info_col = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CLASS_NAME, "casino-detail-info-col"))
        )
        data["detail_info"] = {
            "casino_name": safe_extract_text(detail_info_col, "h1", By.TAG_NAME),
            "safety_index": safe_extract_text(detail_info_col, "rating"),
            "safety_rating": safe_extract_text(detail_info_col, "text-reputation"),
            "user_feedback": safe_extract_text(detail_info_col, "text-bold.text-uppercase.fs-l"),
            "user_reviews_count": safe_extract_text(detail_info_col, ".//p[contains(text(), 'Rated by')]", By.XPATH).split()[-2] if "Rated by" in safe_extract_text(detail_info_col, ".//p[contains(text(), 'Rated by')]", By.XPATH) else "0",
            "accepts_vietnam": safe_extract_text(detail_info_col, "middle"),
            "payment_methods": safe_extract_attributes(detail_info_col, ".//ul[@class='flex flex-wrap']//img", "alt"),
            "withdrawal_limits": {},  # Will be populated with period-based limits
            "owner": safe_extract_text(detail_info_col, ".//label[contains(text(), 'Owner')]/following-sibling::b", By.XPATH),
            "established": safe_extract_text(detail_info_col, ".//label[contains(text(), 'Established')]/following-sibling::b", By.XPATH),
            "estimated_annual_revenues": safe_extract_text(detail_info_col, ".//div[contains(@class, 'info-col-section-revenues')]//label[contains(text(), 'Estimated annual revenues')]/parent::div/following-sibling::b", By.XPATH),
            "licensing_authorities": safe_extract_elements(detail_info_col, "license-list")
        }
        print("[INFO] Extracted: detail_info")

        # Extract withdrawal limits
        try:
            withdrawal_section = detail_info_col.find_element(By.XPATH, ".//div[contains(@class, 'info-col-section')]//div[contains(text(), 'Withdrawal limits')]/parent::div")
            limit_items = withdrawal_section.find_elements(By.XPATH, ".//div[contains(@class, 'fs-m')]")
            
            for item in limit_items:
                try:
                    period = item.find_element(By.CLASS_NAME, "fs-xs").text.strip().replace("per ", "").strip()
                    amount = item.find_element(By.CLASS_NAME, "neo-fs-20").text.strip()
                    if period and amount:
                        data["detail_info"]["withdrawal_limits"][period] = amount
                except Exception as e:
                    print(f"[DEBUG] Failed to extract withdrawal limit item: {e}")
                    continue
                    
            if not data["detail_info"]["withdrawal_limits"]:
                # Fallback to single withdrawal limit if no period-based limits found
                single_limit = safe_extract_text(detail_info_col, "fs-m.text-bold")
                if single_limit:
                    # Store just the value instead of using "general" as key
                    data["detail_info"]["withdrawal_limits"] = single_limit
                
        except Exception as e:
            print(f"[DEBUG] Failed to extract withdrawal limits: {e}")
            data["detail_info"]["withdrawal_limits"] = "Failed to extract withdrawal limits"
    except Exception as e:
        print(f"[ERROR] Failed to extract detail_info: {e}")
        data["detail_info"] = {"casino_name": "Error extracting casino details"}

    # Main Content
    try:
        main_content_elem = driver.find_element(By.CLASS_NAME, "casino-detail-box-description")
        driver.execute_script("arguments[0].scrollIntoView();", main_content_elem)
        time.sleep(2)
        try:
            read_more_button = driver.find_element(By.XPATH, ".//span[contains(text(), 'Read more')]")
            driver.execute_script("arguments[0].click();", read_more_button)
            time.sleep(2)
            print("[INFO] Expanded 'Read more' section")
        except:
            print("[INFO] No 'Read more' button found")
        data["main_content"] = main_content_elem.get_attribute("outerHTML")
        print("[INFO] Extracted: main_content")
    except Exception as e:
        print(f"[ERROR] Failed to extract main_content: {e}")
        data["main_content"] = "<div>Failed to extract review content</div>"

    # Bonuses
    try:
        bonus_section = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CLASS_NAME, "casino-detail-box-bonuses"))
        )
        bonuses = {}
        
        # Get all bonus cards (both available and not available)
        bonus_cards = bonus_section.find_elements(By.CLASS_NAME, "casino-detail-bonus-card")
        
        for card in bonus_cards:
            try:
                # Get bonus type
                bonus_type = safe_extract_text(card, "bonus-type", By.CLASS_NAME).strip(':')
                
                # Get basic bonus info
                bonus_info = {
                    "name": safe_extract_text(card, "bonus-name-1", By.CLASS_NAME),
                    "additional_info": safe_extract_text(card, "bonus-name-2", By.CLASS_NAME),
                    "is_available": not ("not-available" in card.get_attribute("class")),
                }
                
                # Only try to get T&C details for available bonuses
                if bonus_info["is_available"]:
                    try:
                        # Find and click the T&C button to show tooltip
                        info_button = card.find_element(By.CLASS_NAME, "info")
                        driver.execute_script("arguments[0].click();", info_button)
                        time.sleep(1)  # Wait for tooltip to appear
                        
                        # Find the tooltip content
                        tooltip = WebDriverWait(driver, 5).until(
                            EC.presence_of_element_located((By.CSS_SELECTOR, "[data-tippy-root] .bonus-lines-tooltip"))
                        )
                        
                        # Extract all condition lines
                        conditions = tooltip.find_elements(By.CLASS_NAME, "bonus-conditions-line")
                        
                        bonus_details = {}
                        for condition in conditions:
                            try:
                                # Get the text content from the div inside the condition line
                                condition_text = clean_text(condition.find_element(By.TAG_NAME, "div").text)
                                if not condition_text:
                                    continue
                                
                                # Parse specific conditions based on their content
                                if "No deposit bonus" in condition_text:
                                    bonus_details["type"] = "No deposit bonus"
                                elif "Deposit bonus" in condition_text:
                                    bonus_details["type"] = "Deposit bonus"
                                elif "Minimum deposit:" in condition_text:
                                    bonus_details["minimum_deposit"] = condition_text.split("Minimum deposit:")[-1].strip()
                                elif "Wagering requirements:" in condition_text:
                                    bonus_details["wagering_requirements"] = condition_text.split("Wagering requirements:")[-1].strip()
                                elif "Maximum bet:" in condition_text:
                                    bonus_details["maximum_bet"] = condition_text.split("Maximum bet:")[-1].strip()
                                elif "Bonus expiration:" in condition_text:
                                    bonus_details["expiration"] = condition_text.split("Bonus expiration:")[-1].strip()
                                elif "Value of free spins:" in condition_text:
                                    bonus_details["free_spins_value"] = condition_text.split("Value of free spins:")[-1].strip()
                                elif "Free spins:" in condition_text:
                                    bonus_details["free_spins_details"] = condition_text.split("Free spins:")[-1].strip()
                                elif "Free spins conditions:" in condition_text:
                                    bonus_details["free_spins_conditions"] = condition_text.split("Free spins conditions:")[-1].strip()
                                elif "process of getting this bonus" in condition_text:
                                    bonus_details["process_speed"] = "FAST" if "FAST" in condition_text else "NORMAL"
                                elif "This is a" in condition_text and "free spin offer" in condition_text:
                                    bonus_details["free_spins_type"] = condition_text.split("This is a")[1].split(".")[0].strip()
                                elif "Maximum amount that can be won" in condition_text:
                                    bonus_details["maximum_win"] = condition_text.split("Maximum amount that can be won")[1].strip()
                                elif any(x in condition_text for x in ["18+", "Terms apply"]):
                                    bonus_details["additional_terms"] = condition_text
                            except Exception as e:
                                print(f"[DEBUG] Failed to parse condition: {condition_text}")
                                continue
                        
                        if bonus_details:  # Only add T&C if we successfully extracted some details
                            bonus_info["terms_and_conditions"] = bonus_details
                        
                        # Close tooltip by clicking outside
                        driver.execute_script("document.querySelector('[data-tippy-root]').remove();")
                        
                    except Exception as e:
                        print(f"[DEBUG] Failed to extract T&C details: {e}")
                        # Don't add error message to terms_and_conditions
                
                if bonus_type not in bonuses:
                    bonuses[bonus_type] = []
                bonuses[bonus_type].append(bonus_info)
                
            except Exception as e:
                print(f"[DEBUG] Failed to extract bonus card data: {e}")
                continue
        
        data["bonuses"] = bonuses if bonuses else {"status": "No bonuses found"}
        print("[INFO] Extracted: bonuses")
    except TimeoutException:
        print("[WARNING] No bonus section found")
        data["bonuses"] = {"status": "No bonuses found"}
    except Exception as e:
        print(f"[ERROR] Failed to extract bonuses: {e}")
        data["bonuses"] = {"status": "Error extracting bonuses"}

    # Games
    try:
        games_section = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CLASS_NAME, "casino-detail-box-games"))
        )
        
        games_data = {
            "available_games": [],
            "unavailable_games": []
        }

        # First try to get games from the initial view (as backup)
        initial_items = games_section.find_elements(By.CLASS_NAME, "casino-game-genre-item")
        initial_games = {
            "available": [],
            "unavailable": []
        }
        
        for item in initial_items:
            try:
                # Get game name from the span element
                game_name = clean_text(item.find_element(By.CSS_SELECTOR, "span").text)
                if not game_name:
                    continue
                
                # Check if game is available based on span class and "active" class
                is_available = "c-grey-7" in item.find_element(By.CSS_SELECTOR, "span").get_attribute("class")
                
                # Clean up game name and add to appropriate list
                if game_name.startswith("No "):
                    game_name = game_name.replace("No ", "").strip()
                    if game_name:
                        initial_games["unavailable"].append(game_name)
                else:
                    if is_available:
                        initial_games["available"].append(game_name)
                    else:
                        initial_games["unavailable"].append(game_name)
                        
            except Exception as e:
                print(f"[DEBUG] Failed to extract initial game item: {e}")
                continue
        
        # Try to get expanded view (preferred source)
        try:
            # Find and click the "Show all" button
            show_all_button = games_section.find_element(By.CSS_SELECTOR, "[data-ga-id='casDet_overview_btn_allGames']")
            driver.execute_script("arguments[0].click();", show_all_button)
            time.sleep(2)  # Wait for popup to load
            
            # Look for the expanded list in both possible locations
            expanded_items = []
            try:
                # First try the popup content
                expanded_list = WebDriverWait(driver, 5).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, "[data-tippy-root] .casino-card-available-games-ul"))
                )
                expanded_items = expanded_list.find_elements(By.CLASS_NAME, "casino-open-icons-item")
            except:
                # If not found in popup, try the hidden div
                try:
                    expanded_list = games_section.find_element(By.CLASS_NAME, "casino-card-available-games-ul")
                    expanded_items = expanded_list.find_elements(By.CLASS_NAME, "casino-open-icons-item")
                except:
                    print("[DEBUG] Could not find expanded list in either location")
            
            # Process expanded items if found
            if expanded_items:
                for item in expanded_items:
                    try:
                        # Get game name
                        game_name = clean_text(item.text)
                        if not game_name:
                            continue
                        
                        # Check if game is available based on both the item's active class and SVG color
                        svg_elem = item.find_element(By.TAG_NAME, "svg")
                        is_available = "active" in item.get_attribute("class") and "c-green" in svg_elem.get_attribute("class")
                        
                        # Clean up game name and add to appropriate list
                        if game_name.startswith("No "):
                            game_name = game_name.replace("No ", "").strip()
                            if game_name and game_name not in games_data["unavailable_games"]:
                                games_data["unavailable_games"].append(game_name)
                        else:
                            if is_available:
                                if game_name and game_name not in games_data["available_games"]:
                                    games_data["available_games"].append(game_name)
                            else:
                                if game_name and game_name not in games_data["unavailable_games"]:
                                    games_data["unavailable_games"].append(game_name)
                                    
                    except Exception as e:
                        print(f"[DEBUG] Failed to extract expanded game item: {e}")
                        continue
                
                # Close expanded list
                try:
                    close_button = driver.find_element(By.CLASS_NAME, "js-tippy-close")
                    driver.execute_script("arguments[0].click();", close_button)
                except:
                    # Fallback close method
                    driver.execute_script("document.querySelector('[data-tippy-root]').remove();")
            
            else:
                # If expanded view failed, use the initial view data
                games_data["available_games"] = initial_games["available"]
                games_data["unavailable_games"] = initial_games["unavailable"]
                
        except Exception as e:
            print(f"[DEBUG] Could not process expanded games list: {e}")
            # Use initial view data as fallback
            games_data["available_games"] = initial_games["available"]
            games_data["unavailable_games"] = initial_games["unavailable"]
        
        # Final cleanup and sorting
        games_data["available_games"] = sorted(list(set([g.strip() for g in games_data["available_games"] if g.strip()])))
        games_data["unavailable_games"] = sorted(list(set([g.strip() for g in games_data["unavailable_games"] if g.strip()])))
        
        data["games"] = games_data
        print("[INFO] Extracted: games")
    except TimeoutException:
        print("[WARNING] No games section found")
        data["games"] = {
            "available_games": [],
            "unavailable_games": []
        }
    except Exception as e:
        print(f"[ERROR] Failed to extract games: {e}")
        data["games"] = {
            "available_games": [],
            "unavailable_games": []
        }

    # Language Options
    try:
        languages_section = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CLASS_NAME, "casino-detail-box-languages"))
        )
        
        language_data = {
            "website_languages": [],
            "customer_support_languages": [],
            "live_chat_languages": []
        }

        # Get all language options
        language_options = languages_section.find_elements(By.CLASS_NAME, "language-option")
        
        for option in language_options:
            try:
                # Get the description text to identify which language section this is
                description = option.find_element(By.CLASS_NAME, "middle").text.lower()
                
                # Find and click the "All languages" button
                all_languages_btn = option.find_element(By.CSS_SELECTOR, "[data-toggle='popover-with-header']")
                driver.execute_script("arguments[0].click();", all_languages_btn)
                time.sleep(1)  # Wait for popup to appear
                
                # Find the popup content
                popup = WebDriverWait(driver, 5).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, "[data-tippy-root] .popover-languages"))
                )
                
                # Extract all language items
                language_items = popup.find_elements(By.CSS_SELECTOR, ".flex.items-center")
                languages = [clean_text(item.find_element(By.CSS_SELECTOR, "span:last-child").text) 
                           for item in language_items if item.text.strip()]
                
                # Add languages to appropriate list based on description
                if "website" in description:
                    language_data["website_languages"] = sorted(languages)
                elif "customer support" in description:
                    language_data["customer_support_languages"] = sorted(languages)
                elif "live chat" in description:
                    language_data["live_chat_languages"] = sorted(languages)
                
                # Close popup
                try:
                    close_button = driver.find_element(By.CLASS_NAME, "js-tippy-close")
                    driver.execute_script("arguments[0].click();", close_button)
                except:
                    # Fallback close method
                    driver.execute_script("document.querySelector('[data-tippy-root]').remove();")
                
                time.sleep(0.5)  # Small delay between processing options
                
            except Exception as e:
                print(f"[DEBUG] Failed to extract language option: {e}")
                continue
        
        data["language_options"] = language_data
        print("[INFO] Extracted: language_options")
    except TimeoutException:
        print("[WARNING] No language options section found")
        data["language_options"] = {
            "website_languages": [],
            "customer_support_languages": [],
            "live_chat_languages": []
        }
    except Exception as e:
        print(f"[ERROR] Failed to extract language options: {e}")
        data["language_options"] = {
            "website_languages": [],
            "customer_support_languages": [],
            "live_chat_languages": []
        }

    # Game Providers
    try:
        providers_section = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CLASS_NAME, "casino-detail-box-game-providers"))
        )
        
        providers_data = []
        
        # Try to get expanded view (preferred source)
        try:
            # Find and click the "Show all" button
            show_all_button = providers_section.find_element(By.CSS_SELECTOR, "[data-toggle='popover-with-header']")
            driver.execute_script("arguments[0].click();", show_all_button)
            time.sleep(2)  # Wait for popup to load
            
            # Look for the expanded list
            try:
                # Find all provider items in the popup
                provider_items = driver.find_elements(By.CSS_SELECTOR, "[data-tippy-root] .casino-detail-logos-item")
                
                for item in provider_items:
                    try:
                        # Get provider name from title attribute
                        provider_name = item.find_element(By.CSS_SELECTOR, "a").get_attribute("title")
                        if provider_name and provider_name not in providers_data:
                            providers_data.append(provider_name)
                    except Exception as e:
                        print(f"[DEBUG] Failed to extract provider item: {e}")
                        continue
                
                # Close popup
                try:
                    close_button = driver.find_element(By.CLASS_NAME, "js-tippy-close")
                    driver.execute_script("arguments[0].click();", close_button)
                except:
                    # Fallback close method
                    driver.execute_script("document.querySelector('[data-tippy-root]').remove();")
                    
            except Exception as e:
                print(f"[DEBUG] Could not find expanded providers list: {e}")
                # If expanded view fails, fall back to initial view
                provider_items = providers_section.find_elements(By.CSS_SELECTOR, ".casino-detail-logos-item")
                for item in provider_items:
                    try:
                        provider_name = item.find_element(By.CSS_SELECTOR, "a").get_attribute("title")
                        if provider_name and provider_name not in providers_data:
                            providers_data.append(provider_name)
                    except Exception as e:
                        print(f"[DEBUG] Failed to extract provider item from initial view: {e}")
                        continue
                
        except Exception as e:
            print(f"[DEBUG] Could not process expanded providers list: {e}")
            # Fall back to initial view
            provider_items = providers_section.find_elements(By.CSS_SELECTOR, ".casino-detail-logos-item")
            for item in provider_items:
                try:
                    provider_name = item.find_element(By.CSS_SELECTOR, "a").get_attribute("title")
                    if provider_name and provider_name not in providers_data:
                        providers_data.append(provider_name)
                except Exception as e:
                    print(f"[DEBUG] Failed to extract provider item from initial view: {e}")
                    continue
        
        # Final cleanup and sorting
        providers_data = sorted(list(set([p.strip() for p in providers_data if p.strip()])))
        data["game_providers"] = {"providers": providers_data if providers_data else ["No providers found"]}
        print("[INFO] Extracted: game_providers")
        
    except TimeoutException:
        print("[WARNING] No game providers section found")
        data["game_providers"] = {"providers": ["No providers found"]}
    except Exception as e:
        print(f"[ERROR] Failed to extract game providers: {e}")
        data["game_providers"] = {"providers": ["Error extracting providers"]}

    # Screenshots
    try:
        screenshots = safe_extract_attributes(driver, "//div[contains(@class, 'screenshot')]//img", "src")
        data["screenshots"] = screenshots if screenshots else ["No screenshots available"]
        print("[INFO] Extracted: screenshots")
    except Exception as e:
        print(f"[ERROR] Failed to extract screenshots: {e}")
        data["screenshots"] = ["Error extracting screenshots"]

    # Pros and Cons
    try:
        pros_cons_section = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CLASS_NAME, "casino-detail-box-pros"))
        )
        
        pros_cons_data = {
            "positives": [],
            "negatives": [],
            "interesting_facts": []
        }
        
        # Find all columns in the pros_cons section
        columns = pros_cons_section.find_elements(By.CLASS_NAME, "col")
        
        for column in columns:
            try:
                # Get the section title
                title = column.find_element(By.TAG_NAME, "h3").text.lower()
                
                # Find all list items in this column
                items = column.find_elements(By.TAG_NAME, "li")
                
                for item in items:
                    try:
                        # Get the text content from the div inside the li
                        text = clean_text(item.find_element(By.TAG_NAME, "div").text)
                        if not text:
                            continue
                            
                        # Add to appropriate list based on title
                        if "positive" in title:
                            pros_cons_data["positives"].append(text)
                        elif "negative" in title:
                            pros_cons_data["negatives"].append(text)
                        elif "interesting fact" in title:
                            pros_cons_data["interesting_facts"].append(text)
                            
                    except Exception as e:
                        print(f"[DEBUG] Failed to extract pros_cons item: {e}")
                        continue
                        
            except Exception as e:
                print(f"[DEBUG] Failed to process pros_cons column: {e}")
                continue
        
        # Clean up and set default values if empty
        pros_cons_data["positives"] = pros_cons_data["positives"] if pros_cons_data["positives"] else ["No positives specified"]
        pros_cons_data["negatives"] = pros_cons_data["negatives"] if pros_cons_data["negatives"] else ["No negatives specified"]
        pros_cons_data["interesting_facts"] = pros_cons_data["interesting_facts"] if pros_cons_data["interesting_facts"] else ["No facts specified"]
        
        data["pros_cons"] = pros_cons_data
        print("[INFO] Extracted: pros_cons")
        
    except TimeoutException:
        print("[WARNING] No pros_cons section found")
        data["pros_cons"] = {
            "positives": ["No positives specified"],
            "negatives": ["No negatives specified"],
            "interesting_facts": ["No facts specified"]
        }
    except Exception as e:
        print(f"[ERROR] Failed to extract pros_cons: {e}")
        data["pros_cons"] = {
            "positives": ["Error extracting positives"],
            "negatives": ["Error extracting negatives"],
            "interesting_facts": ["Error extracting facts"]
        }

    return data

# Test WP API with a simple post
def test_wp_api():
    headers = {
        "Content-Type": "application/json",
    }
    payload = {
        "title": "Test Post",
        "content": "This is a test post",
        "status": "publish"
    }
    try:
        response = requests.post(WP_API_URL, json=payload, headers=headers, timeout=10)
        response.raise_for_status()
        print(f"[INFO] Test post successful: {response.status_code} - {response.text}")
    except requests.exceptions.RequestException as e:
        print(f"[ERROR] Test post failed: {e} - {getattr(e.response, 'text', 'No response')}")
        if e.response:
            print(f"Status code: {e.response.status_code}")

# Send JSON data to WordPress API
def send_to_wp_api(data, casino_name):
    headers = {
        "Content-Type": "application/json",
    }
    
    # Send the full JSON data as the content field
    payload = {
        "title": f"{casino_name} Review",
        "content": json.dumps(data, ensure_ascii=False),
        "status": "publish"
    }
    
    try:
        response = requests.post(WP_API_URL, json=payload, headers=headers, timeout=10)
        response.raise_for_status()
        print(f"[INFO] Success for {casino_name}: {response.status_code} - {response.text}")
        return True
    except requests.exceptions.HTTPError as e:
        print(f"[ERROR] HTTP error for {casino_name}: {e} - Status: {response.status_code} - Response: {response.text}")
        return False
    except requests.exceptions.RequestException as e:
        print(f"[ERROR] Request failed for {casino_name}: {e}")
        return False

def ensure_directory_exists(directory):
    """Ensure a directory exists, create if it doesn't."""
    try:
        if not os.path.exists(directory):
            print(f"[INFO] Directory {directory} does not exist, creating...")
            os.makedirs(directory, exist_ok=True)
            # Verify directory was created
            if os.path.exists(directory):
                print(f"[INFO] Successfully created directory: {directory}")
            else:
                print(f"[ERROR] Directory creation failed: {directory}")
                return False
        else:
            print(f"[INFO] Directory already exists: {directory}")
        return True
    except Exception as e:
        print(f"[ERROR] Failed to create directory {directory}: {str(e)}")
        return False

def setup_logging():
    log_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs")
    if not os.path.exists(log_dir):
        os.makedirs(log_dir)
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    log_file = os.path.join(log_dir, f"scraping_{timestamp}.log")
    
    # Only log to file, not to console
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s [%(levelname)s] %(message)s',
        handlers=[
            logging.FileHandler(log_file, encoding='utf-8')
        ]
    )
    logging.info(f"Logging started. Log file: {log_file}")
    return log_file

def save_json_file(data, filename, max_retries=3):
    """Save JSON data to file with retry logic."""
    for attempt in range(max_retries):
        try:
            if not ensure_directory_exists(JSON_SAVE_DIR):
                logging.error(f"Cannot proceed with save - directory {JSON_SAVE_DIR} does not exist")
                return False
                
            # Ensure filename follows the pattern: nameCasinoReview.json
            if not filename.endswith('CasinoReview.json'):
                filename = filename.replace('.json', '') + 'CasinoReview.json'
                
            filepath = os.path.join(JSON_SAVE_DIR, filename)
            logging.info(f"Attempting to save to: {filepath}")
            
            if os.path.exists(filepath):
                logging.warning(f"File already exists: {filepath}")
                return True
            
            try:
                with open(filepath, 'a') as test_file:
                    pass
                os.remove(filepath)
            except Exception as e:
                logging.error(f"Cannot write to {filepath}: {str(e)}")
                return False
            
            with open(filepath, "w", encoding="utf-8") as json_file:
                json.dump(data, json_file, indent=4, ensure_ascii=False)
            
            if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                current_files = [f for f in os.listdir(JSON_SAVE_DIR) if f.endswith('.json')]
                logging.info(f"Successfully saved JSON to: {filepath}")
                logging.info(f"Current JSON files in directory: {len(current_files)}")
                return True
            else:
                logging.error(f"File was created but appears to be empty: {filepath}")
                if attempt < max_retries - 1:
                    time.sleep(2)
                continue
            
        except Exception as e:
            logging.error(f"Attempt {attempt + 1}/{max_retries} failed to save {filename}: {str(e)}")
            if attempt < max_retries - 1:
                time.sleep(2)
            continue
    
    logging.error(f"Failed to save {filename} after {max_retries} attempts")
    return False

def clean_casino_name(name):
    """Clean casino name for filename use."""
    # Remove any existing Review or Casino_Review suffixes
    name = name.replace("CasinoReview", "").replace("Review", "")
    
    # Clean the name to only allow alphanumeric
    clean_name = "".join(c for c in name if c.isalnum())
    
    # Convert to title case to match existing pattern
    clean_name = ''.join(word.capitalize() for word in clean_name.split())
    
    return clean_name

def scrape_casino_review(driver, url, max_retries=3):
    """Scrape casino review with retry logic."""
    for attempt in range(max_retries):
        try:
            logging.info(f"Scraping attempt {attempt + 1}/{max_retries}: {url}")
            data = scrape_data(driver, url)
            
            if not data:
                logging.error(f"No data returned from scrape_data for {url}")
                if attempt < max_retries - 1:
                    time.sleep(5)
                continue
            
            # Get casino name from data or URL
            casino_name = data.get("detail_info", {}).get("casino_name", "")
            if not casino_name or casino_name == "Error extracting casino details":
                logging.error(f"Invalid casino name extracted for {url}")
                if attempt < max_retries - 1:
                    time.sleep(5)
                continue
            else:
                casino_name = casino_name.split("\n")[0].strip()
            
            # Clean the casino name
            safe_filename = clean_casino_name(casino_name)
            if not safe_filename:
                logging.error(f"Could not create safe filename for {url}")
                if attempt < max_retries - 1:
                    time.sleep(5)
                continue
                
            json_filename = f"{safe_filename}.json"
            logging.info(f"Generated filename: {json_filename}")
            
            if os.path.exists(os.path.join(JSON_SAVE_DIR, json_filename)):
                logging.info(f"File already exists, skipping save: {json_filename}")
                return data
                
            if save_json_file(data, json_filename):
                send_to_wp_api(data, casino_name)
                return data
            else:
                logging.error(f"Failed to save JSON for {url}, will retry if attempts remain")
                if attempt < max_retries - 1:
                    time.sleep(5)
                continue
                
        except Exception as e:
            logging.error(f"Attempt {attempt + 1}/{max_retries} failed for {url}: {str(e)}")
            if attempt < max_retries - 1:
                time.sleep(5)
            continue
    
    logging.error(f"Failed to scrape {url} after {max_retries} attempts")
    return None  # Return None instead of error data to indicate failure

def read_urls_from_file(file_path):
    """Read URLs from the unscraped URLs file, skipping the header."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            lines = f.readlines()
            # Skip the header (first 3 lines) and get only the URLs
            urls = [line.strip() for line in lines[3:] if line.strip()]
            print(f"[INFO] Read {len(urls)} URLs from {file_path}")
            return urls
    except Exception as e:
        print(f"[ERROR] Failed to read URLs from file: {e}")
        return []

def save_progress(processed_urls, progress_file):
    """Save processed URLs to a progress file."""
    try:
        with open(progress_file, 'w', encoding='utf-8') as file:
            for url in processed_urls:
                file.write(f"{url}\n")
        print(f"[INFO] Progress saved: {len(processed_urls)} URLs processed")
    except Exception as e:
        print(f"[ERROR] Failed to save progress: {e}")

def load_progress(progress_file):
    """Load already processed URLs from progress file."""
    try:
        if os.path.exists(progress_file):
            with open(progress_file, 'r', encoding='utf-8') as file:
                return set(line.strip() for line in file if line.strip())
        return set()
    except Exception as e:
        print(f"[ERROR] Failed to load progress: {e}")
        return set()

def get_existing_json_filenames(directory):
    """Return a set of filenames (without extension) of existing JSON files in the given directory."""
    if not os.path.exists(directory):
         return set()
    return {os.path.splitext(os.path.basename(f))[0] for f in os.listdir(directory) if f.endswith(".json")}

def send_existing_json_files_to_wp():
    """Send all existing JSON files from the specified directory to WordPress API."""
    if not os.path.exists(JSON_SAVE_DIR):
        logging.error(f"JSON directory does not exist: {JSON_SAVE_DIR}")
        return
    
    json_files = [f for f in os.listdir(JSON_SAVE_DIR) if f.endswith('.json')]
    logging.info(f"Found {len(json_files)} JSON files to process")
    
    for json_file in json_files:
        try:
            file_path = os.path.join(JSON_SAVE_DIR, json_file)
            logging.info(f"Processing file: {json_file}")
            
            with open(file_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
            
            # Get casino name from the data
            casino_name = data.get("detail_info", {}).get("casino_name", "")
            if not casino_name:
                casino_name = os.path.splitext(json_file)[0]  # Use filename as fallback
            
            if send_to_wp_api(data, casino_name):
                logging.info(f"Successfully sent {json_file} to WordPress API")
            else:
                logging.error(f"Failed to send {json_file} to WordPress API")
            
            # Add delay between requests
            time.sleep(random.uniform(2, 5))
            
        except Exception as e:
            logging.error(f"Error processing {json_file}: {str(e)}")
            continue

def should_scrape_url(url, scraped_casinos):
    """Determine if a URL should be scraped based on whether it's already been done."""
    # Extract casino name from URL and clean it to match the filename pattern
    casino_name = url.split('/')[-1].replace('-review', '').replace('-casino', '').replace('-', '')
    # Convert to title case to match filename pattern (e.g., 1xSlots)
    casino_name = ''.join(word.capitalize() for word in casino_name.split())
    logging.info(f"Checking URL: {url}")
    logging.info(f"Extracted casino name: {casino_name}")
    logging.info(f"Scraped casinos set: {scraped_casinos}")
    should_scrape = casino_name not in scraped_casinos
    logging.info(f"Should scrape: {should_scrape}")
    return should_scrape

def get_scraped_casinos():
    """Get a set of casino names that have already been scraped."""
    scraped = set()
    if not os.path.exists(JSON_SAVE_DIR):
        logging.info("JSON_SAVE_DIR does not exist, returning empty set")
        return scraped
        
    for filename in os.listdir(JSON_SAVE_DIR):
        if filename.endswith('.json'):
            # Extract casino name from filename (removing CasinoReview.json)
            casino_name = filename.replace('CasinoReview.json', '')
            logging.info(f"Found scraped casino: {casino_name} from file {filename}")
            scraped.add(casino_name)
    logging.info(f"Total scraped casinos found: {len(scraped)}")
    return scraped

def main():
    # Setup logging
    log_file = setup_logging()
    logging.info("Starting process")
    
    # Ensure save directory exists
    ensure_directory_exists(JSON_SAVE_DIR)
    
    # Get list of URLs to scrape
    urls_to_scrape = read_urls_from_file(UNSCRAPED_URLS_FILE)
    if not urls_to_scrape:
        logging.error("No URLs to scrape. Exiting.")
        return

    # Get list of already scraped casinos
    scraped_casinos = get_scraped_casinos()
    
    # Initialize driver
    try:
        driver = initialize_driver()
    except Exception as e:
        logging.error(f"Failed to initialize driver: {e}")
        return

    try:
        # Process each URL
        for url in urls_to_scrape:
            if not should_scrape_url(url, scraped_casinos) and not FORCE_RESCRAPE_ALL:
                logging.info(f"Skipping already scraped URL: {url}")
                continue

            # Only print the URL being scraped to console
            print(f"\nScraping: {url}")
            
            data = scrape_casino_review(driver, url)
            
            if data:
                casino_name = data.get("detail_info", {}).get("casino_name", "unknown")
                if casino_name == "Error extracting casino details":
                    casino_name = url.split("/")[-1].replace("-review", "")
                
                filename = clean_casino_name(casino_name) + ".json"
                save_json_file(data, filename)
                
                # Optional: Send to WordPress API
                # send_to_wp_api(data, casino_name)
                
                # Add random delay between requests
                time.sleep(random.uniform(5, 10))
            else:
                logging.error(f"Failed to scrape data from {url}")

    except Exception as e:
        logging.error(f"An error occurred during scraping: {e}")
    finally:
        driver.quit()

if __name__ == "__main__":
    main()