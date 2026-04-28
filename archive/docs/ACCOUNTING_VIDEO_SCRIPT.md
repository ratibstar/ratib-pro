# Accounting System - Video Tutorial Script

## Complete Video Script for A-Z Accounting System Tutorial

---

## VIDEO 1: Introduction & Overview (5-7 minutes)

### Opening (30 seconds)
"Welcome to the Complete Accounting System Tutorial. In this series, we'll cover everything from A to Z, teaching you how to use every feature of this professional accounting system. Let's get started!"

### What You'll Learn (30 seconds)
"By the end of this series, you'll know how to:
- Create and manage journal entries
- Set up and maintain your chart of accounts
- Handle banking transactions and reconciliation
- Create payment and receipt vouchers
- Manage invoices and bills
- Generate financial reports
- And much more!"

### System Overview (2 minutes)
"First, let's understand what this system does. This is a **professional double-entry accounting system**. That means every transaction affects at least two accounts, and debits always equal credits.

The system is organized into several main modules:
1. **Dashboard** - Quick overview of your finances
2. **Journal Entries** - The foundation of all accounting
3. **Chart of Accounts** - Your account master list
4. **Banking & Cash** - Bank accounts and transactions
5. **Vouchers** - Payment and receipt documents
6. **Invoices & Bills** - Money owed to you and by you
7. **Financial Reports** - Comprehensive financial statements
8. **Settings** - System configuration"

### Accessing the System (1 minute)
"To access the accounting module, first log in to your system. Then navigate to the Accounting menu item. You'll see the main interface with tabs at the top for each module.

The interface consists of:
- **Tabs** at the top for navigation
- **Summary cards** showing key metrics
- **Data tables** with filters and pagination
- **Action buttons** for create, edit, view, print, delete
- **Modals** that pop up for forms and details"

### Interface Tour (2 minutes)
"Let me show you around the interface. [Screen recording]

Here's the Dashboard tab - you can see summary cards showing total revenue, expenses, cash balance, and more.

The filters at the top let you filter by date range, entity type, and account.

The table shows all transactions with pagination controls.

Action buttons let you view details or print transactions.

This is the basic layout you'll see throughout the system."

### Next Steps (30 seconds)
"In the next video, we'll dive deep into Journal Entries - the foundation of accounting. See you there!"

---

## VIDEO 2: Journal Entries - The Foundation (10-12 minutes)

### Introduction (30 seconds)
"Welcome back! In this video, we'll learn about Journal Entries - the foundation of double-entry bookkeeping. This is where all your financial transactions are recorded."

### What is a Journal Entry? (2 minutes)
"A Journal Entry is a record of a financial transaction. In double-entry bookkeeping, every transaction must have equal debits and credits.

For example, if you pay a salary of 1000 SAR:
- You **debit** Salary Expense (increases expense) - 1000 SAR
- You **credit** Cash (decreases asset) - 1000 SAR

Notice: Debit = Credit = 1000 SAR. This is the fundamental rule."

### Understanding Debits and Credits (2 minutes)
"Let me explain debits and credits simply:

**Debits** (left side):
- Increase Assets and Expenses
- Decrease Liabilities, Equity, and Income

**Credits** (right side):
- Increase Liabilities, Equity, and Income
- Decrease Assets and Expenses

Remember: **Debits must always equal Credits** in every transaction."

### Creating a Journal Entry - Step by Step (4 minutes)
"Now let's create a journal entry. [Screen recording]

1. Click the 'New Journal Entry' button
2. The form opens in a modal window
3. Notice the 2-column layout - fields are side by side

**Left Column:**
- Entry # is auto-generated (read-only)
- Select the Date
- Type is auto-set (read-only)
- Enter Debit Amount OR Credit Amount (not both!)
- Select the Account

**Right Column:**
- Status is auto-set to 'Draft' (read-only)
- Select Currency (default: SAR)
- Enter Credit Amount if you entered Debit, or vice versa
- Optionally select an Entity

**Full Width:**
- Enter a clear Description
- Watch the Balance Status indicator

As you enter amounts, the balance status changes:
- **Green 'Balanced'** = Debits equal Credits ✅
- **Red 'Unbalanced'** = They don't match ❌
- **Yellow 'Single entry'** = Only one side entered

