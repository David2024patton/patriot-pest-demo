const { chromium } = require('playwright');

async function openHostingerPanel() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    
    try {
        console.log('Opening Hostinger login page...');
        await page.goto('https://auth.hostinger.com/');
        
        console.log('Auto-filling credentials...');
        await page.fill('input[type="email"]', 'david@itak.live');
        await page.fill('input[type="password"]', 'Wildcats@360');
        
        console.log('Login page ready. Please click the login button manually.');
        console.log('After login, navigate to: Hosting > Files > File Manager > public_html/');
        console.log('Create "cost" directory and upload: G:\\Mojo\\patriot-pest-app\\public\\cost_20260807_214000.zip');
        console.log('Extract the archive to complete deployment.');
        console.log('');
        console.log('Press Ctrl+C to close the browser when done.');
        
        // Keep browser open for manual completion
        await new Promise(() => {}); // Run indefinitely
        
    } catch (error) {
        console.error('Error:', error);
    } finally {
        await browser.close();
    }
}

openHostingerPanel().catch(console.error);
