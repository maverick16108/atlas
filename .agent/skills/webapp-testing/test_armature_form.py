from playwright.sync_api import sync_playwright
import time

def run():
    with sync_playwright() as p:
        # Launch browser
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()
        
        # Subscribe to console logs
        page.on("console", lambda msg: print(f"BROWSER CONSOLE: {msg.type}: {msg.text}"))
        page.on("dialog", lambda d: print(f"DIALOG: {d.message}"))
        page.on("requestfailed", lambda r: print(f"REQUEST FAILED: {r.url} {r.failure}"))


        # Mock API responses
        # 1. User User
        page.route("**/api/me", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body='{"id": 1, "name": "Test User", "phone": "+79991112233", "role": "client", "is_active": true}'
        ))

        # 2. Login (not used if we inject token, but safely mocked)
        page.route("**/api/login", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body='{"access_token": "mock-token", "user": {"id": 1}}'
        ))

        # 3. Create Order
        def handle_order(route):
            print("Order submission intercepted!")
            # print(route.request.post_data)
            route.fulfill(status=200, body='{"success": true, "id": 123}')
            
        page.route("**/orders", handle_order)

        # Start - Inject Token to bypass real login
        print("Navigating to app...")
        page.goto("http://localhost:5173/")
        
        # Inject token
        page.evaluate("localStorage.setItem('token', 'mock-token')")
        
        # Navigate to Armature Form directly
        print("Navigating to Create Armature Order...")
        page.goto("http://localhost:5173/create-order/armature")
        
        # Wait for page to be ready
        page.wait_for_load_state('networkidle')
        
        # Verify Header
        if page.is_visible("text=Новая заявка на арматуру"):
            print("✅ Page Loaded")
        else:
            print("❌ Page Load Failed")
            # Take screenshot
            page.screenshot(path="error_load.png")
            browser.close()
            return

        # Fill Form
        print("Filling form...")
        
        # 1. Buyer & Consignee
        # Looking for selects. 
        # Note: Selects in Vue might need specific handling if they are custom, 
        # but the code uses standard <select> mostly, except Combobox for Carrier.
        
        # Select Buyer (First option)
        page.select_option("select >> nth=0", index=1) 
        
        # Select Consignee (First option)
        page.select_option("select >> nth=1", index=1)

        # 2. Self-Delivery Toggle
        # Click the 'Self-pickup' checkbox to reveal transport fields
        print("Toggling Self-Pickup...")
        page.click("input[id='is_pickup']")
        
        # 3. Carrier (Combobox)
        # Click input
        page.click("input[placeholder*='Выберите']")
        # Wait for options
        page.wait_for_selector("ul[role='listbox']")
        # Click first option
        page.click("li[role='option'] >> nth=0")
        print("✅ Carrier selected")

        # 4. Unloading Point
        # There are selects: Buyer(0), Consignee(1), Vehicle(2), Trailer(3), Driver(4), Unloading(5)
        page.select_option("select >> nth=5", index=1)

        # 5. Specifications
        # Click first spec card by text (more robust than checkbox index which shifted)
        page.click("text=СП-2023 >> nth=0")
        print("✅ Specification selected")
        
        # Wait for Gallery to appear
        page.wait_for_selector("text=Доступно к заказу")
        
        # 5. Add Product
        # Click first 'Plus' button (blue rounded button)
        page.click("button.bg-blue-100 >> nth=0")
        print("✅ Product Added")

        # 6. Set Quantity
        # Find input inside table
        page.fill("input[type='number']", "10")
        
        # 7. Submit
        print("Submitting form...")
        
        # Check validity first
        valid = page.evaluate("document.querySelector('form').checkValidity()")
        print(f"Form Validity: {valid}")
        
        if not valid:
            invalid_fields = page.evaluate("""() => {
                return Array.from(document.querySelectorAll(':invalid')).map(el => {
                    return {
                        tag: el.tagName,
                        id: el.id,
                        name: el.name,
                        value: el.value,
                        validationMessage: el.validationMessage
                    }
                })
            }""")
            print("INVALID FIELDS:", invalid_fields)
        
        # Force JS submit
        page.evaluate("document.querySelector('form').requestSubmit()")
        print("JS Submit triggered")
        
        # Verify navigation to /orders
        try:
            page.wait_for_url("**/orders", timeout=5000)
            print("✅ Successfully redirected to /orders")
        except:
            print("❌ Redirect failed")
            print("Current URL:", page.url)
            page.screenshot(path="error_submit.png")

        browser.close()

if __name__ == "__main__":
    run()
