<?php
/**
 * EN: Handles API endpoint/business logic in `api/help-center/seed-tutorial-content.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/help-center/seed-tutorial-content.php`.
 */
/**
 * Help Center - Detailed tutorial content explaining the entire Ratib program.
 * Used by seedDefaultTutorials() to populate training material.
 * Returns array: category_id => [title, overview, content_html]
 */

if (!function_exists('help_center_seed_content')) {

function help_center_seed_content() {
    return [
        1 => [
            'Getting Started – Complete Program Overview',
            'Introduction to the Ratib program: login, navigation, and how each section fits together.',
            '<h2>What is the Ratib Program?</h2>
            <p>The Ratib program is a full business management system. After you log in, you use the left sidebar to open <strong>Dashboard</strong>, <strong>Agent</strong>, <strong>SubAgent</strong>, <strong>Workers</strong>, <strong>Cases</strong>, <strong>Accounting</strong>, <strong>HR</strong>, <strong>Reports</strong>, <strong>Contact</strong>, <strong>Notifications</strong>, and <strong>System Settings</strong>. Each section has its own screen and purpose.</p>
            <h2>Logging In and First Steps</h2>
            <p>Go to the login page, enter your username and password, and click to sign in. If you forget your password, use the "Forgot password" link. After login you are taken to the <strong>Dashboard</strong>.</p>
            <h2>Understanding the Navigation</h2>
            <p>The main navigation is on the left. Only menu items you have permission to see will appear. Click any item (e.g. Agent, Workers, Accounting) to open that section. At the bottom of the menu you will find <strong>Help &amp; Learning Center</strong> (this guide) and <strong>System Settings</strong> (for admins).</p>
            <h2>How the Sections Work Together</h2>
            <p><strong>Dashboard</strong> gives you an overview. <strong>Agent</strong> and <strong>SubAgent</strong> manage your agents and sub-agents. <strong>Workers</strong> holds worker profiles and documents. <strong>Cases</strong> tracks cases or files. <strong>Accounting</strong> handles money, accounts, and transactions. <strong>HR</strong> covers employees and HR settings. <strong>Reports</strong> lets you run and view reports. <strong>Contact</strong> is for contact management. <strong>Notifications</strong> shows system alerts. Use this Help Center to learn each section in detail.</p>
            <h2>Expert Tips</h2>
            <ul><li>Bookmark the Dashboard or main page for quick access.</li><li>Check Notifications regularly so you do not miss important alerts.</li><li>Use the Help &amp; Learning Center whenever you need step-by-step guidance.</li></ul>'
        ],
        2 => [
            'Dashboard – Full Explanation',
            'What the Dashboard shows, how to read it, and how to use it for daily oversight.',
            '<h2>Purpose of the Dashboard</h2>
            <p>The Dashboard is your home screen after login. It shows a high-level overview of the program: key numbers, recent activity, and shortcuts so you can monitor the business without opening every section.</p>
            <h2>What You See on the Dashboard</h2>
            <p>Typical elements include: <strong>Summary cards or stats</strong> (e.g. total agents, workers, cases, or financial totals), <strong>Charts or graphs</strong> (e.g. trends over time), and <strong>Quick links or lists</strong> (e.g. recent items or pending tasks). The exact layout depends on your role and how the system is configured.</p>
            <h2>How to Use the Dashboard</h2>
            <ol><li>Open the program and land on the Dashboard (or click <strong>Dashboard</strong> in the left menu).</li><li>Review the summary numbers and charts to see current status and trends.</li><li>Use any filters or date ranges if the Dashboard offers them.</li><li>Click through links or buttons to go to the detailed section (e.g. Agents, Workers, Accounting) when you need to take action or see more detail.</li></ol>
            <h2>Expert Tips</h2>
            <ul><li>Refresh the page to get the latest data.</li><li>Use the Reports section for deeper analysis; the Dashboard is for a quick overview.</li><li>If you do not see certain numbers, your role may not have permission—contact your administrator.</li></ul>'
        ],
        3 => [
            'User Management & Permissions – Full Explanation',
            'How users, roles, and permissions work in the program and how to manage them (usually in System Settings).',
            '<h2>What User Management Covers</h2>
            <p>The program is used by multiple users. Each user has a <strong>role</strong> (e.g. Admin, Manager, Operator). Roles are linked to <strong>permissions</strong> that control what each user can see and do (e.g. view agents, edit workers, manage accounting). User management is typically done under <strong>System Settings</strong> or a dedicated Users/Roles area.</p>
            <h2>Main Concepts</h2>
            <p><strong>Users:</strong> People who log in (username, password, maybe name and email). <strong>Roles:</strong> Groups such as Admin or HR Manager. <strong>Permissions:</strong> Fine-grained rights like "view_agents", "edit_workers", "manage_settings". A user gets permissions through their role. Only users with the right permission can access certain menus and actions.</p>
            <h2>How to Manage Users and Permissions</h2>
            <ol><li>Go to <strong>System Settings</strong> (or the Users/Permissions section) from the left menu. You need admin or equivalent permission.</li><li>To add a user: create a new user, set username and password, and assign a role.</li><li>To change what a role can do: edit the role and enable or disable specific permissions (e.g. view reports, manage HR).</li><li>Save changes. The user will see only the menus and actions allowed by their role.</li></ol>
            <h2>Expert Tips</h2>
            <ul><li>Follow the principle of least privilege: give each role only the permissions it needs.</li><li>Review roles when job duties change.</li><li>Keep admin accounts secure and limit how many users have full access.</li></ul>'
        ],
        4 => [
            'Contracts & Recruitment – Full Explanation',
            'How contracts and recruitment are handled in the program (agents, workers, visas, or related modules).',
            '<h2>What This Section Is For</h2>
            <p>The program supports contract and recruitment workflows. This may be part of <strong>Agent</strong>, <strong>SubAgent</strong>, <strong>Workers</strong>, <strong>Cases</strong>, or a dedicated Contracts/Recruitment area. It is used to create agreements, track recruitment steps, and keep records in one place.</p>
            <h2>Main Features</h2>
            <p>You can typically: create and store contracts, link them to agents or clients, track status (draft, active, expired), manage recruitment steps (e.g. applications, visas), and view lists filtered by status or date.</p>
            <h2>How to Use Contracts and Recruitment</h2>
            <ol><li>Open the relevant section from the menu (e.g. Agent, Workers, or Contracts).</li><li>To add a contract: create a new record, fill in parties, dates, and terms; attach documents if the system allows.</li><li>To track recruitment: use the workflow or status fields to move candidates or workers through steps (e.g. applied, approved, visa issued).</li><li>Use filters and search to find contracts or recruitment items that need action.</li></ol>
            <h2>Expert Tips</h2>
            <ul><li>Use consistent templates or fields so reporting and search work well.</li><li>Update status regularly so the team always sees the current state.</li><li>Use status filters to focus on items that need follow-up.</li></ul>'
        ],
        5 => [
            'Client Management – Full Explanation',
            'How to manage clients (agents, sub-agents, or customers) in the Ratib program.',
            '<h2>What Client Management Is</h2>
            <p>In the Ratib program, "clients" may be your <strong>agents</strong> or <strong>sub-agents</strong> (partners who bring workers or business) or direct customers. Client management is the place where you store their details, contacts, and history so you can work with them efficiently.</p>
            <h2>Where You Manage Clients</h2>
            <p>Use the <strong>Agent</strong> and <strong>SubAgent</strong> pages from the left menu. Agent is usually the main partner; SubAgent is often a sub-partner under an agent. Each has a list view and forms to add or edit.</p>
            <h2>How to Add and Manage a Client (Agent / SubAgent)</h2>
            <ol><li>Click <strong>Agent</strong> or <strong>SubAgent</strong> in the sidebar.</li><li>Click the button to add a new agent or sub-agent (e.g. "Add Agent").</li><li>Fill in required fields: name, contact person, phone, email, address, and any IDs or codes your company uses.</li><li>Save. You can later attach workers, cases, or contracts to this agent or sub-agent.</li><li>To edit: open the record from the list and update the fields, then save.</li></ol>
            <h2>Using Client Data in the Rest of the Program</h2>
            <p>When you create workers, cases, or financial records, you often select an agent or sub-agent. That links the data to the right client so you can report and follow up by client.</p>
            <h2>Expert Tips</h2>
            <ul><li>Keep contact details and names consistent so search and reports are accurate.</li><li>Use notes or custom fields to record important agreements or calls.</li><li>Check the Reports section to see activity or revenue by agent/sub-agent.</li></ul>'
        ],
        6 => [
            'Worker Management – Full Explanation',
            'Complete guide to the Workers section: adding workers, documents, status, and daily management.',
            '<h2>What the Workers Section Does</h2>
            <p>The <strong>Workers</strong> section is where you register and manage workers (employees or labour). You store their personal data, documents (e.g. ID, visa, contract), and status (e.g. active, suspended, completed). Workers are often linked to an agent or sub-agent and to cases or contracts.</p>
            <h2>Opening the Workers Section</h2>
            <p>Click <strong>Workers</strong> in the left menu. You will see a list (table) of workers. You can search, filter by status or agent, and open each worker to view or edit.</p>
            <h2>Adding a New Worker</h2>
            <ol><li>Click the button to add a worker (e.g. "Add Worker" or "+ New").</li><li>Fill in the form: name, nationality, ID number, contact details, and any required fields (e.g. agent, visa type, start date).</li><li>Save the worker. Then go to the documents area for this worker to upload ID, visa, contract, or other files if the system supports it.</li></ol>
            <h2>Managing Documents and Status</h2>
            <p>Open a worker record. Use the documents tab or section to upload and label files. Change the worker status (e.g. active, suspended, left) when their situation changes. Some systems track Musaned or other external statuses—update those as required.</p>
            <h2>Expert Tips</h2>
            <ul><li>Upload and verify documents as soon as you have them to avoid delays.</li><li>Use filters (by status, agent, or date) to find workers who need attention.</li><li>Keep names and IDs consistent so searches and reports are reliable.</li></ul>'
        ],
        7 => [
            'Finance & Billing – Full Explanation',
            'How Accounting works in the program: accounts, transactions, and billing.',
            '<h2>What the Accounting Section Is For</h2>
            <p>The <strong>Accounting</strong> section (opened from the left menu) handles the financial side of the business: chart of accounts, income and expenses, transactions, and billing. You can track money per agent, per worker, or per project depending on how it is set up.</p>
            <h2>Main Parts of Accounting</h2>
            <p><strong>Chart of accounts:</strong> List of accounts (e.g. cash, bank, revenue, expenses). <strong>Transactions:</strong> Each movement of money is recorded (date, amount, account, description, maybe link to agent or case). <strong>Billing:</strong> Invoicing or billing may be in Accounting or in a related module; payments and balances are tracked here.</p>
            <h2>How to Record a Transaction</h2>
            <ol><li>Open <strong>Accounting</strong> from the menu.</li><li>Find the option to add a transaction (e.g. "New Transaction" or "Add Entry").</li><li>Enter date, amount, debit and credit accounts (or choose a transaction type that fills them), description, and any link to agent/case/worker if required.</li><li>Save. The balances of the accounts will update.</li></ol>
            <h2>Viewing Balances and Reports</h2>
            <p>Use the account list or trial balance to see balances. Use <strong>Reports</strong> for profit/loss, by period or by category. Export data for backup or external reporting if the system allows.</p>
            <h2>Expert Tips</h2>
            <ul><li>Reconcile bank and cash accounts regularly to avoid errors.</li><li>Use consistent descriptions and categories so reports are meaningful.</li><li>Export or back up accounting data on a schedule.</li></ul>'
        ],
        8 => [
            'Reports & Analytics – Full Explanation',
            'How to generate and use reports in the Ratib program.',
            '<h2>What Reports Are For</h2>
            <p>The <strong>Reports</strong> section lets you generate reports and analytics from the data in the program: agents, workers, cases, accounting, HR, etc. You use it to monitor performance, prepare for management, and meet compliance or audit needs.</p>
            <h2>Opening Reports</h2>
            <p>Click <strong>Reports</strong> in the left menu. You may see categories (e.g. Agents, HR, Financial) or a list of report types. Your menu may show <strong>Reports</strong> and possibly <strong>Individual Reports</strong> or <strong>Financial Reports</strong> under Accounting.</p>
            <h2>How to Run a Report</h2>
            <ol><li>Choose a report type or category (e.g. agent summary, worker list, financial report).</li><li>Set filters: date range, agent, status, or other criteria offered.</li><li>Click Run or Generate. The report appears on screen (table or chart).</li><li>Use Export (e.g. Excel or PDF) if you need to share or archive.</li></ol>
            <h2>Expert Tips</h2>
            <ul><li>Use date ranges to compare periods (e.g. this month vs last month).</li><li>Save or bookmark frequently used report settings if the system allows.</li><li>If you need a report you do not see, ask your administrator—it may be a permission or configuration.</li></ul>'
        ],
        9 => [
            'Notifications & Automation – Full Explanation',
            'How notifications and automation work in the program.',
            '<h2>What Notifications Do</h2>
            <p>The program can send <strong>notifications</strong> to users (e.g. new case, payment received, document expiring). You see them in the <strong>Notifications</strong> area (bell icon or menu item) and sometimes as on-screen alerts.</p>
            <h2>How to Use Notifications</h2>
            <ol><li>Click <strong>Notifications</strong> in the left menu (or the bell icon).</li><li>Read the list of notifications; mark as read or open the related record (e.g. a case or worker) to take action.</li><li>If there are notification settings, choose which events you want to be notified about and how (in-app, email, etc.).</li></ol>
            <h2>Automation (If Available)</h2>
            <p>Some setups allow <strong>automation</strong> (e.g. auto-status change when a document is uploaded, or reminders). This is usually configured in System Settings or by an administrator. Users then benefit from fewer manual steps.</p>
            <h2>Expert Tips</h2>
            <ul><li>Do not ignore critical notifications (e.g. overdue or expiring items).</li><li>Review notification and automation rules periodically so they stay relevant.</li></ul>'
        ],
        10 => [
            'Troubleshooting & FAQ – Full Explanation',
            'Common issues and how to resolve them when using the Ratib program.',
            '<h2>Page Not Loading or Blank Screen</h2>
            <p>Try refreshing the page (F5 or Ctrl+R). Clear your browser cache and cookies for the site. Use a supported browser (e.g. Chrome, Firefox, Edge) and keep it updated. If the problem continues, try another device or network.</p>
            <h2>Cannot Log In</h2>
            <p>Check username and password (caps lock, language). Use "Forgot password" if available. If the account is locked or disabled, an administrator must enable it in System Settings or user management.</p>
            <h2>Missing Menu or Button</h2>
            <p>Menus and actions depend on <strong>permissions</strong>. If you do not see a section or button, your role may not have the right permission. Contact your administrator to have your role updated.</p>
            <h2>Data Not Saving or Error Message</h2>
            <p>Check required fields (often marked with *). Ensure dates and numbers are in the correct format. If you see an error message, read it—it often says which field or action failed. Try again; if it persists, note the message and contact support or your administrator.</p>
            <h2>Reports or Numbers Look Wrong</h2>
            <p>Check the date range and filters. Ensure data was entered correctly in the source section (e.g. Accounting, Workers). Export and check in Excel if needed. If still wrong, report to your administrator with the report name and filters used.</p>
            <h2>Expert Tips</h2>
            <ul><li>Use the Help &amp; Learning Center (this section) for step-by-step guides.</li><li>Keep your browser and device updated for best compatibility.</li></ul>'
        ],
        11 => [
            'Best Practices – Full Explanation',
            'Recommended ways to use the program for consistency and efficiency.',
            '<h2>Data Entry and Naming</h2>
            <p>Use <strong>consistent naming</strong> for agents, workers, and accounts (e.g. same spelling and format). Fill required fields and use the same date format everywhere. This keeps search and reports accurate.</p>
            <h2>Regular Tasks</h2>
            <p>Review and update data on a schedule: e.g. worker status weekly, documents when received, accounting reconciliation monthly. Assign clear responsibilities so nothing is missed.</p>
            <h2>Security and Access</h2>
            <p>Do not share passwords. Use roles with the minimum permissions needed. Log out when leaving a shared computer. Admins should review user and permission lists periodically.</p>
            <h2>Using the Program as a Team</h2>
            <p>Document your own procedures (who does what, in which order) and share them with the team. Use the Help &amp; Learning Center for training. Report unclear or missing features so they can be improved or documented.</p>
            <h2>Expert Tips</h2>
            <ul><li>Document team procedures for onboarding and training.</li><li>Share tips with colleagues to keep usage consistent.</li></ul>'
        ],
        12 => [
            'Compliance & Legal – Full Explanation',
            'How to use the program in line with compliance and legal requirements.',
            '<h2>Why This Matters</h2>
            <p>Your business must meet legal and regulatory requirements (e.g. labour, visa, tax, data protection). The Ratib program helps you keep records and workflows in one place so you can demonstrate compliance when needed.</p>
            <h2>How the Program Supports Compliance</h2>
            <p>Use the program to: keep <strong>accurate records</strong> (workers, contracts, transactions), track <strong>status and dates</strong> (visas, documents, payments), and generate <strong>reports</strong> for authorities or auditors. Do not delete or alter records that may be needed for audits; use status or notes instead.</p>
            <h2>What You Should Do</h2>
            <ol><li>Read any compliance or legal guidelines your company has provided.</li><li>Use the program as intended: enter data on time, attach documents, and run reports when required.</li><li>If you see a possible compliance risk (e.g. missing document, expired visa), report it through your company channel and follow up in the system.</li></ol>
            <h2>Expert Tips</h2>
            <ul><li>Keep records that may be needed for audits; avoid deleting important history.</li><li>When in doubt about a legal or compliance question, ask your supervisor or legal contact—do not rely on the program alone for legal advice.</li></ul>'
        ],
    ];
}

}

