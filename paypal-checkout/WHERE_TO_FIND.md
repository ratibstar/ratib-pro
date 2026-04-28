# Where to Find PayPal on Your Website

## 🎯 PayPal Checkout Access Points

### 1. **Pricing Cards Section** (`#programs`)
   - **Location:** Scroll down to "Plans & Pricing" section on home page
   - **What you'll see:**
     - **Gold Plan Card:** Two buttons:
       - "Register" button (yellow/gold) - Goes to registration form
       - **"Pay with PayPal" button (blue)** - Goes directly to PayPal checkout
     - **Platinum Plan Card:** Two buttons:
       - "Register" button (white/silver) - Goes to registration form
       - **"Pay with PayPal" button (blue)** - Goes directly to PayPal checkout
   - **URL:** `https://yourdomain.com/pages/home.php#programs`

### 2. **Payment Methods Section** (`#payment`)
   - **Location:** Scroll down to "Payment Methods" section on home page
   - **What you'll see:**
     - Two payment option cards:
       - **PayPal Card:** Large PayPal icon, description, and "Pay with PayPal" button
       - Bank Transfer Card: Bank icon, description, and "Register First" button
   - **URL:** `https://yourdomain.com/pages/home.php#payment`

### 3. **Direct PayPal Checkout Page**
   - **URL:** `https://yourdomain.com/paypal-checkout/index.php`
   - **With Parameters:** `https://yourdomain.com/paypal-checkout/index.php?plan=gold&years=1&amount=550`
   - **Parameters:**
     - `plan` - `gold` or `platinum`
     - `years` - `1`, `2`, `3`, or `4`
     - `amount` - Price amount (e.g., `550`, `1000`, `950`, `900` for Gold)

### 4. **Navigation Menu**
   - **Location:** Top navigation bar
   - **Link:** "Payment Methods" (scrolls to Payment Methods section)

## 🔄 How It Works

### From Pricing Cards:
1. User selects a plan (Gold or Platinum)
2. User selects number of years (1, 2, 3, or 4)
3. Price updates automatically
4. User clicks **"Pay with PayPal"** button
5. Redirects to PayPal checkout page with correct plan, years, and amount
6. User completes payment on PayPal
7. Returns to success page

### From Payment Methods Section:
1. User scrolls to Payment Methods section
2. User clicks **"Pay with PayPal"** button
3. Redirects to PayPal checkout page (default: Gold plan, 1 year, $550)
4. User can modify plan/years/amount on checkout page
5. User completes payment

## 📱 Visual Indicators

- **PayPal Buttons:** Blue gradient background (`#0070ba` to `#003087`)
- **PayPal Icon:** Font Awesome `fab fa-paypal` icon
- **Button Text:** "Pay with PayPal"

## ✅ Testing Checklist

- [ ] Visit home page: `https://yourdomain.com/pages/home.php`
- [ ] Scroll to "Plans & Pricing" section
- [ ] See "Pay with PayPal" buttons on both Gold and Platinum cards
- [ ] Click "Pay with PayPal" on Gold card → Should go to checkout
- [ ] Click "Pay with PayPal" on Platinum card → Should go to checkout
- [ ] Scroll to "Payment Methods" section
- [ ] See PayPal payment card with button
- [ ] Click "Pay with PayPal" button → Should go to checkout
- [ ] Test year selection buttons → PayPal button URL should update
- [ ] Verify PayPal checkout page loads correctly
- [ ] Verify plan, years, and amount are passed correctly

## 🎨 Button Styling

The PayPal buttons use:
- **Background:** `linear-gradient(135deg, #0070ba, #003087)` (PayPal brand colors)
- **Icon:** `fab fa-paypal` (PayPal Font Awesome icon)
- **Text:** "Pay with PayPal"
- **Position:** Below the "Register" button on pricing cards

## 🔗 Direct Links

**Gold Plan (1 Year):**
```
https://yourdomain.com/paypal-checkout/index.php?plan=gold&years=1&amount=550
```

**Gold Plan (2 Years):**
```
https://yourdomain.com/paypal-checkout/index.php?plan=gold&years=2&amount=1000
```

**Platinum Plan (1 Year):**
```
https://yourdomain.com/paypal-checkout/index.php?plan=platinum&years=1&amount=600
```

**Platinum Plan (2 Years):**
```
https://yourdomain.com/paypal-checkout/index.php?plan=platinum&years=2&amount=1100
```

## 📍 Summary

**PayPal is accessible from:**
1. ✅ Pricing cards (Gold & Platinum) - "Pay with PayPal" buttons
2. ✅ Payment Methods section - PayPal card with button
3. ✅ Direct URL - `/paypal-checkout/index.php`

**All buttons automatically update with selected plan, years, and amount!**