4. When balanced (green), click 'Save'
5. The entry is created and appears in the General Ledger"

### Real Example (2 minutes)
"Let me show you a real example: Recording a payment to a worker.

[Screen recording]
- Date: Today's date
- Description: 'Payment to Worker Ahmed for services'
- Account: 'Worker Payments' (this is an expense account)
- Debit Amount: 500 SAR
- Currency: SAR
- Entity: Worker - Ahmed

Now I need to balance this. Since I debited 500, I need to credit 500. I'll credit 'Cash' account.

- Account: 'Cash'
- Credit Amount: 500 SAR

Look! The balance status turns green - 'Balanced'. Now I can save."

### Viewing and Editing Entries (1 minute)
"To view an entry, click 'View' in the table. You'll see all details including who created it and when.

To edit, click 'Edit', make changes, ensure it stays balanced, and save."

### General Ledger Overview (1 minute)
"The General Ledger shows all your journal entries. You can:
- Filter by date range
- Filter by account
- See summary cards with totals
- Use pagination to navigate through entries

The table shows Entry #, Entity, Date, Description, Type, Amount (with color coding), and Status."

### Key Points to Remember (30 seconds)
"Remember:
- Always ensure debits = credits
- Use clear descriptions
- Link to entities when applicable
- Review the balance status before saving"

---

## VIDEO 3: Chart of Accounts (8-10 minutes)

### Introduction (30 seconds)
"Welcome! In this video, we'll learn about the Chart of Accounts - your master list of all accounts in the system."

### What is Chart of Accounts? (1 minute)
"The Chart of Accounts is like a filing cabinet for your finances. Every account has:
- A **Code** (like 1100 for Cash)
- A **Name** (like 'Cash - Main Office')
- A **Type** (Asset, Liability, Equity, Income, Expense)
- A **Normal Balance** (Debit or Credit)
- A **Balance** (current amount)"

### Understanding Account Types (3 minutes)
"Let's understand the 5 main account types:

**1. Assets (1000-1999)**
- What you OWN
- Examples: Cash, Bank Accounts, Equipment, Accounts Receivable
- Normal Balance: **Debit**
- When you receive cash, you DEBIT cash (increases)

**2. Liabilities (2000-2999)**
- What you OWE
- Examples: Loans, Accounts Payable, Taxes Payable
- Normal Balance: **Credit**
- When you borrow money, you CREDIT the loan (increases)

**3. Equity (3000-3999)**
- Owner's stake in the business
- Examples: Owner Capital, Retained Earnings
- Normal Balance: **Credit**

**4. Income (4000-4999)**
- Money COMING IN
- Examples: Sales Revenue, Service Revenue, Agent Revenue
- Normal Balance: **Credit**
- When you make a sale, you CREDIT revenue (increases)

**5. Expenses (5000-5999)**
- Money GOING OUT
- Examples: Salaries, Rent, Utilities, Worker Payments
- Normal Balance: **Debit**
- When you pay expenses, you DEBIT the expense (increases)"

### Viewing the Chart of Accounts (1 minute)
"[Screen recording]
Go to the 'Chart of Accounts' tab. You'll see a table with all accounts showing:
- Code
- Account Name
- Type
- Normal Balance
- Opening Balance
- Current Balance
- Status (Active/Inactive)
- Actions (Edit, Delete)"

### Filtering Accounts (1 minute)
"You can filter accounts:
- **By Type**: Select Asset, Liability, etc. from dropdown
- **By Search**: Type in the search box to find by code or name
- Click 'Clear' to reset filters"

### Creating a New Account (2 minutes)
"[Screen recording]
1. Click 'New Account'
2. Fill in the form:
   - **Account Code**: Unique number (e.g., 1200)
   - **Account Name**: Full name (e.g., 'Bank Account - Main')
   - **Account Type**: Select from dropdown
   - **Normal Balance**: Usually auto-set based on type
   - **Opening Balance**: Starting balance (usually 0)
   - **Description**: Optional notes
   - **Status**: Active or Inactive
3. Click 'Save'

The account appears in your chart of accounts."

### Editing and Deleting Accounts (1 minute)
"To edit: Click 'Edit', modify fields, save.