/**
 * Second (extra) tutorial per category – quick tips / checklists.
 * Returns: category_id => [slug, title, overview, content_html]
 */
if (!function_exists('help_center_seed_extra_tutorials')) {

function help_center_seed_extra_tutorials() {
    return [
        1 => ['getting-started-quick-tips', 'Quick Start Checklist', 'A short checklist to get going in the Ratib program.', '<h2>Quick Checklist</h2><ol><li>Log in with your credentials.</li><li>Open the Dashboard and check key numbers.</li><li>Use the left menu to open Agent, Workers, or other sections as needed.</li><li>Bookmark the Help &amp; Learning Center for guides.</li></ol><h2>Expert Tip</h2><p>Check Notifications regularly for important alerts.</p>'],
        2 => ['dashboard-quick-tips', 'Dashboard at a Glance', 'Quick reference for what the Dashboard shows and how to use it.', '<h2>What to Look At</h2><ul><li>Summary cards: totals and key metrics.</li><li>Charts: trends over time.</li><li>Quick links: jump to Agents, Workers, Reports.</li></ul><h2>Expert Tip</h2><p>Refresh the page to see the latest data.</p>'],
        3 => ['permissions-quick-tips', 'Permissions Quick Reference', 'Who can do what – a short guide to roles and permissions.', '<h2>Key Points</h2><ul><li>Each user has a role (e.g. Admin, Manager).</li><li>Roles define what menus and actions are visible.</li><li>User management is in System Settings (admin only).</li></ul><h2>Expert Tip</h2><p>Give each role only the permissions it needs.</p>'],
        4 => ['contracts-quick-tips', 'Contracts & Recruitment Checklist', 'Short checklist for contracts and recruitment steps.', '<h2>Checklist</h2><ol><li>Open the relevant section (Agent, Workers, or Contracts).</li><li>Create or edit the contract; link parties and dates.</li><li>Update status as the process moves (e.g. approved, visa issued).</li><li>Use filters to find items that need action.</li></ol>'],
        5 => ['client-quick-tips', 'Adding a New Client (Agent/SubAgent)', 'Short steps to add and manage a client.', '<h2>Steps</h2><ol><li>Go to Agent or SubAgent from the menu.</li><li>Click Add and fill name, contact, phone, email.</li><li>Save and link workers or cases to this client when needed.</li></ol><h2>Expert Tip</h2><p>Keep contact details up to date for smooth communication.</p>'],
        6 => ['worker-quick-tips', 'Adding a Worker – Short Guide', 'Quick steps to register a worker and upload documents.', '<h2>Steps</h2><ol><li>Open Workers and click Add Worker.</li><li>Fill personal data and required fields; select agent if needed.</li><li>Upload documents (ID, visa, contract) in the worker record.</li><li>Set status (e.g. active, suspended) when it changes.</li></ol>'],
        7 => ['accounting-quick-tips', 'Recording a Transaction', 'Quick steps to record income or expense in Accounting.', '<h2>Steps</h2><ol><li>Open Accounting from the menu.</li><li>Create a new transaction; enter date, amount, and accounts.</li><li>Add description and link to agent/case if required.</li><li>Save; balances update automatically.</li></ol><h2>Expert Tip</h2><p>Reconcile accounts regularly.</p>'],
        8 => ['reports-quick-tips', 'Running a Report', 'Quick steps to generate and export a report.', '<h2>Steps</h2><ol><li>Open Reports from the menu.</li><li>Choose report type and set date range or filters.</li><li>Run the report and view on screen.</li><li>Export to Excel or PDF if needed.</li></ol>'],
        9 => ['notifications-quick-tips', 'Managing Notifications', 'How to stay on top of system notifications.', '<h2>Steps</h2><ol><li>Click Notifications (or the bell icon) in the menu.</li><li>Read new alerts and open related records to take action.</li><li>Adjust notification settings if the system allows.</li></ol><h2>Expert Tip</h2><p>Do not ignore critical notifications.</p>'],
        10 => ['troubleshooting-quick-tips', 'Quick Fixes for Common Issues', 'Short fixes for login, blank screen, and missing menus.', '<h2>Quick Fixes</h2><ul><li>Blank page: refresh (F5), clear cache, try another browser.</li><li>Cannot log in: check password, use Forgot password.</li><li>Missing menu: your role may not have permission – contact admin.</li></ul>'],
        11 => ['best-practices-quick-tips', 'Daily Best Practices', 'Short list of habits for consistent use.', '<h2>Habits</h2><ul><li>Use consistent names and data entry.</li><li>Review and update data on a schedule.</li><li>Do not share passwords; log out on shared devices.</li></ul>'],
        12 => ['compliance-quick-tips', 'Compliance Checklist', 'Short checklist for staying compliant.', '<h2>Checklist</h2><ul><li>Keep accurate records; do not delete audit-relevant data.</li><li>Track status and dates (visas, documents).</li><li>Report compliance concerns through the correct channel.</li></ul>'],
    ];
}

}
