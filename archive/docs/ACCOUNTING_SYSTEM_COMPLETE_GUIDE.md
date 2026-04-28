# Accounting System - Complete User Guide (A to Z)

## Table of Contents
1. [System Overview](#system-overview)
2. [Getting Started](#getting-started)
3. [Dashboard](#dashboard)
4. [Journal Entries](#journal-entries)
5. [Chart of Accounts](#chart-of-accounts)
6. [Banking & Cash](#banking--cash)
7. [Vouchers](#vouchers)
8. [Invoices & Bills](#invoices--bills)
9. [Financial Reports](#financial-reports)
10. [Settings](#settings)
11. [Best Practices](#best-practices)
12. [Troubleshooting](#troubleshooting)

---

## System Overview

### What is This Accounting System?

This is a **professional double-entry accounting system** designed for managing:
- Financial transactions
- Journal entries
- Bank accounts and transactions
- Payment vouchers (receipts and payments)
- Invoices and bills
- Financial reports

### Key Features

✅ **Double-Entry Bookkeeping** - Every transaction has equal debits and credits  
✅ **Entity Management** - Track transactions for Agents, Subagents, Workers, and HR  
✅ **Bank Reconciliation** - Match bank statements with your records  
✅ **Voucher System** - Create payment and receipt vouchers  
✅ **Financial Reports** - Generate comprehensive financial statements  
✅ **Multi-Currency Support** - Handle SAR, USD, EUR, GBP, JOD  

### Accounting Principles Used

1. **Double-Entry System**: Every transaction affects at least two accounts
2. **Debit = Credit**: Total debits must always equal total credits
3. **Accrual Basis**: Transactions recorded when they occur, not when cash is received/paid
4. **Chart of Accounts**: Organized account structure (Assets, Liabilities, Equity, Income, Expenses)

---

## Getting Started

### Accessing the Accounting Module

1. **Login** to your system
2. **Navigate** to the Accounting module from the main menu
3. **Verify Permissions**: Ensure you have `accounting_view` permission (minimum)

### First Time Setup

1. **Check Database**: Run `database/final-accounting-database-check.sql` to verify all tables exist
2. **Set Up Chart of Accounts**: Go to "Chart of Accounts" tab
3. **Add Bank Accounts**: Go to "Banking & Cash" → Add your bank accounts
4. **Configure Settings**: Go to "Settings" tab for system configuration

### Understanding the Interface

- **Tabs**: Main navigation at the top (Dashboard, Journal Entries, etc.)
- **Summary Cards**: Quick overview of totals and balances
- **Data Tables**: Detailed lists with filters and pagination
- **Action Buttons**: Create, Edit, View, Print, Delete operations
- **Modals**: Forms and detailed views open in popup windows

---

## Dashboard

### Purpose
The Dashboard provides a **quick overview** of your financial status at a glance.

### What You'll See

#### Summary Cards
- **Total Revenue**: All income transactions
- **Total Expenses**: All expense transactions
- **Net Profit/Loss**: Revenue minus Expenses
- **Cash Balance**: Current cash on hand
- **Bank Balance**: Total in all bank accounts
- **Outstanding Receivables**: Money owed to you
- **Outstanding Payables**: Money you owe

#### Quick Actions
- **Quick Entry**: Fast transaction entry
- **View Transactions**: See all transactions in a detailed table
- **Refresh**: Update all dashboard data

### How to Use

1. **View Summary**: Check the cards for quick status
2. **Click "View Transactions"**: Opens detailed transaction history
3. **Filter Transactions**: Use Entity Type, Date Range, Account filters
4. **View Details**: Click "View" button on any transaction
5. **Print**: Click "Print" to generate a printable receipt

### Transaction History Modal

When you click "View Transactions", you'll see:

**Filters:**
- **Entity Type**: Agent, Subagent, Worker, HR, or All
- **Entity**: Specific entity selection (after selecting type)
- **Date From/To**: Date range filter
- **Account**: Filter by specific account
- **Show Entries**: Number of rows per page (default: 5)

**Table Columns:**
- Entry #: Transaction identifier
- Entity: Who the transaction is for
- Date: Transaction date
- Description: Transaction details
- Type: Income, Expense, Transfer, etc.
- Debit: Debit amount (red)
- Credit: Credit amount (green)
- Status: Draft, Posted, etc.

**Actions:**
- **View**: See full transaction details
- **Print**: Print transaction receipt

---

## Journal Entries

### Purpose
Journal Entries are the **foundation of double-entry bookkeeping**. Every financial transaction is recorded as a journal entry with equal debits and credits.

### Understanding Journal Entries

**What is a Journal Entry?**
- A record of a financial transaction
- Must have equal debits and credits
- Can have multiple lines (accounts)
- Example: Paying salary
  - Debit: Salary Expense (increases expense)
  - Credit: Cash (decreases asset)

### Creating a New Journal Entry

1. **Click "New Journal Entry"** button
2. **Fill in the Form:**

   **Required Fields:**
   - **Date**: Transaction date
   - **Description**: What the transaction is for
   - **Account**: Which account to debit or credit
   - **Debit Amount**: OR **Credit Amount** (not both!)
   - **Currency**: SAR, USD, EUR, etc.
   - **Reference**: Optional reference number

   **Optional Fields:**
   - **Entity**: Link to Agent, Subagent, Worker, or HR
   - **Entry #**: Auto-generated if left blank

3. **Check Balance Status:**
   - Green "Balanced" = Debits = Credits ✅
   - Red "Unbalanced" = Debits ≠ Credits ❌
   - Yellow "Single entry" = Only one side entered

4. **Click "Save"** when balanced

### Journal Entry Form Layout

The form uses a **2-column layout**:

**Left Column:**
- Entry # (read-only, auto-generated)
- Date
- Type (read-only)
- Debit Amount
- Account

**Right Column:**
- Status (read-only)
- Currency
- Credit Amount
- Entity

**Full Width:**
- Description (spans both columns)
- Balance Status (shows if balanced)

### Editing a Journal Entry

1. **Find the entry** in the General Ledger table
2. **Click "Edit"** button
3. **Modify** the fields as needed
4. **Ensure balance** (debits = credits)
5. **Click "Save"**

### Viewing Journal Entry Details

1. **Click "View"** on any entry
2. **See Complete Details:**
   - Entry ID and Number
   - Reference Number
   - Entry Date
   - Entry Type
   - Description
   - Account Name
   - Debit and Credit Amounts
   - Currency
   - Status
   - Created By
   - Created At
   - Updated At

3. **Actions Available:**
   - **Edit Entry**: Modify the entry
   - **Print Entry**: Print a copy

### General Ledger View

The General Ledger shows **all journal entries and transactions**:

**Summary Cards:**
- **Total Entries**: Number of journal entries
- **Total Debit**: Sum of all debits
- **Total Credit**: Sum of all credits
- **Balance**: Difference (should be 0 if balanced)
- **Posted**: Number of posted entries
- **Draft**: Number of draft entries
- **Entity Counts**: Entries per entity type (Agents, Workers, etc.)

**Filters:**
- **Date From/To**: Filter by date range
- **Account**: Filter by specific account
- **Show Entries**: Rows per page

**Table Columns:**
- Entry #: Journal entry number
- Entity: Linked entity (if any)
- Date: Transaction date
- Description: Transaction description (truncated to 60 chars)
- Type: Transaction type
- Amount: Debit (red) or Credit (green) in single column
- Status: Posted, Draft, etc.

**Pagination:**
- **Top Pagination**: First ⏮, Previous ◀, Page Numbers, Next ▶, Last ⏭
- **Page Numbers**: Shows current page and nearby pages (max 5 visible)
- **Info**: "Showing X to Y of Z entries"

### Account Filtering

When you select an account from the dropdown:

1. **System automatically filters** transactions
2. **Shows transactions** linked to that account
3. **For entity accounts** (like "Worker Revenue"):
   - Shows all transactions for that entity type
   - Example: "Worker Revenue" shows all worker transactions

### Best Practices for Journal Entries

✅ **Always ensure debits = credits** before saving  
✅ **Use clear descriptions** for easy reference  
✅ **Link to entities** when applicable  
✅ **Use reference numbers** for tracking  
✅ **Review before posting** (check balance status)  
❌ **Never save unbalanced entries**  
❌ **Don't mix debits and credits** on the same line  

---

## Chart of Accounts

### Purpose
The Chart of Accounts is the **master list of all accounts** in your accounting system. It's organized by account type (Assets, Liabilities, Equity, Income, Expenses).

### Understanding Account Types

**Assets** (1000-1999):
- What you own
- Examples: Cash, Bank Accounts, Accounts Receivable, Equipment
- Normal Balance: **Debit** (increases with debits)

**Liabilities** (2000-2999):
- What you owe
- Examples: Accounts Payable, Loans, Taxes Payable
- Normal Balance: **Credit** (increases with credits)

**Equity** (3000-3999):
- Owner's stake in the business
- Examples: Owner Capital, Retained Earnings
- Normal Balance: **Credit** (increases with credits)

**Income** (4000-4999):
- Money coming in
- Examples: Sales Revenue, Service Revenue, Agent Revenue
- Normal Balance: **Credit** (increases with credits)

**Expenses** (5000-5999):
- Money going out
- Examples: Salaries, Rent, Utilities, Worker Payments
- Normal Balance: **Debit** (increases with debits)

### Viewing Chart of Accounts

1. **Go to "Chart of Accounts" tab**
2. **See All Accounts** in a table with:
   - **Code**: Account code (e.g., 1100)
   - **Account Name**: Full account name
   - **Type**: Asset, Liability, Equity, Income, Expense
   - **Normal Balance**: Debit or Credit
   - **Opening Balance**: Starting balance
   - **Current Balance**: Current balance
   - **Status**: Active or Inactive

### Filtering Accounts

**By Account Type:**
- Select from dropdown: All Types, Asset, Liability, Equity, Income, Expense
- Table updates automatically

**By Search:**
- Type in search box
- Searches account code and name
- Updates as you type

**Clear Filters:**
- Click "Clear" button to reset all filters

### Creating a New Account

1. **Click "New Account" button**
2. **Fill in the Form:**
   - **Account Code**: Unique code (e.g., 1200)
   - **Account Name**: Full name (e.g., "Bank Account - Main")
   - **Account Type**: Select from dropdown
   - **Normal Balance**: Usually auto-set based on type
   - **Opening Balance**: Starting balance
   - **Description**: Optional notes
   - **Status**: Active or Inactive

3. **Click "Save"**

### Editing an Account

1. **Find the account** in the table
2. **Click "Edit" button**
3. **Modify** the fields
4. **Click "Save"**

**Note**: Some accounts may be system accounts and cannot be edited.

### Deleting an Account

1. **Find the account** in the table
2. **Click "Delete" button**
3. **Confirm** the deletion

**Warning**: Accounts with transactions cannot be deleted. You must first delete or void all related transactions.

### Exporting Accounts

1. **Click "Export" button**
2. **Downloads** a CSV file with all accounts
3. **Use** for backup or external analysis

---

## Banking & Cash

### Purpose
The Banking & Cash module manages **bank accounts, transactions, and reconciliation**.

### Bank Accounts

#### Viewing Bank Accounts
- **Summary Cards**: Total cash, total bank balance, unreconciled amounts
- **Table**: Lists all bank accounts with balances

#### Adding a Bank Account
1. **Click "Add Bank Account"** (in Settings or Banking tab)
2. **Fill in:**
   - **Bank Name**: Name of the bank
   - **Account Name**: Account identifier
   - **Account Number**: Bank account number
   - **Account Type**: Checking or Savings
   - **Opening Balance**: Starting balance
3. **Click "Save"**

### Bank Transactions

#### Creating a Bank Transaction

1. **Click "New Transaction" button**
2. **Fill in the Form:**
   - **Bank Account**: Select from dropdown
   - **Transaction Date**: Date of transaction
   - **Transaction Type**: 
     - **Deposit**: Money coming in
     - **Withdrawal**: Money going out
     - **Transfer**: Between accounts
     - **Fee**: Bank charges
     - **Interest**: Interest earned
   - **Amount**: Transaction amount
   - **Description**: What the transaction is for
   - **Reference Number**: Optional reference

3. **Click "Save"**

**Note**: Deposits and Interest **increase** bank balance. Withdrawals and Fees **decrease** bank balance.

#### Viewing Bank Transactions

The Banking & Cash table shows:
- **Date**: Transaction date
- **Type**: Deposit, Withdrawal, etc. (color-coded)
- **Account**: Which bank account
- **Description**: Transaction details
- **Amount**: Amount (green for deposits, red for withdrawals)
- **Status**: Active, Cleared, etc.
- **Actions**: View button

#### Viewing Transaction Details

1. **Click "View"** on any transaction
2. **See Complete Details:**
   - Transaction ID
   - Bank Account
   - Transaction Date
   - Type
   - Amount
   - Description
   - Reference Number
   - Created At

### Bank Reconciliation

#### What is Bank Reconciliation?

Bank Reconciliation matches your **book balance** (what you think you have) with your **bank statement balance** (what the bank says you have).

#### Starting a Reconciliation

1. **Click "Reconcile" button**
2. **Fill in the Form:**
   - **Bank Account**: Select the account to reconcile
   - **Reconciliation Date**: Date of the bank statement
   - **Statement Balance**: Balance from bank statement
3. **Click "Start Reconciliation"**

#### Understanding Reconciliation Results

The system will:
- **Get your book balance** (from all transactions)
- **Compare** with statement balance
- **Calculate difference** (if any)
- **Show results:**
  - Book Balance: What your records show
  - Statement Balance: What bank shows
  - Difference: Any discrepancy

#### Reconciliation Status

- **In Progress**: Reconciliation started but not finalized
- **Reconciled**: Matched and verified
- **Finalized**: Completed and locked

---

## Vouchers

### Purpose
Vouchers are **official documents** for recording payments (money going out) and receipts (money coming in).

### Types of Vouchers

1. **Payment Voucher**: Money you pay to vendors/suppliers
2. **Receipt Voucher**: Money you receive from customers

### Payment Vouchers

#### Creating a Payment Voucher

1. **Go to "Vouchers" tab**
2. **Click "Payment Voucher" button**
3. **Fill in the Form:**

   **Required Fields:**
   - **Payment Date**: When payment was made
   - **Payment Method**: Cash, Bank Transfer, Cheque, Credit Card, Other
   - **Amount**: Payment amount
   - **Currency**: SAR, USD, etc.

   **Optional Fields:**
   - **Vendor ID**: If paying a vendor
   - **Entity Type**: Agent, Subagent, Worker, HR
   - **Entity ID**: Specific entity
   - **Reference Number**: Invoice or reference number
   - **Cheque Number**: If paid by cheque
   - **Notes**: Additional information
   - **Status**: Draft, Sent, Cleared

4. **Click "Create Payment Voucher"**

#### Viewing Payment Vouchers

The Vouchers table shows:
- **Voucher #**: Auto-generated number (PAY-00000001)
- **Date**: Payment date
- **Type**: Payment (red badge) or Receipt (green badge)
- **Party**: Vendor or Customer name
- **Method**: Payment method
- **Amount**: Payment amount
- **Status**: Draft, Sent, Cleared, etc.
- **Actions**: View, Print

### Receipt Vouchers

#### Creating a Receipt Voucher

1. **Go to "Vouchers" tab**
2. **Click "Receipt Voucher" button**
3. **Fill in the Form:**

   **Required Fields:**
   - **Payment Date**: When payment was received
   - **Payment Method**: Cash, Bank Transfer, Cheque, Credit Card, Other
   - **Amount**: Receipt amount
   - **Currency**: SAR, USD, etc.

   **Optional Fields:**
   - **Customer ID**: If receiving from a customer
   - **Entity Type**: Agent, Subagent, Worker, HR
   - **Entity ID**: Specific entity
   - **Reference Number**: Invoice or reference number
   - **Cheque Number**: If received by cheque
   - **Notes**: Additional information
   - **Status**: Draft, Deposited, Cleared

4. **Click "Create Receipt Voucher"**

### Viewing Voucher Details

1. **Click "View"** on any voucher
2. **See Complete Details:**
   - Voucher Number
   - Date
   - Type (Payment/Receipt)
   - Party (Vendor/Customer/Entity)
   - Payment Method
   - Amount
   - Status
   - Reference Number (if any)
   - Notes (if any)

3. **Actions Available:**
   - **Close**: Return to list
   - **Print**: Print voucher

### Printing Vouchers

1. **Click "Print"** on any voucher
2. **Print Dialog Opens** automatically
3. **Select Printer** and print
4. **Window Closes** automatically after printing

### Filtering Vouchers

**By Type:**
- **All Types**: Shows both payments and receipts
- **Payment**: Only payment vouchers
- **Receipt**: Only receipt vouchers

**By Date:**
- **Date From**: Start date
- **Date To**: End date

**Summary Cards:**
- **Total Payments**: Sum of all payment vouchers
- **Total Receipts**: Sum of all receipt vouchers

---

## Invoices & Bills

### Purpose
The Invoices & Bills module combines **Accounts Receivable** (money owed to you) and **Accounts Payable** (money you owe) in one view.

### Understanding Invoices vs Bills

**Invoices (Accounts Receivable):**
- Money **customers owe you**
- You send invoices to customers
- When paid, create a Receipt Voucher

**Bills (Accounts Payable):**
- Money **you owe to vendors**
- Vendors send you bills
- When paid, create a Payment Voucher

### Combined View

The Invoices tab shows **both invoices and bills** in one table:

**Table Columns:**
- **#**: Invoice or Bill number
- **Date**: Issue date
- **Party**: Customer (for invoices) or Vendor (for bills)
- **Due Date**: When payment is due
- **Type**: Invoice (blue badge) or Bill (yellow badge)
- **Total**: Total amount
- **Paid**: Amount already paid
- **Balance**: Remaining amount owed
- **Status**: Draft, Sent, Partially Paid, Paid, Overdue, etc.
- **Actions**: View, Print

### Summary Cards

- **Outstanding Receivables**: Total money customers owe you
- **Outstanding Payables**: Total money you owe vendors
- **Overdue**: Amounts past due date

### Filtering Invoices & Bills

**By Type:**
- **All Types**: Both invoices and bills
- **Invoice**: Only invoices
- **Bill**: Only bills

**By Status:**
- **All Statuses**: All invoices/bills
- **Draft**: Not yet sent
- **Sent**: Sent to customer/vendor
- **Partially Paid**: Some payment received
- **Paid**: Fully paid
- **Overdue**: Past due date
- **Cancelled**: Cancelled
- **Voided**: Voided

**By Date:**
- **Date From**: Start date
- **Date To**: End date

**By Search:**
- Search by invoice/bill number or party name

### Viewing Invoice/Bill Details

1. **Click "View"** on any invoice or bill
2. **See Complete Details:**
   - Invoice/Bill Number
   - Date and Due Date
   - Party (Customer/Vendor)
   - Total Amount
   - Paid Amount
   - Balance Amount
   - Status
   - Line Items (if any)
   - Notes

3. **Actions Available:**
   - **Edit**: Modify invoice/bill
   - **Print**: Print invoice/bill

### Printing Invoices & Bills

1. **Click "Print"** on any invoice or bill
2. **Print Dialog Opens** automatically
3. **Select Printer** and print
4. **Window Closes** automatically after printing

---

## Financial Reports

### Purpose
Financial Reports provide **insights into your financial performance** and help with decision-making.

### Available Reports

1. **Trial Balance**: Lists all accounts with their balances
2. **Income Statement**: Revenue minus Expenses (Profit/Loss)
3. **Balance Sheet**: Assets, Liabilities, and Equity
4. **Cash Flow Statement**: Cash inflows and outflows
5. **Aged Receivables**: Money owed to you by age
6. **Aged Payables**: Money you owe by age
7. **Cash Book**: All cash transactions
8. **Bank Book**: All bank transactions
9. **General Ledger Report**: Detailed ledger entries
10. **Expense Statement**: Breakdown of expenses
11. **Chart of Accounts Report**: Complete account listing

### Generating Reports

1. **Go to "Financial Reports" tab**
2. **Click on a Report Card** (e.g., "Trial Balance")
3. **Report Generates** and displays
4. **Use Filters** (if available):
   - Date Range
   - Account Selection
   - Entity Selection
5. **Print or Export** the report

### Understanding Reports

**Trial Balance:**
- Shows all accounts with debit and credit balances
- Total debits should equal total credits
- Used to verify accounting accuracy

**Income Statement:**
- Shows Revenue and Expenses
- Calculates Net Income (Revenue - Expenses)
- Shows profit or loss for a period

**Balance Sheet:**
- Shows Assets, Liabilities, and Equity
- Must balance: Assets = Liabilities + Equity
- Snapshot of financial position

**Cash Flow:**
- Shows where cash came from and where it went
- Operating, Investing, and Financing activities
- Helps understand cash position

---

## Settings

### Purpose
Settings allow you to **configure the accounting system** to match your business needs.

### Available Settings

1. **Chart of Accounts Management**: Add, edit, delete accounts
2. **Financial Periods**: Set up accounting periods/years
3. **Tax Settings**: Configure tax rates and rules
4. **System Settings**: General accounting preferences

### Accessing Settings

1. **Go to "Settings" tab**
2. **Click "Manage"** on any setting category
3. **Configure** as needed
4. **Save** changes

---

## Best Practices

### General Guidelines

✅ **Always verify debits = credits** before saving  
✅ **Use clear, descriptive transaction descriptions**  
✅ **Link transactions to entities** when applicable  
✅ **Reconcile bank accounts regularly** (monthly recommended)  
✅ **Review financial reports** regularly  
✅ **Keep reference numbers** for tracking  
✅ **Back up your data** regularly  

### Transaction Entry

✅ **Enter transactions promptly** (don't wait)  
✅ **Review before posting** (check all details)  
✅ **Use appropriate accounts** (don't mix account types)  
✅ **Document everything** (add notes when needed)  

### Voucher Management

✅ **Create vouchers immediately** when payments/receipts occur  
✅ **Match vouchers to invoices/bills** using reference numbers  
✅ **Update status** as vouchers are processed  
✅ **Keep printed copies** for records  

### Bank Reconciliation

✅ **Reconcile monthly** (at minimum)  
✅ **Investigate differences** immediately  
✅ **Document adjustments** with notes  
✅ **Finalize reconciliations** when complete  

### Reporting

✅ **Generate reports regularly** (weekly/monthly)  
✅ **Review trends** over time  
✅ **Compare periods** (month-over-month, year-over-year)  
✅ **Export for analysis** if needed  

---

## Troubleshooting

### Common Issues and Solutions

#### Issue: "Journal entry is not balanced"
**Solution:**
- Check that total debits = total credits
- Ensure you're not entering both debit and credit on the same line
- Review the balance status indicator (should be green)

#### Issue: "Cannot save transaction"
**Solution:**
- Check all required fields are filled
- Verify permissions (need `accounting_create`)
- Check for validation errors (red messages)

#### Issue: "Account filter shows no results"
**Solution:**
- Some accounts (like "Worker Revenue") filter by entity type, not account ID
- Try selecting the entity type filter instead
- Check if transactions are linked to that account

#### Issue: "Bank balance doesn't match"
**Solution:**
- Run bank reconciliation
- Check for missing transactions
- Verify all deposits and withdrawals are recorded
- Check for bank fees or interest

#### Issue: "Foreign key constraint error"
**Solution:**
- Ensure referenced tables exist (banks, customers, vendors, users)
- Check that referenced IDs exist
- Run `database/fix-new-accounting-tables.sql` to add missing foreign keys

#### Issue: "Table not found error"
**Solution:**
- Run `database/create-new-accounting-tables-safe.sql`
- Or access the API endpoint (it will auto-create the table)
- Verify database connection

#### Issue: "Print not working"
**Solution:**
- Allow popups in your browser
- Check browser print settings
- Try a different browser
- Ensure printer is connected

### Getting Help

1. **Check Console**: Open browser Developer Tools (F12) → Console tab
2. **Check Network**: See API calls and responses
3. **Review Logs**: Check server error logs
4. **Verify Permissions**: Ensure you have required permissions
5. **Check Database**: Run verification scripts

---

## Video Script Outline

If you want to create a video tutorial, here's a suggested structure:

### Video 1: Introduction (5 minutes)
- System overview
- Accessing the module
- Interface tour
- Basic navigation

### Video 2: Journal Entries (10 minutes)
- What are journal entries?
- Creating a new entry
- Understanding debits and credits
- Balance validation
- Editing entries
- Viewing details

### Video 3: Chart of Accounts (8 minutes)
- Understanding account types
- Viewing accounts
- Creating new accounts
- Editing accounts
- Filtering and searching

### Video 4: Banking & Cash (12 minutes)
- Adding bank accounts
- Creating bank transactions
- Viewing transactions
- Bank reconciliation process
- Understanding balances

### Video 5: Vouchers (10 minutes)
- Payment vouchers (creating, viewing, printing)
- Receipt vouchers (creating, viewing, printing)
- Filtering vouchers
- Linking to invoices/bills

### Video 6: Invoices & Bills (10 minutes)
- Understanding invoices vs bills
- Viewing combined list
- Filtering options
- Viewing details
- Printing invoices/bills

### Video 7: Financial Reports (8 minutes)
- Available reports
- Generating reports
- Understanding each report type
- Exporting reports

### Video 8: Best Practices & Tips (7 minutes)
- Daily workflow
- Monthly reconciliation
- Year-end closing
- Common mistakes to avoid

---

## Quick Reference Card

### Keyboard Shortcuts
- **F5**: Refresh current view
- **Ctrl+P**: Print (when in print view)
- **Esc**: Close modal

### Common Actions
- **Create**: Click "New" or "Add" button
- **Edit**: Click "Edit" button in table
- **View**: Click "View" button in table
- **Print**: Click "Print" button
- **Delete**: Click "Delete" button (with confirmation)

### Status Meanings
- **Draft**: Not yet finalized
- **Posted**: Finalized and recorded
- **Cleared**: Payment/receipt cleared
- **Overdue**: Past due date
- **Cancelled**: Cancelled transaction
- **Voided**: Voided transaction

### Color Coding
- **Red**: Debit amounts, Payment vouchers, Withdrawals
- **Green**: Credit amounts, Receipt vouchers, Deposits
- **Blue**: Invoices
- **Yellow**: Bills, Warnings

---

## Conclusion

This accounting system provides a **complete solution** for managing your finances. By following this guide and using the system regularly, you'll maintain accurate financial records and gain valuable insights into your business performance.

**Remember**: 
- Always verify debits = credits
- Reconcile regularly
- Keep detailed descriptions
- Review reports frequently
- Back up your data

For additional support, refer to the troubleshooting section or check the system logs.

---

**Last Updated**: 2025-11-23  
**System Version**: Professional Accounting v1.0  
**Database Status**: ✅ Production Ready