To delete: Click 'Delete', confirm. **Warning**: Accounts with transactions cannot be deleted. You must first remove all related transactions."

### Best Practices (30 seconds)
"Best practices:
- Use consistent numbering (1000s for assets, 2000s for liabilities, etc.)
- Use clear, descriptive names
- Keep accounts organized
- Don't delete accounts with transactions"

---

## VIDEO 4: Banking & Cash Management (12-15 minutes)

### Introduction (30 seconds)
"Welcome! In this video, we'll learn how to manage your bank accounts, record bank transactions, and reconcile your bank statements."

### Adding a Bank Account (2 minutes)
"[Screen recording]
First, let's add a bank account:

1. Go to 'Banking & Cash' tab or Settings
2. Click 'Add Bank Account'
3. Fill in:
   - **Bank Name**: Name of the bank (e.g., 'Al Rajhi Bank')
   - **Account Name**: Account identifier (e.g., 'Main Business Account')
   - **Account Number**: Your account number
   - **Account Type**: Checking or Savings
   - **Opening Balance**: Starting balance
4. Click 'Save'

The account appears in your bank accounts list."

### Creating Bank Transactions (4 minutes)
"[Screen recording]
Now let's record bank transactions:

1. Click 'New Transaction'
2. Fill in the form:
   - **Bank Account**: Select from dropdown
   - **Transaction Date**: Date of transaction
   - **Transaction Type**: 
     - **Deposit**: Money coming in (increases balance)
     - **Withdrawal**: Money going out (decreases balance)
     - **Transfer**: Between accounts
     - **Fee**: Bank charges (decreases balance)
     - **Interest**: Interest earned (increases balance)
   - **Amount**: Transaction amount
   - **Description**: What the transaction is for
   - **Reference Number**: Optional (check number, etc.)
3. Click 'Save'

**Important**: The system automatically updates the bank balance when you save a transaction."

### Understanding Transaction Types (2 minutes)
"Let me explain each type:

**Deposit**: Money added to the account
- Example: Customer payment deposited
- Effect: Balance increases

**Withdrawal**: Money taken from the account
- Example: Paying a vendor
- Effect: Balance decreases

**Transfer**: Moving money between accounts
- Example: Transfer from checking to savings
- Effect: One account decreases, another increases

**Fee**: Bank charges
- Example: Monthly maintenance fee
- Effect: Balance decreases

**Interest**: Interest earned
- Example: Savings account interest
- Effect: Balance increases"

### Viewing Bank Transactions (1 minute)
"[Screen recording]
The Banking & Cash table shows:
- Date
- Type (color-coded: green for deposits, red for withdrawals)
- Account
- Description
- Amount (color-coded)
- Status
- Actions (View button)

Click 'View' to see complete transaction details."

### Bank Reconciliation - Complete Process (5 minutes)
"Bank Reconciliation matches your records with the bank statement.

**Step 1: Get Your Bank Statement**
- Download or get your monthly bank statement
- Note the statement balance and date

**Step 2: Start Reconciliation**
[Screen recording]
1. Click 'Reconcile' button
2. Select the bank account
3. Enter the reconciliation date (statement date)
4. Enter the statement balance from the bank
5. Click 'Start Reconciliation'

**Step 3: Review Results**
The system shows:
- **Book Balance**: What your records show
- **Statement Balance**: What the bank shows
- **Difference**: Any discrepancy

**Step 4: Investigate Differences**
If there's a difference:
- Check for missing transactions
- Look for bank fees not recorded
- Verify all deposits and withdrawals
- Check for timing differences (checks not yet cleared)

**Step 5: Finalize**
Once everything matches:
- Update status to 'Reconciled'
- Add notes if needed
- Finalize the reconciliation"

