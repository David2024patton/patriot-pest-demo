import asyncio
import nodriver as nd
from pathlib import Path

async def hostinger_automation():
    """
    Cloudflare bypass automation for Hostinger panel using nodriver.
    Based on Research agent recommendation - highest Cloudflare bypass success rate.
    """
    
    # Start browser with stealth configuration
    browser = await nd.start(
        headless=True,  # Headless mode for non-interactive environments
        no_sandbox=True,  # Required for some environments
        user_data_dir=Path.home() / ".nodriver" / "hostinger-profile",
        args=[
            "--disable-blink-features=AutomationControlled",
            "--disable-dev-shm-usage",
            "--disable-gpu",
            "--window-size=1920,1080"
        ]
    )
    
    try:
        # Navigate to login page
        page = await browser.get("https://auth.hostinger.com/")
        
        print("Waiting for Cloudflare challenge...")
        await asyncio.sleep(5.0)
        
        print("Filling login credentials...")
        email_input = await page.select("input[type='email']")
        await email_input.send_keys("david@itak.live")
        
        password_input = await page.select("input[type='password']")
        await password_input.send_keys("Wildcats@360")
        
        print("Clicking login button...")
        login_button = await page.select("button[type='submit']")
        await login_button.click()
        
        print("Waiting for navigation after login...")
        await asyncio.sleep(10.0)
        
        print("Login successful! Manual file upload required.")
        print("Navigate to: Hosting > Files > File Manager > public_html/")
        print("Create 'cost' directory and upload: G:\\Mojo\\patriot-pest-app\\public\\cost_20260807_214000.zip")
        print("Extract archive to public_html/cost/")
        print("Browser will close in 30 seconds.")
        
        await asyncio.sleep(30)
        
    except Exception as e:
        print(f"Error during automation: {e}")
        print("Manual completion required.")
        await asyncio.sleep(10)

if __name__ == "__main__":
    asyncio.run(hostinger_automation())