### Reconciliation Best Practices (1 minute)
"Best practices:
- Reconcile monthly (at minimum)
- Do it promptly (don't wait)
- Investigate differences immediately
- Document adjustments with notes
- Keep bank statements for records"

---

## VIDEO 5: Vouchers - Payment & Receipt (10-12 minutes)

### Introduction (30 seconds)
"Welcome! In this video, we'll learn about Vouchers - official documents for recording payments and receipts."

### Understanding Vouchers (1 minute)
"There are two types of vouchers:

**1. Payment Voucher**: Money you PAY OUT
- To vendors, suppliers, workers, etc.
- Red badge in the system

**2. Receipt Voucher**: Money you RECEIVE
- From customers, clients, etc.
- Green badge in the system"

### Creating a Payment Voucher (4 minutes)
"[Screen recording]
Let's create a payment voucher:

1. Go to 'Vouchers' tab
2. Click 'Payment Voucher' button
3. Fill in the form:

**Required Fields:**
- **Payment Date**: When payment was made
- **Payment Method**: Cash, Bank Transfer, Cheque, Credit Card, Other
- **Amount**: Payment amount
- **Currency**: SAR, USD, etc.

**Optional Fields:**
- **Vendor ID**: If paying a vendor
- **Entity Type**: Agent, Subagent, Worker, HR
- **Entity ID**: Specific entity
- **Reference Number**: Invoice number or reference
- **Cheque Number**: If paid by cheque
- **Notes**: Additional information
- **Status**: Draft, Sent, Cleared

4. Click 'Create Payment Voucher'

The voucher is created with an auto-generated number like PAY-00000001."

### Creating a Receipt Voucher (3 minutes)
"[Screen recording]
Creating a receipt voucher is similar:

1. Click 'Receipt Voucher' button
2. Fill in the same fields, but:
   - Use **Customer ID** instead of Vendor ID
   - Status options: Draft, Deposited, Cleared
3. Click 'Create Receipt Voucher'

Receipts get numbers like REC-00000001."

### Viewing Vouchers (1 minute)
"The Vouchers table shows all payment and receipt vouchers with:
- Voucher Number
- Date
- Type (color-coded badges)
- Party (Vendor/Customer/Entity)
- Payment Method
- Amount
- Status
- Actions (View, Print)

Click 'View' to see complete details including reference numbers and notes."

### Printing Vouchers (1 minute)
"[Screen recording]
To print a voucher:
1. Click 'Print' button
2. Print dialog opens automatically
3. Select your printer
4. Click 'Print'
5. Window closes automatically

The printed voucher includes all details in a professional format."

### Filtering Vouchers (1 minute)
"You can filter vouchers:
- **By Type**: All, Payment only, Receipt only
- **By Date**: Date From and Date To
- Summary cards show total payments and total receipts"

### Linking Vouchers to Invoices/Bills (30 seconds)
"Use the Reference Number field to link vouchers to invoices or bills. This helps track which payments apply to which invoices."

---

## VIDEO 6: Invoices & Bills Management (10-12 minutes)

### Introduction (30 seconds)
"Welcome! In this video, we'll learn how to manage Invoices (money owed to you) and Bills (money you owe)."

### Understanding Invoices vs Bills (2 minutes)
"**Invoices (Accounts Receivable)**:
- Money CUSTOMERS owe YOU
- You send invoices to customers
- When they pay, create a Receipt Voucher
- Blue badge in the system

**Bills (Accounts Payable)**:
- Money YOU owe to VENDORS
- Vendors send you bills
- When you pay, create a Payment Voucher
- Yellow badge in the system"

### Combined View (1 minute)
"[Screen recording]
The Invoices tab shows BOTH invoices and bills in one table. This gives you a complete picture of:
- What customers owe you
- What you owe vendors
- All in one place"

### Table Columns Explained (2 minutes)
"The table shows:
- **#**: Invoice or Bill number
- **Date**: Issue date
- **Party**: Customer (invoices) or Vendor (bills)
- **Due Date**: When payment is due
- **Type**: Invoice (blue) or Bill (yellow) badge
- **Total**: Total amount
- **Paid**: Amount already paid
- **Balance**: Remaining amount owed
- **Status**: Draft, Sent, Partially Paid, Paid, Overdue, etc.
- **Actions**: View, Print buttons"

### Summary Cards (1 minute)
"At the top, you'll see summary cards:
- **Outstanding Receivables**: Total money customers owe you
- **Outstanding Payables**: Total money you owe vendors
- **Overdue**: Amounts past their due date (in red)"

### Filtering Options (2 minutes)
"[Screen recording]
You can filter in multiple ways:

**By Type:**
- All Types: Both invoices and bills
- Invoice: Only invoices
- Bill: Only bills

**By Status:**
- All Statuses
- Draft: Not yet sent
- Sent: Sent to customer/vendor
- Partially Paid: Some payment received
- Paid: Fully paid
- Overdue: Past due date
- Cancelled or Voided

**By Date:**
- Date From and Date To

**By Search:**
- Search by invoice/bill number or party name"

### Viewing Invoice/Bill Details (1 minute)
"[Screen recording]
Click 'View' to see complete details:
- Invoice/Bill Number
- Date and Due Date
- Party information
- Total, Paid, and Balance amounts
- Status
- Line Items (if any)
- Notes

You can also Edit or Print from the detail view."

### Printing Invoices & Bills (1 minute)
"[Screen recording]
To print:
1. Click 'Print' button
2. Print dialog opens
3. Select printer and print
4. Window closes automatically

The printed document is formatted professionally with all details."

### Tracking Payments (1 minute)
"To track which payments apply to which invoices/bills:
- Use the Reference Number field in vouchers
- Match voucher reference to invoice/bill number
- Update invoice/bill status as payments are received/made"

---

## VIDEO 7: Financial Reports (8-10 minutes)

### Introduction (30 seconds)
"Welcome! In this video, we'll explore the Financial Reports module - where you get insights into your business performance."

### Available Reports Overview (2 minutes)
"The system provides 11 different reports:

1. **Trial Balance**: All accounts with balances
2. **Income Statement**: Revenue minus Expenses (Profit/Loss)
3. **Balance Sheet**: Assets, Liabilities, Equity
4. **Cash Flow Statement**: Cash movements
5. **Aged Receivables**: Money owed by age
6. **Aged Payables**: Money you owe by age
7. **Cash Book**: All cash transactions
8. **Bank Book**: All bank transactions
9. **General Ledger Report**: Detailed ledger
10. **Expense Statement**: Expense breakdown
11. **Chart of Accounts Report**: Complete account list"

### Generating a Report (2 minutes)
"[Screen recording]
To generate a report:

1. Go to 'Financial Reports' tab
2. You'll see report cards in a grid
3. Click on any report card (e.g., 'Trial Balance')
4. The report generates and displays
5. Use filters if available:
   - Date Range
   - Account Selection
   - Entity Selection
6. Review the report
7. Print or Export if needed"

### Understanding Key Reports (4 minutes)

**Trial Balance:**
"Shows all accounts with their debit and credit balances. Total debits should equal total credits. This verifies your accounting is correct."

**Income Statement:**
"Shows Revenue and Expenses for a period. Calculates Net Income (Revenue - Expenses). Shows if you made a profit or loss."

**Balance Sheet:**
"Shows your financial position at a point in time:
- Assets (what you own)
- Liabilities (what you owe)
- Equity (owner's stake)

Must balance: Assets = Liabilities + Equity"

**Cash Flow:**
"Shows where cash came from and where it went:
- Operating activities (day-to-day business)
- Investing activities (buying/selling assets)
- Financing activities (loans, owner investments)

Helps understand your cash position."

**Aged Receivables:**
"Shows money customers owe you, organized by how old the debt is:
- Current (0-30 days)
- 31-60 days
- 61-90 days
- Over 90 days

Helps identify collection issues."

**Aged Payables:**
"Similar to receivables, but shows what you owe vendors by age. Helps manage payment priorities."

### Using Reports for Decision Making (1 minute)
"Reports help you:
- Understand financial performance
- Identify trends
- Make informed decisions
- Prepare for tax filing
- Present to stakeholders
- Plan for the future"

---

## VIDEO 8: Best Practices & Daily Workflow (7-10 minutes)

### Introduction (30 seconds)
"Welcome to the final video! Here we'll cover best practices and show you an efficient daily workflow."

### Daily Workflow (3 minutes)
"Here's a recommended daily workflow:

**Morning (10 minutes):**
1. Check Dashboard for overnight transactions
2. Review any pending items
3. Check bank balances

**During the Day:**
- Record transactions as they happen (don't wait!)
- Create vouchers immediately when payments/receipts occur
- Link vouchers to invoices/bills using reference numbers

**End of Day (15 minutes):**
1. Review all transactions entered today
2. Verify balances
3. Check for any errors
4. Update statuses (Draft → Posted)

**Weekly (30 minutes):**
1. Reconcile bank accounts
2. Review outstanding receivables and payables
3. Generate key reports
4. Check for overdue items"

### Monthly Tasks (2 minutes)
"**Monthly Routine:**

1. **Bank Reconciliation** (First week):
   - Get bank statements
   - Reconcile all accounts
   - Investigate and resolve differences
   - Finalize reconciliations

2. **Review Reports** (Mid-month):
   - Generate Income Statement
   - Review Balance Sheet
   - Check Aged Receivables/Payables
   - Identify issues early

3. **Month-End Closing** (Last day):
   - Verify all transactions recorded
   - Review and post any drafts
   - Generate final reports
   - Close the period (if using periods)"

### Best Practices Summary (2 minutes)
"**Always:**
✅ Verify debits = credits before saving
✅ Use clear, descriptive descriptions
✅ Link transactions to entities when applicable
✅ Reconcile bank accounts monthly
✅ Review reports regularly
✅ Keep reference numbers for tracking
✅ Back up your data regularly

**Never:**
❌ Save unbalanced journal entries
❌ Mix debits and credits on the same line
❌ Delete accounts with transactions
❌ Skip reconciliation
❌ Ignore differences in reconciliation
❌ Use vague descriptions"

### Common Mistakes to Avoid (2 minutes)
"**Mistake 1: Waiting to Enter Transactions**
- Enter transactions immediately
- Don't let them pile up
- Easier to remember details

**Mistake 2: Unclear Descriptions**
- Bad: 'Payment'
- Good: 'Payment to Worker Ahmed for November services - Invoice #123'

**Mistake 3: Not Reconciling**
- Reconcile monthly minimum
- Catches errors early
- Ensures accuracy

**Mistake 4: Ignoring Balance Status**
- Always check the balance indicator
- Red = problem, fix it before saving

**Mistake 5: Not Linking to Entities**
- Link transactions to agents, workers, etc.
- Makes reporting and tracking easier"

### Tips for Efficiency (1 minute)
"**Efficiency Tips:**

1. **Use Filters**: Don't scroll through hundreds of rows - use filters
2. **Keyboard Shortcuts**: Learn F5 for refresh, Esc to close modals
3. **Batch Operations**: Enter similar transactions together
4. **Templates**: For recurring transactions, copy and modify
5. **Regular Reviews**: Weekly reviews prevent monthly headaches"

### Getting Help (30 seconds)
"If you encounter issues:
1. Check the browser console (F12)
2. Review error messages
3. Check the troubleshooting guide
4. Verify your permissions
5. Check database status"

### Conclusion (30 seconds)
"Congratulations! You've completed the A-Z Accounting System tutorial. You now know how to use every feature of the system. Remember to practice regularly, follow best practices, and don't hesitate to refer back to these videos.

Thank you for watching, and happy accounting!"

---

## Video Production Notes

### Technical Requirements
- **Screen Recording Software**: OBS Studio, Camtasia, or ScreenFlow
- **Microphone**: Good quality microphone for clear audio
- **Resolution**: 1920x1080 (Full HD) minimum
- **Frame Rate**: 30 FPS
- **Audio**: Clear narration, no background noise

### Recording Tips
1. **Prepare Script**: Read through script before recording
2. **Practice**: Do a dry run first
3. **Clear Screen**: Close unnecessary applications
4. **Slow Pace**: Speak clearly and slowly
5. **Pause**: Pause between sections for easy editing
6. **Highlight**: Use cursor highlights or annotations
7. **Zoom**: Zoom in on important areas

### Editing Tips
1. **Intro/Outro**: Add branded intro and outro
2. **Transitions**: Smooth transitions between sections
3. **Captions**: Add captions for accessibility
4. **Chapters**: Add chapter markers for easy navigation
5. **Thumbnails**: Create eye-catching thumbnails
6. **Annotations**: Add text annotations for key points

### Distribution
- **YouTube**: Upload as a playlist
- **Internal Training**: Host on company intranet
- **Documentation**: Link from help documentation
- **Embed**: Embed in user dashboard

---

**Total Video Length**: Approximately 60-75 minutes (8 videos)  
**Recommended Release**: One video per week for 8 weeks  
**Update Frequency**: Update videos when system features change

