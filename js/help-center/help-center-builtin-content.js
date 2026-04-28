/**
 * EN: Implements frontend interaction behavior in `js/help-center/help-center-builtin-content.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/help-center/help-center-builtin-content.js`.
 */
/**
 * Help Center – Deep-detail built-in tutorials (entire program explained).
 * Loaded before help-center.js. Assigns window.HELP_CENTER_BUILTIN.
 * 
 * Each tutorial has MINIMUM 50 lines of detailed explanation covering:
 * - Every page structure
 * - Every table column and function
 * - Every form field and validation
 * - Every button and its action
 * - Every section and workflow
 */
window.HELP_CENTER_BUILTIN = {
    1: [
        {
            id: 'builtin-1-0',
            title: 'Getting Started – Complete Program Overview',
            overview: 'Full introduction to the Ratib program: login, navigation, and how every section works together.',
            content: '<h2>What is the Ratib Program?</h2><p>Ratib is a complete business management system for managing agents, sub-agents, workers, cases, accounting, HR, reports, and communications. After you log in, the <strong>left sidebar</strong> is your main navigation. Only menu items you have permission to see will appear.</p>' +
            '<h2>Logging In and Security</h2><p>Go to the login page, enter your <strong>username</strong> and <strong>password</strong>, and sign in. Use <strong>Forgot password</strong> if you need to reset. After login you are taken to the <strong>Dashboard</strong>. For security, do not share your password and log out when leaving a shared computer.</p>' +
            '<h2>Understanding the Left Menu</h2><p><strong>Dashboard</strong> – Your home screen with key numbers and shortcuts.<br><strong>Agent</strong> – Main partners or clients who bring business or workers.<br><strong>SubAgent</strong> – Sub-partners linked to an agent.<br><strong>Workers</strong> – Register and manage workers, documents, and status.<br><strong>🌍 Partner Agencies</strong> – Overseas partner offices you work with; open an agency row and use <strong>View</strong> to see workers sent and deployment details (country, contract, job, salary, status) in one table.<br><strong>Cases</strong> – Track cases or files and their status.<br><strong>Accounting</strong> – Chart of accounts, transactions, income and expenses.<br><strong>HR</strong> – Employees, attendance, salaries, and HR settings.<br><strong>Reports</strong> – Generate and view reports by category.<br><strong>Contact</strong> – Contact management and communications.<br><strong>Notifications</strong> – System alerts and messages.<br><strong>Help &amp; Learning Center</strong> – This guide; use it anytime.<br><strong>System Settings</strong> – Configuration and user management (admin).</p>' +
            '<h2>How the Sections Work Together</h2><p>Data flows across the program: you link <strong>workers</strong> to an <strong>agent</strong> or <strong>sub-agent</strong>; you link <strong>cases</strong> to workers or clients; <strong>accounting</strong> can track money per agent or case; <strong>reports</strong> pull from all sections. Use each module for its purpose and link records so reports and follow-up are accurate.</p>' +
            '<h2>Program Structure and Data Flow (Deep)</h2><p><strong>Core entities</strong> – <strong>Agents</strong> and <strong>SubAgents</strong> are your partners/clients. <strong>Workers</strong> are employees or labour. <strong>🌍 Partner Agencies</strong> are overseas recruitment partners; each deployment row ties a worker to an agency with contract dates and placement status. <strong>Cases</strong> are files or projects. <strong>HR Employees</strong> are your internal staff. <strong>Contacts</strong> are people you communicate with. <strong>Accounting</strong> tracks money (transactions, invoices, bills, customers, vendors).<br><strong>Linking</strong> – Workers link to Agent/SubAgent. Partner agency rows list workers sent to that office. Cases can link to workers or agents. Accounting transactions can link to agents, cases, or workers. Reports pull from all linked data.<br><strong>Workflow</strong> – Maintain Agents and Partner Agencies. Add Workers linked to agents. Record deployments from Partner Agencies → View when workers are sent abroad. Create Cases if needed. Record Accounting transactions. Use Reports to see summaries. Use Notifications to stay aware of alerts. Use HR for internal employees, attendance, payroll.<br><strong>Documents</strong> – Upload documents in Workers (ID, visa, contract), Cases (file attachments), or HR (employee documents). Keep document status and dates updated.<br><strong>Status</strong> – Every entity has a status (active/inactive, pending/completed, etc.). Use status filters to find what needs action. Update status as things change.</p>' +
            '<h2>Expert Tips</h2><ul><li>Bookmark the Dashboard for quick access.</li><li>Check Notifications regularly so you do not miss important alerts.</li><li>Use this Help &amp; Learning Center for step-by-step guides on every section.</li><li>If you do not see a menu item, your role may not have permission—contact your administrator.</li></ul>',
            estimated_time: 15,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-1-1',
            title: 'How to Use Tables, Forms, and Buttons – Trainee Guide',
            overview: 'Step-by-step guide to the program interface: tables (lists), forms (add/edit), and buttons so you can use every section confidently.',
            content:
            '<h2>1. Understanding Data Tables (List Views)</h2>' +
            '<p>In most sections (Workers, Agent, SubAgent, Cases, Accounting, HR, Communications, etc.) you see a <strong>table</strong> – a grid of data. Learning how to read and use it is essential.</p>' +
            '<h3>What the table shows</h3>' +
            '<ul><li><strong>Header row (top)</strong> – Column names (e.g. Name, Status, Date, Agent). Some columns are clickable to <strong>sort</strong> the list (A–Z, date order, etc.).</li>' +
            '<li><strong>Each row</strong> – One record (e.g. one worker, one agent, one case). Click a row or an action in the row to open that record.</li>' +
            '<li><strong>Columns</strong> – Different fields. Scroll horizontally if the table is wide; the first columns often show the main identifier (name, code).</li></ul>' +
            '<h3>Search and filters</h3>' +
            '<ul><li><strong>Search box</strong> – Type a word (name, ID, subject, etc.) to filter the table. The list updates as you type. Clear the search to see all records again.</li>' +
            '<li><strong>Filter dropdowns</strong> – e.g. by Status, Type, Agent, Date range. Choose an option to show only matching records. Use <strong>Clear filters</strong> to reset.</li></ul>' +
            '<h3>Pagination (when there are many records)</h3>' +
            '<p>The table may show a limited number of rows per page. At the bottom you will see:</p>' +
            '<ul><li><strong>First</strong> – Go to the first page.</li><li><strong>Previous</strong> – Go to the previous page.</li>' +
            '<li><strong>Page numbers</strong> (e.g. 1, 2, 3) – Click a number to jump to that page.</li>' +
            '<li><strong>Next</strong> – Go to the next page.</li><li><strong>Last</strong> – Go to the last page.</li></ul>' +
            '<h3>Checkboxes and bulk actions</h3>' +
            '<p>Some tables have a <strong>checkbox</strong> in each row (or one in the header to select all). Select one or more rows, then use buttons such as <strong>Bulk Edit</strong> or <strong>Bulk Delete</strong> to act on all selected records at once.</p>' +
            '<h3>Row actions (per record)</h3>' +
            '<p>At the end of each row you often see small buttons or icons:</p>' +
            '<ul><li><strong>View</strong> (eye icon) – Open the record in read-only mode.</li>' +
            '<li><strong>Edit</strong> (pencil icon) – Open the form to change this record.</li>' +
            '<li><strong>Delete</strong> (trash icon) – Remove this record (usually after a confirmation message).</li></ul>' +
            '<p>If you do not see these, try clicking the row itself – some screens open the record on click.</p>' +

            '<h2>2. Understanding Forms (Add / Edit)</h2>' +
            '<p>Forms are where you <strong>add</strong> a new record or <strong>edit</strong> an existing one. They can open in a <strong>modal</strong> (pop-up window) or on a <strong>full page</strong>.</p>' +
            '<h3>Required fields</h3>' +
            '<p>Fields marked with <strong>*</strong> (asterisk) are <strong>required</strong>. You must fill them before the program will save. If you try to save without them, you will see an error message and the field may be highlighted.</p>' +
            '<h3>Common field types</h3>' +
            '<ul><li><strong>Text box</strong> – Type names, descriptions, notes.</li>' +
            '<li><strong>Number</strong> – Amounts, quantities; use only digits (and decimal point if allowed).</li>' +
            '<li><strong>Date / Date and time</strong> – Use the calendar or type in the format shown (e.g. DD/MM/YYYY or YYYY-MM-DD).</li>' +
            '<li><strong>Dropdown (select)</strong> – Choose one option from a list (e.g. Status, Agent, Type). Click the field and pick from the list.</li>' +
            '<li><strong>File upload</strong> – Click to choose a file from your computer (e.g. ID, contract, document).</li>' +
            '<li><strong>Checkbox</strong> – Tick for Yes, leave empty for No.</li></ul>' +
            '<h3>Form buttons</h3>' +
            '<ul><li><strong>Save / Submit</strong> – Saves your data. The form closes and the table updates. If validation fails, the program shows which field to fix.</li>' +
            '<li><strong>Cancel / Close</strong> – Closes the form without saving. Any changes you made are discarded.</li></ul>' +
            '<h3>Validation messages</h3>' +
            '<p>If you leave a required field empty, use an invalid date, or enter text where a number is expected, the program shows an <strong>error message</strong>. Read it carefully – it usually says which field is wrong. Fix that field and click Save again.</p>' +

            '<h2>3. Common Buttons and What They Do</h2>' +
            '<p>These buttons appear in many sections. Use this as a quick reference.</p>' +
            '<table class="help-table"><thead><tr><th>Button</th><th>What it does</th></tr></thead><tbody>' +
            '<tr><td><strong>Add / New / Add Worker / Add Agent</strong></td><td>Opens a blank form to create a new record.</td></tr>' +
            '<tr><td><strong>Edit</strong></td><td>Opens the form with existing data so you can change it.</td></tr>' +
            '<tr><td><strong>View</strong></td><td>Opens the record in read-only mode (no editing).</td></tr>' +
            '<tr><td><strong>Delete</strong></td><td>Removes the record. You may be asked to confirm.</td></tr>' +
            '<tr><td><strong>Export CSV / Export Excel / Export PDF</strong></td><td>Downloads the current list or report to your computer.</td></tr>' +
            '<tr><td><strong>Filter / Apply filters</strong></td><td>Applies the chosen filter options to the table.</td></tr>' +
            '<tr><td><strong>Clear filters</strong></td><td>Removes filters and shows all records again.</td></tr>' +
            '<tr><td><strong>Search</strong></td><td>Filters the list by the text you typed in the search box.</td></tr>' +
            '<tr><td><strong>Bulk Edit / Bulk Delete</strong></td><td>Works on the rows you selected with checkboxes.</td></tr>' +
            '<tr><td><strong>First / Previous / Next / Last</strong></td><td>Pagination: move between pages of the table.</td></tr>' +
            '</tbody></table>' +

            '<h2>4. Step-by-Step: Viewing or Editing a Record</h2>' +
            '<ol><li>Open the section from the left menu (e.g. Workers, Agent, Communications).</li>' +
            '<li>In the table, use Search or Filters if you need to find a specific record.</li>' +
            '<li>Click the row or the <strong>Edit</strong> (pencil) button for that record.</li>' +
            '<li>The form opens (in a modal or on the page). View the data or change the fields you need.</li>' +
            '<li>Click <strong>Save</strong> to keep your changes, or <strong>Cancel</strong> to close without saving.</li></ol>' +

            '<h2>5. Step-by-Step: Adding a New Record</h2>' +
            '<ol><li>Open the section from the left menu.</li>' +
            '<li>Click the <strong>Add</strong> or <strong>New</strong> button (e.g. Add Worker, Add Agent, Add Communication).</li>' +
            '<li>A blank form opens. Fill in all required fields (marked with *).</li>' +
            '<li>Fill optional fields if needed. Use dropdowns for predefined options.</li>' +
            '<li>Click <strong>Save</strong>. The new record appears in the table. If you see an error, fix the field mentioned and try again.</li></ol>' +

            '<h2>Expert Tips</h2>' +
            '<ul><li>Always complete required fields (*) before saving to avoid repeated errors.</li>' +
            '<li>Use Search and Filters to work with large lists instead of scrolling through many pages.</li>' +
            '<li>Use Export to keep a copy of data or share with others (e.g. Excel or PDF).</li>' +
            '<li>If a button or menu is missing, your role may not have permission – contact your administrator.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-1-2',
            title: 'Detailed Step-by-Step Login Guide – Complete Instructions',
            overview: 'Extremely detailed, step-by-step instructions for logging into the Ratib Program system with every action explained.',
            content:
            '<h2>Task: Logging Into the System</h2>' +
            '<p>This guide provides extremely detailed, step-by-step instructions for every action when logging in.</p>' +
            '<h3>Step 1: Open Your Web Browser</h3>' +
            '<p>Open your web browser (Chrome, Firefox, or Edge recommended). Make sure your browser is updated to the latest version for best compatibility.</p>' +
            '<h3>Step 2: Navigate to the Program URL</h3>' +
            '<p>In the address bar at the top of your browser, type the URL provided by your administrator. Example: <code>https://out.ratib.sa</code> or <code>http://localhost/ratibprogram</code>. Press <strong>Enter</strong> on your keyboard. Wait for the page to start loading.</p>' +
            '<h3>Step 3: Wait for Login Page to Load</h3>' +
            '<p>Wait for the login page to load completely. You will see a login form with: A username field (usually the first field), A password field (usually below the username), A "Login" button, Possibly a "Forgot Password?" link. Do not proceed until the page is fully loaded.</p>' +
            '<h3>Step 4: Click Inside the Username Field</h3>' +
            '<p>Click inside the <strong>username field</strong> (the first empty box). The cursor will appear inside the field. The field border may highlight to show it is active. This indicates you are ready to type.</p>' +
            '<h3>Step 5: Type Your Username</h3>' +
            '<p>Type your username exactly as provided by your administrator. Make sure Caps Lock is OFF (unless your username requires capital letters). Type carefully – usernames are case-sensitive. If you make a mistake, use Backspace to delete and retype.</p>' +
            '<h3>Step 6: Move to Password Field</h3>' +
            '<p>Press the <strong>Tab</strong> key on your keyboard OR click inside the <strong>password field</strong>. This moves your cursor to the password field. The username field will no longer be highlighted, and the password field will become active.</p>' +
            '<h3>Step 7: Type Your Password</h3>' +
            '<p>Type your password. Passwords are hidden (you will see dots or asterisks instead of letters). This is normal for security. Type carefully – passwords are case-sensitive. Make sure Caps Lock is OFF unless your password requires capital letters.</p>' +
            '<h3>Step 8: Click the Login Button</h3>' +
            '<p>Click the <strong>"Login"</strong> button. The button is usually green or blue. It is located below the password field. You can also press <strong>Enter</strong> on your keyboard instead of clicking the button.</p>' +
            '<h3>Step 9: Wait for System to Process</h3>' +
            '<p>Wait for the system to process your login. You may see a loading indicator (spinning circle or progress bar). Do NOT click the button multiple times. This can cause errors or multiple login attempts. Be patient and wait for the response.</p>' +
            '<h3>Step 10: If Login is Successful</h3>' +
            '<p>If login is successful: You will be automatically redirected to the <strong>Dashboard</strong>. You will see a welcome message or your name at the top. The left sidebar menu will appear. You are now logged in and ready to use the system.</p>' +
            '<h3>Step 11: If Login Fails</h3>' +
            '<p>If login fails: You will see an error message (usually in red). Common messages include: "Invalid username or password" – Check your credentials and try again. "Account is disabled" – Contact your administrator. "Please check your credentials" – Verify username and password are correct. Try again with correct credentials. If you forgot your password, click "Forgot Password?" link and follow the instructions.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Always check Caps Lock before typing – it causes many login failures.</li><li>If login fails multiple times, wait a few minutes before trying again.</li><li>Bookmark the login page for quick access.</li><li>Contact your administrator if you continue to have login issues.</li></ul>',
            estimated_time: 10,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-1-5',
            title: 'Step-by-Step Login and First Screen Guide',
            overview: 'Complete guide to logging in, understanding your first screen, and navigating the interface.',
            content:
            '<h2>Step 1: Logging In – Complete Process</h2>' +
            '<p><strong>Opening the Program:</strong> Open your web browser (Chrome, Firefox, or Edge recommended). Navigate to your Ratib Program URL (provided by your administrator). You will see the login page with a username field at the top, a password field below it, a "Login" button, and a "Forgot Password?" link (if available).</p>' +
            '<p><strong>Entering Credentials:</strong> Type your username in the first field. Type your password in the second field. Make sure Caps Lock is off and your keyboard language is correct. Click the "Login" button. After successful login, you will be automatically redirected to the Dashboard.</p>' +
            '<p><strong>Password Recovery:</strong> If you forget your password, click "Forgot Password?" and follow the instructions sent to your email. If your account is locked or disabled, an administrator must enable it in System Settings.</p>' +
            '<h2>Step 2: Understanding Your First Screen (Dashboard)</h2>' +
            '<p>When you first log in, you will see the <strong>Dashboard</strong>. This is your main control center where you can see overview statistics for all modules, quick access cards to different sections, recent activities in the system, and notifications and alerts. The Dashboard shows key numbers at a glance so you can monitor the business without opening every section.</p>' +
            '<h2>Understanding the Interface – Complete Breakdown</h2>' +
            '<h3>The Navigation Menu (Left Sidebar)</h3>' +
            '<p>On the left side of your screen, you will see a vertical menu with icons and text. This is your main navigation. Menu items you may see include: Dashboard (takes you to the main overview page), Agent (manage your agents), SubAgent (manage your subagents), Workers (manage worker profiles and documents), 🌍 Partner Agencies (manage overseas partner offices and open <strong>View</strong> for workers sent / deployment rows), Cases (track and manage cases), Accounting (financial management and transactions), HR (human resources management), Reports (view and generate reports), Contact (manage contacts), Communications (view messages and communications), Notifications (see system alerts), System Settings (system configuration, Admin only), Profile (your user profile), Logout (sign out of the system).</p>' +
            '<p><strong>Important:</strong> You will only see menu items that you have permission to access. If you do not see a module, ask your administrator for access.</p>' +
            '<h3>The Top Bar</h3>' +
            '<p>At the top of the screen, you will see your name (on the right), a notification bell icon (if you have notifications), and a profile icon (to access your profile). Click the notification bell to see unread alerts. Click your name or profile icon to access profile settings or logout.</p>' +
            '<h2>Color Coding in the System</h2>' +
            '<p>The system uses colors to help you understand status: Green = Active, Approved, Success; Red = Inactive, Rejected, Error; Yellow/Orange = Pending, Warning; Blue = Information, In Progress; Gray = Inactive, Disabled. Learn these colors so you can quickly identify status at a glance.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Bookmark the Dashboard for quick access.</li><li>Check Notifications regularly so you do not miss important alerts.</li><li>If you do not see a menu item, your role may not have permission—contact your administrator.</li><li>Use the Help &amp; Learning Center (this section) for step-by-step guides on every section.</li></ul>',
            estimated_time: 12,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-1-3',
            title: 'Quick Start Checklist',
            overview: 'A short checklist to get going in the program.',
            content: '<h2>First-Time Checklist</h2><ol><li>Log in with your credentials.</li><li>Open the Dashboard and note the main stats and links.</li><li>Use the left menu to open Agent, Workers, or Accounting and explore the list and add forms.</li><li>Bookmark the Help &amp; Learning Center and open a category to read a full guide.</li><li>Check Notifications for any pending alerts.</li></ol><h2>Expert Tip</h2><p>Spend a few minutes each day in one section (e.g. Workers or Reports) to build familiarity.</p>',
            estimated_time: 5,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-1-4',
            title: 'Complete Navigation Guide – Every Menu Item Explained',
            overview: 'Detailed explanation of every menu item in the left sidebar: what each page does, when to use it, and how it connects to other sections.',
            content:
            '<h2>Left Sidebar Navigation – Complete Breakdown</h2><p>The <strong>left sidebar</strong> is your main navigation in the Ratib program. It appears on every page and shows menu items based on your <strong>permissions</strong>. If you do not see a menu item, your role does not have permission to access it. Contact your administrator to have permissions added.</p>' +
            '<h2>Dashboard Menu Item</h2><p><strong>What it is</strong> – The Dashboard is your home screen showing key statistics and shortcuts.<br><strong>When to use</strong> – Use it daily to monitor totals (agents, workers, cases, HR, accounting) and spot trends. Click stat cards to go to detailed sections.<br><strong>What you see</strong> – Stat cards (Agents total/active/inactive, Workers total/active/inactive, Cases by status, HR employees, Accounting invoices/bills/transactions, Reports activity, Contact counts, Visa counts, Notifications new/total), charts showing trends, recent activity list, last login time.<br><strong>Buttons and actions</strong> – Click any stat card to open that section. Use refresh (F5) to update numbers. Charts may have date range filters.<br><strong>How it connects</strong> – Dashboard pulls data from all sections (Agents, Workers, Cases, HR, Accounting) and shows summaries. Click through to detailed pages.</p>' +
            '<h2>Agent Menu Item</h2><p><strong>What it is</strong> – Page to manage agents (main partners/clients).<br><strong>When to use</strong> – Add a new agent, edit agent details, view agent list, search or filter agents, link workers to agents.<br><strong>What you see</strong> – Table of agents (columns: name, contact, phone, email, address, status, code), search box, filter dropdowns (by status), pagination controls, Add Agent button, View/Edit/Delete buttons per row.<br><strong>Buttons and actions</strong> – <strong>Add Agent</strong> opens form to create new agent. <strong>Search</strong> filters table by name or contact. <strong>Filter</strong> shows only active/inactive agents. <strong>View</strong> opens agent in read-only mode. <strong>Edit</strong> opens form to change agent details. <strong>Delete</strong> removes agent (with confirmation). <strong>Export</strong> downloads agent list to Excel/CSV.<br><strong>How it connects</strong> – Agents link to Workers (each worker belongs to an agent), Cases (cases can link to agents), Accounting (transactions can link to agents), Reports (reports by agent).</p>' +
            '<h2>SubAgent Menu Item</h2><p><strong>What it is</strong> – Page to manage sub-agents (sub-partners linked to an agent).<br><strong>When to use</strong> – Add a sub-agent under an agent, edit sub-agent details, view sub-agent list, filter by parent agent.<br><strong>What you see</strong> – Table of sub-agents (columns: name, parent agent, contact, phone, email, address, status), search box, filters (by agent, by status), Add SubAgent button, View/Edit/Delete per row.<br><strong>Buttons and actions</strong> – <strong>Add SubAgent</strong> opens form; you must select parent Agent first, then fill name, contact, phone, email, address, status. <strong>Search</strong> finds by name. <strong>Filter by Agent</strong> shows sub-agents for one agent. <strong>Filter by Status</strong> shows active/inactive. <strong>Edit</strong> changes sub-agent details or parent agent link. <strong>Delete</strong> removes sub-agent.<br><strong>How it connects</strong> – SubAgents link to parent Agent (required), Workers (workers can link to sub-agent), Cases, Accounting, Reports.</p>' +
            '<h2>Workers Menu Item</h2><p><strong>What it is</strong> – Page to register and manage workers (employees or labour).<br><strong>When to use</strong> – Add a new worker, edit worker details, upload documents (ID, visa, passport, contract), update worker status, filter by status/agent/nationality, search by name or ID.<br><strong>What you see</strong> – Table of workers (columns: name, status, agent, subagent, nationality, ID number, passport, visa number, dates, etc.), search box, filter dropdowns (status, agent, nationality, date range), Add Worker button, View/Edit/Delete per row, Export button, Bulk actions (if checkboxes enabled).<br><strong>Buttons and actions</strong> – <strong>Add Worker</strong> opens form with many fields (name, identity number, passport, nationality, agent, subagent, contact, address, visa, police, medical, ticket numbers and dates, status, job title, emergency contact). <strong>Search</strong> finds by name, ID, passport, email. <strong>Filter by Status</strong> shows pending/active/inactive/suspended/completed. <strong>Filter by Agent</strong> shows workers for one agent. <strong>Edit</strong> opens worker form to change any field or upload documents. <strong>Documents</strong> section in worker record lets you upload files (ID, passport, visa, contract, police, medical) and set document status. <strong>Musaned Status</strong> fields track external system status if integrated. <strong>Export</strong> downloads worker list.<br><strong>How it connects</strong> – Workers link to Agent/SubAgent (required), Cases (cases can link to workers), Accounting (transactions can link to workers), Reports (worker reports by status, agent, nationality).</p>' +
            '<h2>Cases Menu Item</h2><p><strong>What it is</strong> – Page to manage cases or files (contract files, recruitment cases, project files).<br><strong>When to use</strong> – Create a case file, update case status, link case to worker or agent, add case notes or documents, track case progress.<br><strong>What you see</strong> – Table of cases (columns: case number, title, status, linked worker, linked agent, dates, priority), search box, filters (by status, by agent, by date), Add Case button, View/Edit/Delete per row.<br><strong>Buttons and actions</strong> – <strong>Add Case</strong> opens form (title, description, status: open/in progress/pending/resolved/closed, priority: normal/urgent, link to worker/agent if applicable, dates). <strong>Filter by Status</strong> shows open cases, in progress, pending, resolved, closed. <strong>Filter by Agent</strong> shows cases for one agent. <strong>Edit</strong> updates case details, status, or links. <strong>Documents</strong> section lets you attach files to the case. <strong>Notes</strong> or <strong>Activity</strong> section shows case history.<br><strong>How it connects</strong> – Cases link to Workers (optional), Agents (optional), Accounting (transactions can link to cases), Reports (case reports by status, date, agent).</p>' +
            '<h2>Accounting Menu Item</h2><p><strong>What it is</strong> – Financial management: chart of accounts, transactions, invoices, bills, vouchers, customers, vendors.<br><strong>When to use</strong> – Record transactions, create invoices or bills, manage accounts, view balances, run financial reports, reconcile accounts.<br><strong>What you see</strong> – Depending on sub-menu: <strong>Overview</strong> (summary of accounts, balances), <strong>Transactions</strong> (table of all entries with date, amount, accounts, description, links to agent/case/worker), <strong>Invoices</strong> (customer invoices table), <strong>Bills</strong> (vendor bills table), <strong>Vouchers</strong> (payment and receipt vouchers), <strong>Customers</strong> (parties you invoice), <strong>Vendors</strong> (parties you pay), <strong>Cost Centers</strong> (for cost allocation), <strong>Bank Guarantees</strong> (guarantee tracking), <strong>Financial Reports</strong> (profit/loss, balance sheet, trial balance).<br><strong>Buttons and actions</strong> – <strong>New Transaction</strong> opens form (date, amount, debit account, credit account, description, optional link to agent/case/worker). <strong>New Invoice</strong> creates customer invoice. <strong>New Bill</strong> creates vendor bill. <strong>Add Customer/Vendor</strong> creates party record. <strong>Search</strong> finds transactions by description, account, or linked entity. <strong>Filter</strong> by date range, account, or linked entity. <strong>Export</strong> downloads to Excel/CSV/PDF. <strong>Reconcile</strong> matches bank/cash accounts.<br><strong>How it connects</strong> – Accounting links to Agents (transactions can link to agents), Workers (transactions can link to workers), Cases (transactions can link to cases), Reports (financial reports pull from accounting data).</p>' +
            '<h2>HR Menu Item</h2><p><strong>What it is</strong> – Human Resources: employees, attendance, salaries, advances, documents, cars.<br><strong>When to use</strong> – Manage internal employees, mark attendance, process payroll, track advances, manage employee documents, assign company cars.<br><strong>What you see</strong> – HR dashboard with stat cards (total employees, active, inactive, terminated, today attendance, pending salaries), module cards: <strong>Employees</strong> (employee list table with name, email, department, status, employee ID), <strong>Attendance</strong> (attendance records with date, employee, check-in, check-out, hours), <strong>Salaries</strong> (salary records with employee, amount, period, status), <strong>Advances</strong> (advance payments with employee, amount, date, status), <strong>Documents</strong> (employee document uploads), <strong>Cars</strong> (company car assignments).<br><strong>Buttons and actions</strong> – <strong>Add Employee</strong> opens form (name, email, department, position, employee ID, status, hire date). <strong>Mark Attendance</strong> records check-in/check-out for employees. <strong>Process Salaries</strong> generates payroll for period. <strong>Add Advance</strong> records advance payment. <strong>Upload Document</strong> attaches file to employee. <strong>Assign Car</strong> links car to employee. <strong>View</strong> opens employee record. <strong>Edit</strong> updates employee details. <strong>Filter</strong> by department, status, date range.<br><strong>How it connects</strong> – HR is separate from Workers (Workers are external labour; HR Employees are internal staff). HR data appears in Reports (attendance reports, payroll reports).</p>' +
            '<h2>Reports Menu Item</h2><p><strong>What it is</strong> – Generate reports and analytics from all sections.<br><strong>When to use</strong> – Create agent reports, worker reports, case reports, HR reports, financial reports, activity logs, export data for management or compliance.<br><strong>What you see</strong> – Report categories or list: <strong>Agent Reports</strong> (agent summary, counts, activity by agent), <strong>Worker Reports</strong> (worker list by status, agent, nationality, dates, document status), <strong>Case Reports</strong> (cases by status, date, agent, worker), <strong>HR Reports</strong> (employees, attendance, payroll, advances), <strong>Financial Reports</strong> (profit/loss, balance sheet, trial balance, by date range), <strong>Individual Reports</strong> (per-entity reports), <strong>Activity Logs</strong> (system history: who changed what and when).<br><strong>Buttons and actions</strong> – <strong>Select Report Type</strong> chooses report category. <strong>Set Filters</strong> (date range, agent, status, department, etc.) narrows results. <strong>Run Report</strong> generates and displays report (table or chart). <strong>Export</strong> downloads to Excel/CSV/PDF. <strong>Print</strong> prints report. <strong>Save Settings</strong> (if available) saves filter preferences for reuse.<br><strong>How it connects</strong> – Reports pull data from Agents, Workers, Cases, HR, Accounting, and show summaries, trends, or detailed lists based on filters.</p>' +
            '<h2>Contact Menu Item</h2><p><strong>What it is</strong> – Contact management: people you communicate with (not agents or workers, but other contacts).<br><strong>When to use</strong> – Add a contact, edit contact details, view contact list, link contacts to communications.<br><strong>What you see</strong> – Table of contacts (columns: name, email, phone, company, type, status), search box, filters (by type, by status), Add Contact button, View/Edit/Delete per row.<br><strong>Buttons and actions</strong> – <strong>Add Contact</strong> opens form (name, email, phone, company, address, type, status, notes). <strong>Search</strong> finds by name, email, phone. <strong>Filter</strong> by type or status. <strong>Edit</strong> updates contact details. <strong>Delete</strong> removes contact.<br><strong>How it connects</strong> – Contacts link to Communications (each communication links to a contact), Reports (contact activity reports).</p>' +
            '<h2>Notifications Menu Item</h2><p><strong>What it is</strong> – System alerts and messages (new case, payment received, document expiring, etc.).<br><strong>When to use</strong> – Read notifications, mark as read, open related records to take action, adjust notification settings.<br><strong>What you see</strong> – List of notifications (columns: title, message, date/time, read/unread status, related record link), Mark as read button, Mark all as read button, notification count badge in header.<br><strong>Buttons and actions</strong> – <strong>Click notification</strong> opens related record (case, worker, payment, etc.) or shows notification detail. <strong>Mark as read</strong> clears unread status. <strong>Mark all as read</strong> clears all unread. <strong>Settings</strong> (if available) lets you choose which events generate notifications and delivery method (in-app, email).<br><strong>How it connects</strong> – Notifications come from all sections (new worker added, case status changed, payment received, document expiring, etc.). Click notification to go to that record.</p>' +
            '<h2>Help & Learning Center Menu Item</h2><p><strong>What it is</strong> – This guide system: tutorials, step-by-step guides, FAQs.<br><strong>When to use</strong> – Learn how to use any section, find answers to questions, get training materials.<br><strong>What you see</strong> – Categories sidebar (Getting Started, Dashboard, User Management, Contracts, Client Management, Worker Management, Finance, Reports, Notifications, Troubleshooting, Best Practices, Compliance), tutorial list or grid view, search bar, tutorial detail view with content and rating.<br><strong>Buttons and actions</strong> – <strong>Click category</strong> shows tutorials in that category. <strong>Click tutorial</strong> opens detailed guide. <strong>Search</strong> finds tutorials by keyword. <strong>Rate tutorial</strong> (stars) provides feedback. <strong>Grid/List toggle</strong> changes view style.<br><strong>How it connects</strong> – Help Center explains how to use all other sections (Dashboard, Agents, Workers, Accounting, HR, Reports, etc.).</p>' +
            '<h2>System Settings Menu Item</h2><p><strong>What it is</strong> – Configuration and user management (admin only).<br><strong>When to use</strong> – Add users, manage roles and permissions, configure system settings, view system logs.<br><strong>What you see</strong> – Users list (table with username, name, email, role, status), Roles list (table with role name, permissions count), Permissions matrix (per role: checkboxes for each permission), Add User button, Add Role button, Edit buttons, Delete buttons, Settings tabs (general, email, backup, etc.).<br><strong>Buttons and actions</strong> – <strong>Add User</strong> opens form (username, password, name, email, role selection). <strong>Add Role</strong> creates new role. <strong>Edit Role</strong> opens permissions matrix; enable/disable permissions (view_dashboard, view_agents, add_agents, edit_agents, delete_agents, view_workers, add_workers, edit_workers, delete_workers, view_cases, view_accounting, manage_accounting, view_hr_dashboard, manage_hr, view_reports, view_contact, manage_communications, manage_settings, etc.). <strong>Save</strong> applies changes. <strong>Delete User/Role</strong> removes (with confirmation).<br><strong>How it connects</strong> – System Settings controls access to all other sections. Permissions determine what menus and buttons users see.</p>' +
            '<h2>Expert Tips</h2><ul><li>Bookmark frequently used pages (Dashboard, Workers, Accounting) for quick access.</li><li>Use the left menu to navigate; it is always visible.</li><li>If a menu item is missing, your role does not have permission—contact administrator.</li><li>Use Help & Learning Center to learn any section in detail.</li></ul>',
            estimated_time: 45,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    2: [
        {
            id: 'builtin-2-0',
            title: 'Dashboard – Your Home Base – Complete Guide',
            overview: 'Complete guide to the Dashboard: understanding dashboard cards, statistics, recent activities, and how to use it as your main control center.',
            content:
            '<h2>What is the Dashboard?</h2>' +
            '<p>The Dashboard is your <strong>main overview page</strong>. It shows you a quick summary of everything in the system. When you first log in, you will be automatically redirected to the Dashboard. This is your home base and control center.</p>' +
            '<h2>Understanding Dashboard Cards – Complete Breakdown</h2>' +
            '<p>The Dashboard displays <strong>cards</strong> for each module. Each card shows:</p>' +
            '<ol><li><strong>Module Name</strong> (e.g., "Agents", "Workers", "Cases", "Accounting", "HR", "Reports", "Contact", "Notifications") – The name of the module.</li>' +
            '<li><strong>Statistics:</strong> Total count (total number of records in that module), Active count (number of active records), Inactive count (number of inactive records), Status indicator (visual indicator showing overall status).</li>' +
            '<li><strong>Icon</strong> – Visual icon representing the module (🔧 for Workers, 👥 for Agents, 💰 for Accounting, etc.).</li></ol>' +
            '<h2>How to Use Dashboard Cards</h2>' +
            '<ol><li><strong>Click any card</strong> to go directly to that module. Clicking a card is the fastest way to navigate to a section.</li>' +
            '<li><strong>View statistics</strong> at a glance without opening the module. You can see totals, active counts, and inactive counts without leaving the Dashboard.</li>' +
            '<li><strong>See status indicators</strong> – Green = active/good, Red = inactive/needs attention. Color coding helps you quickly identify which modules need attention.</li></ol>' +
            '<h2>Example: Workers Card</h2>' +
            '<p>The Workers card shows:</p>' +
            '<pre style="background:#f5f5f5; padding:15px; border:1px solid #ddd; border-radius:5px;">┌─────────────────────┐\n│   🔧 Workers        │\n│                     │\n│   Total: 150        │\n│   Active: 120       │\n│   Inactive: 30     │\n│   Status: 🟢 Active │\n└─────────────────────┘</pre>' +
            '<p><strong>Click this card</strong> → Takes you to the Workers page where you can manage all workers.</p>' +
            '<h2>Understanding Each Dashboard Card</h2>' +
            '<h3>Agents Card</h3>' +
            '<p>Shows: Total Agents (all agents in system), Active Agents (currently active), Inactive Agents (disabled or inactive), Status indicator. Click to go to Agents page.</p>' +
            '<h3>SubAgents Card</h3>' +
            '<p>Shows: Total SubAgents, Active SubAgents, Inactive SubAgents, Status. Click to go to SubAgents page.</p>' +
            '<h3>Workers Card</h3>' +
            '<p>Shows: Total Workers, Active Workers, Inactive Workers, Status. Click to go to Workers page.</p>' +
            '<h3>Cases Card</h3>' +
            '<p>Shows: Total Cases, Open Cases, Closed Cases, Status. Click to go to Cases page.</p>' +
            '<h3>Accounting Card</h3>' +
            '<p>Shows: Total Invoices, Total Bills, Bank Balances summary, Status. Click to go to Accounting page.</p>' +
            '<h3>HR Card</h3>' +
            '<p>Shows: Total Employees, Active Employees, Today\'s Attendance count, Status. Click to go to HR page.</p>' +
            '<h3>Reports Card</h3>' +
            '<p>Shows: Total Reports available, Recent reports count, Status. Click to go to Reports page.</p>' +
            '<h3>Contact Card</h3>' +
            '<p>Shows: Total Contacts, Active Contacts, Inactive Contacts, Status. Click to go to Contacts page.</p>' +
            '<h3>Notifications Card</h3>' +
            '<p>Shows: Unread Notifications count, Total Notifications, Status. Click to go to Notifications page.</p>' +
            '<h2>Recent Activities Section</h2>' +
            '<p>Below the cards, you will see <strong>Recent Activities</strong> showing:</p>' +
            '<ul><li><strong>What action was performed</strong> – Description of the action (e.g. "New worker added", "Invoice created", "Status changed").</li>' +
            '<li><strong>Who performed it</strong> – Username of the person who did the action.</li>' +
            '<li><strong>When it happened</strong> – Date and time of the action.</li>' +
            '<li><strong>Related record</strong> – Link to the record that was affected (click to view details).</li></ul>' +
            '<p>This helps you track what is happening in the system. Recent activities show the latest actions across all modules.</p>' +
            '<h2>Charts and Visualizations</h2>' +
            '<p>Some Dashboards may show charts:</p>' +
            '<ul><li><strong>Trend charts</strong> – Show trends over time (e.g. workers added per month, revenue over time).</li>' +
            '<li><strong>Pie charts</strong> – Show distribution (e.g. workers by status, cases by priority).</li>' +
            '<li><strong>Bar charts</strong> – Show comparisons (e.g. agents by worker count, departments by employee count).</li></ul>' +
            '<p>Charts help you visualize data and spot trends quickly.</p>' +
            '<h2>Quick Actions</h2>' +
            '<p>The Dashboard may have quick action buttons:</p>' +
            '<ul><li><strong>Add Worker</strong> – Quick button to add a new worker.</li>' +
            '<li><strong>Add Agent</strong> – Quick button to add a new agent.</li>' +
            '<li><strong>Create Invoice</strong> – Quick button to create an invoice.</li>' +
            '<li><strong>Generate Report</strong> – Quick button to generate a report.</li></ul>' +
            '<p>Quick actions let you perform common tasks without navigating to the module first.</p>' +
            '<h2>Using Dashboard for Daily Monitoring</h2>' +
            '<p><strong>Start your day:</strong> Open Dashboard to see overnight activity and current status.</p>' +
            '<p><strong>Monitor key numbers:</strong> Check stat cards to see if totals changed significantly.</p>' +
            '<p><strong>Review recent activities:</strong> See what happened recently to stay informed.</p>' +
            '<p><strong>Click through to details:</strong> Click any card to drill down into that module.</p>' +
            '<p><strong>Use quick actions:</strong> Use quick action buttons for common tasks.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Bookmark the Dashboard for quick access – it\'s your home base.</li>' +
            '<li>Check Dashboard daily to monitor system activity.</li>' +
            '<li>Click stat cards to navigate quickly to modules.</li>' +
            '<li>Review recent activities to stay aware of what\'s happening.</li>' +
            '<li>Use Dashboard as starting point for your daily work.</li></ul>',
            estimated_time: 25,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-2-1',
            title: 'Dashboard – Detailed Step-by-Step Navigation Guide',
            overview: 'Extremely detailed, step-by-step instructions for navigating the Dashboard: identifying menu items, understanding cards, opening modules, and returning to Dashboard.',
            content:
            '<h2>Task: Navigating the Dashboard</h2>' +
            '<h3>Step 1: After Login</h3>' +
            '<p>After logging in, you will automatically see the <strong>Dashboard</strong>. The Dashboard is your main overview page. It shows statistics for all modules.</p>' +
            '<h3>Step 2: Look at Left Side</h3>' +
            '<p>Look at the <strong>left side</strong> of your screen. You will see a vertical menu (sidebar). This menu contains all available modules. Each menu item has an icon and text.</p>' +
            '<h3>Step 3: Identify Menu Items</h3>' +
            '<p>Identify the menu items: <strong>Dashboard</strong> (🏠 icon) – You are here. <strong>Agent</strong> (👥 icon) – Manage agents. <strong>SubAgent</strong> (👤 icon) – Manage subagents. <strong>Workers</strong> (🔧 icon) – Manage workers. <strong>Cases</strong> (📋 icon) – Manage cases. <strong>Accounting</strong> (💰 icon) – Financial management. <strong>HR</strong> (👔 icon) – Human resources. <strong>Reports</strong> (📊 icon) – View reports. <strong>Contact</strong> (📞 icon) – Manage contacts. <strong>Notifications</strong> (🔔 icon) – See alerts. <strong>Help &amp; Learning Center</strong> (📚 icon) – Get help. <strong>Logout</strong> (🚪 icon) – Sign out.</p>' +
            '<h3>Step 4: Look at Center/Main Area</h3>' +
            '<p>Look at the <strong>center/main area</strong> of the Dashboard. You will see <strong>cards</strong> (boxes) for each module. Each card shows: Module name (e.g., "Agents", "Workers"), Statistics (Total, Active, Inactive counts), Status indicator (colored dot or text).</p>' +
            '<h3>Step 5: Understanding Statistics Cards</h3>' +
            '<p>Understanding the statistics cards: <strong>Total</strong> – Total number of records. <strong>Active</strong> – Number of active records (usually green). <strong>Inactive</strong> – Number of inactive records (usually red or gray).</p>' +
            '<h3>Step 6: To Open a Module</h3>' +
            '<p>To open a module: <strong>Click</strong> on any card (box) with your mouse. OR click the corresponding menu item in the left sidebar. The page will change to show that module\'s content.</p>' +
            '<h3>Step 7: Look at Top Right</h3>' +
            '<p>Look at the <strong>top right</strong> of the screen. You will see your name (if displayed). You may see a notification bell icon (🔔). You may see a profile icon (👤).</p>' +
            '<h3>Step 8: To Return to Dashboard</h3>' +
            '<p>To return to Dashboard: Click <strong>"Dashboard"</strong> in the left menu. OR click the Dashboard card. OR click the logo/company name at the top left.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Use Dashboard cards for quick navigation.</li><li>Check statistics regularly to monitor system activity.</li><li>Click cards instead of menu items for faster access.</li></ul>',
            estimated_time: 10,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-2-2',
            title: 'Dashboard – Complete Deep Guide',
            overview: 'Every element on the Dashboard explained: stat cards, numbers, charts, and how to use them for daily oversight.',
            content:
            '<h2>What the Dashboard Is</h2><p>The Dashboard is your <strong>home screen</strong> after login. It shows key numbers and shortcuts so you can monitor the business at a glance without opening every section. What you see depends on your <strong>permissions</strong>—some cards or links may be hidden if your role does not have access.</p>' +
            '<h2>Stat Cards – What Each Number Means</h2>' +
            '<h3>Agents</h3><p><strong>Total</strong> – Total number of agents (main partners) in the system.<br><strong>Active</strong> – Agents with status Active; these are your current partners.<br><strong>Inactive</strong> – Agents marked Inactive (e.g. no longer working with you). Click the card or a link to open the <strong>Agent</strong> page and see the full table.</p>' +
            '<h3>SubAgents</h3><p><strong>Total / Active / Inactive</strong> – Same idea as Agents but for sub-agents (sub-partners linked to an agent). Use the <strong>SubAgent</strong> page to manage them.</p>' +
            '<h3>Workers</h3><p><strong>Total</strong> – All workers (excluding deleted).<br><strong>Active</strong> – Workers with status Active (currently working or under contract).<br><strong>Inactive</strong> – Workers with status Inactive, Suspended, or similar. Click through to the <strong>Workers</strong> section to search, filter, and manage.</p>' +
            '<h3>Cases</h3><p><strong>Total</strong> – All cases/files.<br><strong>Open / In Progress / Pending</strong> – Cases in those workflow stages.<br><strong>Resolved / Closed</strong> – Completed cases.<br><strong>Urgent</strong> – Cases marked urgent. Use the <strong>Cases</strong> page to work on individual cases.</p>' +
            '<h3>HR (Employees)</h3><p><strong>Total</strong> – All employees in the HR module.<br><strong>Active</strong> – Employees with status Active.<br><strong>Inactive / Terminated</strong> – Left or no longer active. Open <strong>HR</strong> from the menu to see Employees, Attendance, Salaries, Advances, Documents, Cars.</p>' +
            '<h3>Accounting</h3><p><strong>Invoices</strong> – Number of invoices.<br><strong>Bills</strong> – Number of bills.<br><strong>Transactions</strong> – Number of financial transactions.<br><strong>Customers / Vendors</strong> – Active customers and vendors in accounting. Open <strong>Accounting</strong> for details, transactions, and reports.</p>' +
            '<h3>Reports / Activity</h3><p>May show <strong>total</strong> activity or log entries, <strong>today</strong> count, and <strong>this month</strong> count. These reflect system usage and history. Use the <strong>Reports</strong> section for real report types (agent, worker, financial, etc.).</p>' +
            '<h3>Contact</h3><p><strong>Total / Active / Inactive</strong> – Contacts in the Contact module. Open <strong>Contact</strong> or <strong>Communications</strong> to manage.</p>' +
            '<h3>Visa</h3><p>If shown: visa-related counts (e.g. total, active, inactive). Use the <strong>Visa</strong> page for visa applications and status.</p>' +
            '<h3>Notifications</h3><p><strong>New / Total</strong> – Unread and total notifications. Click to open <strong>Notifications</strong> and read or act on alerts.</p>' +
            '<h2>Charts and Graphs</h2><p>If the Dashboard has <strong>charts</strong>, they usually show trends over time (e.g. workers added per month, revenue, activity). Use them to spot patterns. Hover or click for details if the chart supports it. Date range or filters may be available above the chart.</p>' +
            '<h2>Recent Activity / Last Login</h2><p>You may see <strong>Recent Activity</strong> (latest actions in the system) and <strong>Last Login</strong> (when you or the system last logged in). This helps you stay aware of recent changes.</p>' +
            '<h2>How to Use the Dashboard Day to Day</h2><ol><li>Open the program; you land on the Dashboard (or click <strong>Dashboard</strong> in the left menu).</li><li>Scan the stat cards: note totals and active/inactive splits for Agents, Workers, Cases, HR, Accounting.</li><li>Click a card or its link to go to that section when you need to add, edit, or review records.</li><li>Check Notifications count; open Notifications to read and act on alerts.</li><li>Use charts to see trends; use Reports for detailed analysis.</li><li>Refresh the page (F5) to get the latest numbers.</li></ol>' +
            '<h2>Expert Tips</h2><ul><li>Refresh the Dashboard when you need up-to-date figures.</li><li>If a stat card is missing, your role may not have permission to that section—contact your administrator.</li><li>Use the Dashboard for a quick overview; use each section (Workers, Accounting, etc.) for detailed work and data entry.</li></ul>',
            estimated_time: 25,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    3: [
        {
            id: 'builtin-3-0',
            title: 'User Management & Permissions – Deep Guide',
            overview: 'System Settings, users, roles, and every permission explained so you can set up and manage access correctly.',
            content:
            '<h2>Where User Management Lives</h2><p>All user and permission management is under <strong>System Settings</strong> in the left menu. Only users with admin or equivalent permission can open it. If you do not see System Settings, your role does not have access.</p>' +
            '<h2>Main Concepts</h2><p><strong>Users</strong> – People who log in. Each has a <strong>username</strong>, <strong>password</strong>, and optionally name and email. Each user is assigned one <strong>role</strong>.<br><strong>Roles</strong> – Named groups (e.g. Admin, Manager, HR, Operator). A role has a set of <strong>permissions</strong>.<br><strong>Permissions</strong> – Fine-grained rights that control what appears in the menu and what actions are allowed. Examples: <em>view_dashboard</em>, <em>view_agents</em>, <em>edit_workers</em>, <em>manage_hr</em>, <em>view_reports</em>, <em>manage_settings</em>. If a permission is off, the user will not see that menu or button.</p>' +
            '<h2>System Settings Screens</h2><p>Inside System Settings you typically have: <strong>Users</strong> (list of users, add/edit/delete), <strong>Roles</strong> (list of roles), and <strong>Permissions</strong> (per role: checkboxes or toggles for each permission). Some setups combine Users and Roles in one screen with tabs or sections.</p>' +
            '<h2>Adding a New User (Step by Step)</h2><ol><li>Open <strong>System Settings</strong>.</li><li>Go to the Users section and click <strong>Add User</strong> (or similar).</li><li>In the form, enter <strong>username</strong> (required, unique) and <strong>password</strong> (required).</li><li>Enter name and email if the form has them.</li><li>Select the <strong>role</strong> for this user (e.g. Admin, Manager). The role determines all permissions.</li><li>Click <strong>Save</strong>. The user can now log in and will see only the menus and actions allowed by that role.</li></ol>' +
            '<h2>Editing a User</h2><p>Open the user from the list (Edit). You can change password, name, email, or <strong>role</strong>. Changing the role immediately changes what the user can see and do. Save to apply.</p>' +
            '<h2>Managing Roles and Permissions</h2><p>Open the Roles (or Permissions) section. Select a role. You will see a list of permissions (e.g. view_dashboard, view_agents, add_agents, edit_agents, delete_agents, view_workers, edit_workers, view_hr_dashboard, manage_hr, view_reports, manage_settings). Enable (tick) or disable (untick) each permission for that role. Save. All users with that role get the updated permissions.</p>' +
            '<h2>Common Permissions (What They Control)</h2><p><strong>view_dashboard</strong> – Can see the Dashboard.<br><strong>view_agents / add_agents / edit_agents / delete_agents</strong> – Agent page access and actions.<br><strong>view_subagents</strong> (or similar) – SubAgent page.<br><strong>view_workers / add_workers / edit_workers / delete_workers</strong> – Workers section.<br><strong>view_cases</strong> – Cases.<br><strong>view_accounting / manage_accounting</strong> – Accounting.<br><strong>view_hr_dashboard / manage_hr</strong> – HR module.<br><strong>view_reports</strong> – Reports.<br><strong>view_contact / manage_communications</strong> – Contact and Communications.<br><strong>manage_settings</strong> – System Settings (users, roles, permissions).</p>' +
            '<h2>Why a Menu or Button Is Missing</h2><p>If a user does not see a menu item or a button (e.g. Add Worker, Export), their <strong>role</strong> does not have the corresponding permission. The administrator must edit the role in System Settings and enable that permission, then the user will see it after refresh or next login.</p>' +
            '<h2>Expert Tips</h2><ul><li>Give each role only the permissions it needs (least privilege).</li><li>Review roles when job duties change.</li><li>Keep the number of full-admin users small; use separate roles for HR, Accounting, etc.</li><li>Use strong passwords and do not share accounts.</li></ul>',
            estimated_time: 22,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-3-1',
            title: 'Permissions Quick Reference',
            overview: 'Who can do what – roles and permissions at a glance.',
            content: '<h2>Key Points</h2><ul><li>Each user has a role (e.g. Admin, Manager).</li><li>Roles define what menus and actions are visible.</li><li>User and role management is in System Settings (admin only).</li><li>If you cannot see a section, your role may not have the permission—contact your administrator.</li></ul><h2>Expert Tip</h2><p>Give each role only the permissions it needs for the job.</p>',
            estimated_time: 5,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    4: [
        {
            id: 'builtin-4-0',
            title: 'Contracts & Recruitment – Deep Guide',
            overview: 'Where contracts and recruitment live in the program, how to create and track them, and how they link to agents, workers, and cases.',
            content:
            '<h2>Where Contracts and Recruitment Live</h2><p>The program does not have a single "Contracts" menu. Contract and recruitment data is spread across: <strong>Agent</strong> and <strong>SubAgent</strong> (agreements with partners), <strong>Workers</strong> (worker contracts, documents, visa and recruitment status), <strong>Cases</strong> (case files that may represent a contract or recruitment file), and sometimes <strong>Visa</strong> (visa applications and status). You use the same <strong>tables, forms, and buttons</strong> in each section—see the tutorial <strong>How to Use Tables, Forms, and Buttons – Trainee Guide</strong> in Getting Started.</p>' +
            '<h2>Contracts with Agents and SubAgents</h2><p>When you add or edit an <strong>Agent</strong> or <strong>SubAgent</strong>, you store their details (name, contact, address, etc.). That record is the basis for your partnership. You can attach or link documents (e.g. signed agreement) in the agent/subagent record or in a linked Case. Use the Agent/SubAgent <strong>table</strong> to list all partners; use <strong>Search</strong> and <strong>Filters</strong> to find by status or name. Use <strong>Add Agent</strong> / <strong>Add SubAgent</strong> to create a new one; use <strong>Edit</strong> to update details or status (e.g. active/inactive).</p>' +
            '<h2>Worker-Level Contracts and Recruitment</h2><p>Each <strong>Worker</strong> has fields for contract and recruitment: agent/subagent link, status (e.g. pending, active, suspended, completed), visa number, visa date, passport, police, medical, ticket, and document uploads. Adding a worker is like starting a recruitment record; you then update status and documents as the worker moves through steps (e.g. documents received, visa issued, arrived). Use the <strong>Workers</strong> table: <strong>filter by status</strong> to see "pending" or "active" workers; open each worker to edit and upload documents. Use <strong>Documents</strong> in the worker record to attach contract copies, ID, visa, etc.</p>' +
            '<h2>Cases as Contract or File Containers</h2><p><strong>Cases</strong> can represent a contract file or a recruitment case. Create a case, set its status (open, in progress, pending, resolved, closed), and link it to a worker or agent if the system allows. Use the Cases <strong>table</strong> and <strong>filters</strong> to find cases by status or date. Open a case to view or edit details and any linked documents or notes.</p>' +
            '<h2>Visa Applications</h2><p>If your menu has <strong>Visa</strong>, use it for visa applications and status. Create or edit visa records and link them to workers where applicable. Status and dates there help you track recruitment and compliance.</p>' +
            '<h2>Workflow Summary</h2><ol><li>Create or select an <strong>Agent</strong> or <strong>SubAgent</strong> (partnership).</li><li>Add a <strong>Worker</strong> and link them to that agent/subagent; fill contract-related fields (visa, passport, dates) and upload documents.</li><li>Update worker <strong>status</strong> as steps complete (e.g. pending → active).</li><li>Use <strong>Cases</strong> if you need a separate file per contract or recruitment; link case to worker/agent.</li><li>Use <strong>Filters</strong> (by status, agent, date) in each section to find items that need action.</li></ol>' +
            '<h2>Expert Tips</h2><ul><li>Use consistent status values (e.g. pending, active, completed) so filters and reports work.</li><li>Update status and dates as soon as steps complete so the team sees the current state.</li><li>Attach documents in the worker record (or case) so everything is in one place.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    5: [
        {
            id: 'builtin-5-0',
            title: 'Client Management (Agent & SubAgent) – Full Explanation',
            overview: 'How to manage clients (agents and sub-agents) in the Ratib program.',
            content: '<h2>What Agents and SubAgents Are</h2><p>In Ratib, <strong>agents</strong> are your main partners (e.g. recruitment agencies, clients). <strong>SubAgents</strong> are sub-partners linked to an agent. You store their details so you can link <strong>workers</strong>, <strong>cases</strong>, and <strong>accounting</strong> to the right client. The Agent and SubAgent pages use the same interface: <strong>table</strong>, <strong>Search</strong>, <strong>Filters</strong>, <strong>Add</strong> / <strong>Edit</strong> / <strong>View</strong> / <strong>Delete</strong>. See <strong>How to Use Tables, Forms, and Buttons – Trainee Guide</strong> in Getting Started.</p>' +
            '<h2>Agent Page – Table and Buttons</h2><p>Open <strong>Agent</strong> from the left menu. You see a <strong>table</strong>: each row is one agent. Columns: name, contact, phone, email, address, status. Use <strong>search</strong> and <strong>filters</strong> (e.g. status). Buttons: <strong>Add Agent</strong>, <strong>View</strong>, <strong>Edit</strong>, <strong>Delete</strong> per row. Click a row or Edit to open that agent.</p>' +
            '<h2>Agent Form – Fields</h2><p>Form fields: Name, Contact person, Phone, Email, Address, Code, Status (active/inactive), Notes. Fill required (*) and click <strong>Save</strong>.</p>' +
            '<h2>SubAgent Page – Same Plus Parent Agent</h2><p><strong>SubAgent</strong> page: same table and buttons. When adding/editing a sub-agent you select the <strong>parent Agent</strong> and fill name, contact, phone, email, address, status. Save to link sub-agent to that agent.</p>' +
            '<h2>Using Client Data in Workers and Cases</h2><p>When adding a <strong>Worker</strong> you choose <strong>Agent</strong> and optionally <strong>SubAgent</strong>. Cases and accounting can also link to agent/sub-agent. Reports then show workers, cases, or revenue by client. Always select the correct agent.</p>' +
            '<h2>Expert Tips</h2><ul><li>Keep names and contact details consistent for search and reports.</li><li>Use status (active/inactive) to filter former partners.</li><li>Use notes for agreements or follow-up dates.</li></ul>',
            estimated_time: 22,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-5-1',
            title: 'Agents Management – Complete Step-by-Step Guide',
            overview: 'Complete guide to managing agents: accessing, understanding the page, adding, editing, viewing statistics, and managing status.',
            content:
            '<h2>What is Agents Management?</h2>' +
            '<p>Agents are your primary business partners. This module helps you manage agent profiles and relationships. Agents bring business or workers to your company. You store their contact details, business information, and status so you can link workers, cases, and accounting records correctly.</p>' +
            '<h2>Accessing Agents</h2>' +
            '<p>There are two ways to access Agents: Click <strong>"Agent"</strong> in the left menu (👥 icon), or click the <strong>Agents card</strong> on the Dashboard. Both methods take you to the Agents page.</p>' +
            '<h2>Understanding the Agents Page – Every Element</h2>' +
            '<p>When you open Agents, you will see: <strong>Search bar</strong> – Find agents quickly by typing name, contact, or company name. Results filter as you type. <strong>Add Agent button</strong> – Usually green, located top right. Click to create new agent. <strong>Agents table</strong> – List of all agents with columns: Name (agent name), Contact Person (main contact), Phone Number, Email Address, Address, Company Name (if applicable), License Number (if applicable), Status (Active/Inactive, color-coded), Actions (View, Edit, Delete buttons).</p>' +
            '<h2>Adding a New Agent – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click "Add Agent"</strong> button (green, top right).</li>' +
            '<li><strong>Fill in the form</strong> with the following sections:</li>' +
            '<li><strong>Agent Name</strong> (required, marked with *) – Enter the full name of the agent or company.</li>' +
            '<li><strong>Contact Information:</strong> Phone Number (enter phone with country code if needed), Email Address (valid email format), Address (full address including city and country).</li>' +
            '<li><strong>Business Details:</strong> Company Name (if agent represents a company), License Number (business license if applicable), Tax ID (tax identification number if applicable), Code (internal code your company uses, if any).</li>' +
            '<li><strong>Status:</strong> Select Active (agent is currently working with you) or Inactive (agent is temporarily disabled or no longer active).</li>' +
            '<li><strong>Notes:</strong> Optional field for any additional information, agreements, or follow-up dates.</li>' +
            '<li><strong>Click "Save"</strong> button at the bottom.</li>' +
            '<li><strong>Agent is created</strong> – appears in the table immediately.</li></ol>' +
            '<h2>Viewing Agent Statistics</h2>' +
            '<p>Each agent record shows statistics: <strong>Total Workers</strong> – Count of workers assigned to this agent. <strong>Active Workers</strong> – Count of workers with active status. <strong>Status</strong> – Active/Inactive indicator (green for active, red for inactive). These statistics help you understand the relationship and activity level with each agent.</p>' +
            '<h2>Editing an Agent</h2>' +
            '<ol><li><strong>Find the agent</strong> in the table (use search if needed).</li>' +
            '<li><strong>Click the "Edit" button</strong> (pencil icon ✏️) in the Actions column.</li>' +
            '<li><strong>Modify any information</strong> you need to change.</li>' +
            '<li><strong>Click "Update" or "Save"</strong> button.</li>' +
            '<li><strong>Changes are saved</strong> – confirmation message appears.</li></ol>' +
            '<h2>Viewing Agent Details</h2>' +
            '<ol><li><strong>Click the "View" button</strong> (eye icon 👁️) in the Actions column.</li>' +
            '<li><strong>Detailed view opens</strong> showing: All contact and business information, Statistics (total workers, active workers), Related records (workers linked to this agent, cases, accounting transactions), History (if available).</li></ol>' +
            '<h2>Managing Agent Status</h2>' +
            '<ol><li><strong>Find the agent</strong> in the table.</li>' +
            '<li><strong>Click status toggle</strong> in the Status column, or click Edit button to change status in the form.</li>' +
            '<li><strong>Change status:</strong> <strong>Active</strong> – Agent is active and can receive workers. <strong>Inactive</strong> – Agent is temporarily disabled or no longer working with you.</li>' +
            '<li><strong>Save changes</strong> – status updates and color changes automatically.</li></ol>' +
            '<h2>Searching Agents</h2>' +
            '<ol><li><strong>Type in the search box</strong> at the top of the table.</li>' +
            '<li><strong>Search by:</strong> Agent name, Contact person name, Company name, Phone number, Email address.</li>' +
            '<li><strong>Results filter automatically</strong> as you type.</li>' +
            '<li><strong>Clear search</strong> by deleting text.</li></ol>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Keep agent names and contact details consistent so search and reports work accurately.</li><li>Use status (active/inactive) to filter out former partners.</li><li>Use notes field to record important agreements or follow-up dates.</li><li>Link workers to the correct agent so reports show accurate data.</li></ul>',
            estimated_time: 25,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-5-3',
            title: 'Agents Management – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for Agents Management: opening page, understanding layout, adding agents (12 steps), editing, viewing, changing status.',
            content:
            '<h2>Task 1: Opening the Agents Page</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, locate the <strong>Agents card</strong>. OR click <strong>"Agent"</strong> in the left sidebar menu (👥 icon).</p>' +
            '<h3>Step 2: Click to Open</h3>' +
            '<p><strong>Click</strong> to open Agents page. Page loads showing all agents.</p>' +
            '<h2>Task 2: Understanding Agents Page Layout</h2>' +
            '<h3>Step 1: Page Elements</h3>' +
            '<p>You will see: <strong>Top:</strong> Statistics cards (Total Agents, Active, Inactive). <strong>Middle:</strong> Search bar and "Add Agent" button. <strong>Bottom:</strong> Agents table with columns: ID, Agent Name, Contact Information, Status, Actions (View, Edit, Delete).</p>' +
            '<h2>Task 3: Adding a New Agent – 12-Step Process</h2>' +
            '<h3>Step 1: Click Add Button</h3>' +
            '<p><strong>Click</strong> the <strong>"Add Agent"</strong> button (green, top left).</p>' +
            '<h3>Step 2: Form Opens</h3>' +
            '<p>Form opens with fields.</p>' +
            '<h3>Step 3: Agent Name</h3>' +
            '<p>Fill in <strong>"Agent Name"</strong> field (required). Click inside the field. Type the agent\'s name. Example: "ABC Recruitment Agency".</p>' +
            '<h3>Step 4: Contact Person</h3>' +
            '<p>Fill in <strong>"Contact Person"</strong> field (if available). Type the name of the contact person. Example: "Mohammed Ali".</p>' +
            '<h3>Step 5: Phone Number</h3>' +
            '<p>Fill in <strong>"Phone Number"</strong> field. Type phone number. Include country code if needed. Example: "+966501234567".</p>' +
            '<h3>Step 6: Email</h3>' +
            '<p>Fill in <strong>"Email"</strong> field. Type email address. Example: "agent@example.com".</p>' +
            '<h3>Step 7: Address</h3>' +
            '<p>Fill in <strong>"Address"</strong> field (if available). Type the full address.</p>' +
            '<h3>Step 8: Company Name</h3>' +
            '<p>Fill in <strong>"Company Name"</strong> field (if available). Type the company name.</p>' +
            '<h3>Step 9: License Number</h3>' +
            '<p>Fill in <strong>"License Number"</strong> field (if available). Type the business license number.</p>' +
            '<h3>Step 10: Tax ID</h3>' +
            '<p>Fill in <strong>"Tax ID"</strong> field (if available). Type the tax identification number.</p>' +
            '<h3>Step 11: Status</h3>' +
            '<p>Select <strong>"Status"</strong> dropdown. Choose "Active" or "Inactive". Usually select "Active" for new agents.</p>' +
            '<h3>Step 12: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save"</strong> or <strong>"Add Agent"</strong> button. Form closes. Success message appears. Agent appears in table.</p>' +
            '<h2>Task 4: Editing an Agent</h2>' +
            '<h3>Step 1: Find Agent</h3>' +
            '<p>Find the agent in the table.</p>' +
            '<h3>Step 2: Click Edit</h3>' +
            '<p><strong>Click</strong> <strong>"Edit"</strong> button (✏️ icon) in Actions column.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Form opens with current data pre-filled.</p>' +
            '<h3>Step 4: Make Changes</h3>' +
            '<p>Make your changes to any fields.</p>' +
            '<h3>Step 5: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Update"</strong> or <strong>"Save"</strong> button. Changes saved. Success message appears.</p>' +
            '<h2>Task 5: Viewing Agent Details</h2>' +
            '<h3>Step 1: Find Agent</h3>' +
            '<p>Find the agent in the table.</p>' +
            '<h3>Step 2: Click View</h3>' +
            '<p><strong>Click</strong> <strong>"View"</strong> button (👁️ icon) in Actions column.</p>' +
            '<h3>Step 3: Detailed View Opens</h3>' +
            '<p>Detailed view opens showing: All agent information, Related workers count, Statistics.</p>' +
            '<h3>Step 4: Close</h3>' +
            '<p><strong>Click</strong> X or outside window to close.</p>' +
            '<h2>Task 6: Changing Agent Status</h2>' +
            '<h3>Step 1: Find Agent</h3>' +
            '<p>Find the agent in the table.</p>' +
            '<h3>Step 2: Look at Status</h3>' +
            '<p>Look at <strong>Status</strong> column. Current status shown (Active/Inactive).</p>' +
            '<h3>Step 3: Click Status or Edit</h3>' +
            '<p><strong>Click</strong> on status OR <strong>Click</strong> Edit button.</p>' +
            '<h3>Step 4: Change Status</h3>' +
            '<p>Change status dropdown: Select "Active" to activate. Select "Inactive" to deactivate.</p>' +
            '<h3>Step 5: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save"</strong>. Status updates immediately.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Fill all required fields before saving.</li><li>Keep contact information current.</li><li>Use status to manage active agents.</li></ul>',
            estimated_time: 30,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-5-2',
            title: 'Subagents Management – Complete Step-by-Step Guide',
            overview: 'Complete guide to managing subagents: accessing, adding, understanding the relationship with agents, and daily management.',
            content:
            '<h2>What is Subagents Management?</h2>' +
            '<p>Subagents work under agents. This module manages subagent profiles and their relationships with agents. A subagent is a sub-partner linked to a main agent. Workers can be assigned to either agents or subagents, giving you flexibility in organizing partnerships.</p>' +
            '<h2>Accessing Subagents</h2>' +
            '<p>There are two ways to access Subagents: Click <strong>"SubAgent"</strong> in the left menu (👤 icon), or click the <strong>SubAgents card</strong> on the Dashboard. Both methods take you to the SubAgents page.</p>' +
            '<h2>Understanding the SubAgents Page</h2>' +
            '<p>When you open SubAgents, you will see: <strong>Search bar</strong> – Find subagents quickly. <strong>Filter by Agent</strong> – Dropdown to show subagents for a specific agent. <strong>Status Filter</strong> – Filter by Active/Inactive. <strong>Add Subagent button</strong> – Create new subagent. <strong>SubAgents table</strong> – List showing: Name, Parent Agent (which agent this subagent belongs to), Contact, Phone, Email, Address, Status, Actions (View, Edit, Delete).</p>' +
            '<h2>Adding a New Subagent – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click "Add Subagent"</strong> button (usually green, top right).</li>' +
            '<li><strong>Fill in the form:</strong></li>' +
            '<li><strong>Select Parent Agent</strong> (required, marked with *) – This is the most important field. Choose the agent this subagent works under from the dropdown. You cannot save without selecting a parent agent.</li>' +
            '<li><strong>Subagent Name</strong> (required) – Enter the full name of the subagent or company.</li>' +
            '<li><strong>Contact Information:</strong> Contact Person (name of main contact), Phone Number, Email Address, Address (full address).</li>' +
            '<li><strong>Status:</strong> Select Active (subagent is currently active) or Inactive (subagent is disabled).</li>' +
            '<li><strong>Notes:</strong> Optional field for additional information.</li>' +
            '<li><strong>Click "Save"</strong> button.</li>' +
            '<li><strong>Subagent is created</strong> – appears in the table, linked to the parent agent.</li></ol>' +
            '<h2>Understanding Subagent-Agent Relationship</h2>' +
            '<p><strong>Hierarchy:</strong> Each subagent must be assigned to an agent (parent agent). The relationship is: Agent → SubAgent → Workers. Workers can be assigned to either agents or subagents directly.</p>' +
            '<p><strong>Why This Matters:</strong> This structure lets you organize partnerships in layers. For example, if Agent A has SubAgent B and SubAgent C, you can assign workers to Agent A directly, or to SubAgent B or SubAgent C. Reports can then show data by agent or by subagent.</p>' +
            '<p><strong>Filtering:</strong> Use "Filter by Agent" to see all subagents under one agent. This helps you manage sub-partners organized by main agent.</p>' +
            '<h2>Editing a Subagent</h2>' +
            '<ol><li><strong>Find the subagent</strong> in the table (use search or filter by agent).</li>' +
            '<li><strong>Click the "Edit" button</strong> (pencil icon ✏️).</li>' +
            '<li><strong>You can change:</strong> Parent Agent (if you need to move subagent to a different agent), Name, Contact information, Status, Notes.</li>' +
            '<li><strong>Click "Save"</strong> – changes are applied.</li></ol>' +
            '<h2>Viewing Subagent Details</h2>' +
            '<ol><li><strong>Click the "View" button</strong> (eye icon 👁️).</li>' +
            '<li><strong>Detailed view shows:</strong> All contact information, Parent Agent name (with link to agent), Statistics (workers assigned to this subagent), Related records.</li></ol>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Always select the correct parent agent when adding a subagent.</li><li>Use "Filter by Agent" to manage subagents organized by main agent.</li><li>Keep subagent names consistent for accurate search and reports.</li><li>Use status to filter active vs inactive subagents.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-5-4',
            title: 'Subagents Management – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for Subagents Management: opening page, adding subagents (7 steps), understanding relationship.',
            content:
            '<h2>Task 1: Opening Subagents Page</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, <strong>Click</strong> <strong>"SubAgent"</strong> in left menu (👤 icon). OR click SubAgents card on Dashboard.</p>' +
            '<h3>Step 2: Page Loads</h3>' +
            '<p>Page loads showing all subagents.</p>' +
            '<h2>Task 2: Adding a New Subagent – 7-Step Process</h2>' +
            '<h3>Step 1: Click Add Button</h3>' +
            '<p><strong>Click</strong> <strong>"Add Subagent"</strong> button.</p>' +
            '<h3>Step 2: Form Opens</h3>' +
            '<p>Form opens.</p>' +
            '<h3>Step 3: Subagent Name</h3>' +
            '<p>Fill in <strong>"Subagent Name"</strong> field (required). Type the subagent\'s name. Example: "XYZ Sub Agency".</p>' +
            '<h3>Step 4: IMPORTANT – Select Parent Agent</h3>' +
            '<p><strong>IMPORTANT:</strong> Select <strong>"Parent Agent"</strong> dropdown (required). <strong>Click</strong> the dropdown. <strong>Select</strong> the agent this subagent belongs to. This is mandatory – subagent must have a parent agent.</p>' +
            '<h3>Step 5: Contact Information</h3>' +
            '<p>Fill in contact information: Phone Number, Email, Address.</p>' +
            '<h3>Step 6: Status</h3>' +
            '<p>Select <strong>"Status"</strong> dropdown. Choose "Active" or "Inactive".</p>' +
            '<h3>Step 7: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save"</strong> button. Subagent created. Success message appears.</p>' +
            '<h2>Task 3: Understanding Subagent-Agent Relationship</h2>' +
            '<h3>Important Notes:</h3>' +
            '<ul><li>Every subagent <strong>must</strong> belong to an agent (parent agent).</li>' +
            '<li>When assigning workers, you can assign to: An agent directly, OR A subagent (which belongs to an agent).</li>' +
            '<li>Subagents appear under their parent agent in reports.</li></ul>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Always select parent agent – it\'s required.</li><li>Understand the hierarchy: Agent → SubAgent → Workers.</li><li>Use filters to see subagents by parent agent.</li></ul>',
            estimated_time: 15,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    6: [
        {
            id: 'builtin-6-0',
            title: 'Workers Management – Complete Step-by-Step Guide',
            overview: 'Complete guide to the Workers section: accessing, understanding the page, adding, editing, viewing, uploading documents, changing status, bulk operations, and searching.',
            content:
            '<h2>What is Workers Management?</h2>' +
            '<p>The Workers module lets you manage all worker profiles, documents, and statuses. Workers are employees or labour that you register in the system. You store personal data, documents (e.g. ID, visa, contract), and status (e.g. active, suspended, completed). Workers are often linked to an agent or sub-agent and to cases or contracts.</p>' +
            '<h2>Accessing Workers</h2>' +
            '<p>There are two ways to access Workers: Click <strong>"Workers"</strong> in the left menu (🔧 icon), or click the <strong>Workers card</strong> on the Dashboard. Both methods take you to the same Workers page.</p>' +
            '<h2>Understanding the Workers Page – Every Element Explained</h2>' +
            '<h3>Top Section – Search and Filters</h3>' +
            '<p><strong>Search Box</strong> – Type to search by name, passport number, or nationality. The search filters the table as you type. Results filter automatically. Clear search by deleting text.</p>' +
            '<p><strong>Status Filter</strong> – Dropdown to filter by status (All, Pending, Approved, Deployed, Rejected, Returned, etc.). Select a status to show only workers with that status. Use "All" to show everyone.</p>' +
            '<p><strong>Agent Filter</strong> – Dropdown to filter by agent. Select an agent to show only workers assigned to that agent. Useful when you need to see workers for a specific partner.</p>' +
            '<p><strong>Add Worker Button</strong> – Usually green, located top right. Click to create a new worker profile. Opens a form with many fields.</p>' +
            '<h3>Main Table – Every Column Explained</h3>' +
            '<p>The table shows all workers with these columns: <strong>Name</strong> – Worker\'s full name (clickable to view details). <strong>Passport Number</strong> – Passport ID (used for search and identification). <strong>Nationality</strong> – Country of origin. <strong>Agent</strong> – Assigned agent name (shows which agent this worker belongs to). <strong>Status</strong> – Current status (color-coded: green for active, yellow for pending, red for rejected, etc.). <strong>Actions</strong> – Buttons to view (eye icon 👁️), edit (pencil icon ✏️), or delete (trash icon 🗑️).</p>' +
            '<h2>Adding a New Worker – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click the "Add Worker" button</strong> (usually green, top right).</li>' +
            '<li><strong>Fill in the form</strong> with the following sections:</li>' +
            '<li><strong>Personal Information:</strong> Full Name (required, marked with *), Passport Number (required, must be unique), Date of Birth (use date picker or type in format shown), Gender (select from dropdown: Male/Female), Nationality (select country from dropdown), Contact Number (phone number), Email Address (valid email format), Address (full address).</li>' +
            '<li><strong>Work Assignment:</strong> Select Agent (required, choose from dropdown – this links worker to an agent), Select Subagent (optional, only if worker belongs to a sub-agent), Visa Type (select type if applicable), Job Category (select from options), Salary (enter amount if applicable).</li>' +
            '<li><strong>Status:</strong> Select initial status (usually "Pending" for new workers).</li>' +
            '<li><strong>Click "Save" or "Submit"</strong> button at the bottom of the form.</li>' +
            '<li><strong>Success message</strong> will appear – worker is now added and appears in the table!</li></ol>' +
            '<h2>Editing a Worker – Complete Process</h2>' +
            '<ol><li><strong>Find the worker</strong> in the table (use search box if needed – type name, passport, or nationality).</li>' +
            '<li><strong>Click the "Edit" button</strong> (pencil icon ✏️) in the Actions column for that worker.</li>' +
            '<li><strong>Modify the information</strong> you need to change (any field can be updated).</li>' +
            '<li><strong>Click "Update" or "Save"</strong> button at the bottom.</li>' +
            '<li><strong>Changes are saved</strong> – you will see a confirmation message and the table updates.</li></ol>' +
            '<h2>Viewing Worker Details – What You See</h2>' +
            '<ol><li><strong>Click the "View" button</strong> (eye icon 👁️) in the Actions column.</li>' +
            '<li><strong>A detailed view opens</strong> showing: All personal information (name, passport, nationality, contact, address), Documents (if uploaded – you can see list of documents), Status history (timeline of status changes), Related information (linked agent, cases, accounting records).</li>' +
            '<li><strong>From the view page</strong> you can also click Edit to make changes, or go back to the list.</li></ol>' +
            '<h2>Uploading Worker Documents – Complete Guide</h2>' +
            '<ol><li><strong>Open a worker\'s profile</strong> (click View or Edit button).</li>' +
            '<li><strong>Find the "Documents" section</strong> (usually a tab or section in the worker detail page).</li>' +
            '<li><strong>Click "Upload Document"</strong> button.</li>' +
            '<li><strong>Select document type</strong> from dropdown: Identity Document, Passport, Medical Certificate, Police Clearance, Visa, Ticket, Contract, Other.</li>' +
            '<li><strong>Choose file</strong> from your computer (click Browse or drag and drop). Allowed formats usually include PDF, JPG, PNG. Check file size limit.</li>' +
            '<li><strong>Click "Upload"</strong> button.</li>' +
            '<li><strong>Document appears</strong> in the documents list with name, type, upload date, and download/view options.</li>' +
            '<li><strong>Update document status</strong> if the form has status fields (e.g. pending, received, verified).</li></ol>' +
            '<h2>Changing Worker Status – Step-by-Step</h2>' +
            '<ol><li><strong>Find the worker</strong> in the table (use search or filters).</li>' +
            '<li><strong>Click the status dropdown</strong> in the Status column (or click Edit button to change status in the form).</li>' +
            '<li><strong>Select new status:</strong> <strong>Pending</strong> – Initial status, awaiting approval. <strong>Approved</strong> – Approved and ready to proceed. <strong>Rejected</strong> – Application rejected. <strong>Deployed</strong> – Worker is deployed and working. <strong>Returned</strong> – Worker has returned. <strong>Active</strong> – Currently active. <strong>Inactive</strong> – Temporarily inactive. <strong>Suspended</strong> – Suspended from work.</li>' +
            '<li><strong>Click "Update Status"</strong> or Save if editing.</li>' +
            '<li><strong>Status changes</strong> – color updates automatically (green for active/approved, yellow for pending, red for rejected/inactive).</li></ol>' +
            '<h2>Bulk Operations – Working with Multiple Workers</h2>' +
            '<p>You can perform actions on multiple workers at once:</p>' +
            '<ol><li><strong>Select workers</strong> using checkboxes in the left column (or checkbox in header to select all on current page).</li>' +
            '<li><strong>Choose action</strong> from bulk actions dropdown (usually appears when workers are selected): Bulk Activate (set all selected to active), Bulk Deactivate (set all selected to inactive), Bulk Delete (remove all selected – use with caution), Bulk Status Update (change status for all selected).</li>' +
            '<li><strong>Click "Apply"</strong> or the action button.</li>' +
            '<li><strong>Confirm</strong> the action in the confirmation dialog.</li>' +
            '<li><strong>All selected workers</strong> are updated – you will see a success message.</li></ol>' +
            '<h2>Searching Workers – Complete Guide</h2>' +
            '<ol><li><strong>Type in the search box</strong> at the top of the table.</li>' +
            '<li><strong>Search by:</strong> Name (full name or partial), Passport number (exact or partial), Nationality (country name), Agent name (finds workers by agent).</li>' +
            '<li><strong>Results filter automatically</strong> as you type (no need to press Enter).</li>' +
            '<li><strong>Clear search</strong> by deleting text or clicking the X icon in the search box.</li>' +
            '<li><strong>Combine with filters</strong> – use search AND status/agent filters together for precise results.</li></ol>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Upload and verify documents as soon as you have them to avoid delays.</li><li>Use filters (by status, agent, or date) to find workers who need attention.</li><li>Keep names and IDs consistent so searches and reports are reliable.</li><li>Use bulk operations carefully – double-check selections before applying.</li><li>Export worker lists regularly for backup or external use.</li></ul>',
            estimated_time: 35,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-6-3',
            title: 'Workers Management – Detailed Step-by-Step Manual (31 Steps)',
            overview: 'Extremely detailed, step-by-step instructions for every action in Workers Management: opening page, understanding layout, adding workers (31 steps), searching, filtering, viewing, editing, changing status, uploading documents, deleting, and bulk operations.',
            content:
            '<h2>Task 1: Opening the Workers Page</h2>' +
            '<h3>Step 1: Locate the Workers Card</h3>' +
            '<p>From the Dashboard, locate the <strong>Workers card</strong>. Look for a card with "Workers" text and a 🔧 icon. The card shows statistics: Total, Active, Inactive workers.</p>' +
            '<h3>Step 2: Click to Open</h3>' +
            '<p><strong>Click</strong> on the Workers card with your mouse. OR click "Workers" in the left sidebar menu. Wait for the page to load (you may see a loading spinner).</p>' +
            '<h3>Step 3: Page Loaded</h3>' +
            '<p>Once loaded, you will see: <strong>Top section:</strong> Statistics cards showing worker counts. <strong>Middle section:</strong> Search bar and filters. <strong>Bottom section:</strong> Table showing all workers.</p>' +
            '<h2>Task 2: Understanding the Workers Page Layout</h2>' +
            '<h3>Step 1: Statistics Cards</h3>' +
            '<p>Look at the <strong>top of the Workers page</strong>. You will see <strong>statistics cards</strong> showing: Total Workers (with number), Active Workers (with number, usually green), Inactive Workers (with number, usually red), Pending Workers (with number, usually yellow), Suspended Workers (with number, usually orange).</p>' +
            '<h3>Step 2: Control Buttons</h3>' +
            '<p>Below the statistics, find the <strong>control buttons</strong>: <strong>"Add New Worker"</strong> button (usually green, with + icon). This button is on the left side. Below it, you may see bulk action buttons (if workers are selected).</p>' +
            '<h3>Step 3: Search and Filters</h3>' +
            '<p>On the <strong>right side</strong>, find: <strong>Search box</strong> – A text input field with a search icon (🔍). <strong>Status filter dropdown</strong> – A dropdown menu showing "All Status".</p>' +
            '<h3>Step 4: Workers Table</h3>' +
            '<p>Below the controls, you will see the <strong>workers table</strong>. The table has columns: Checkbox column (for selecting workers), ID column, Name column, Passport Number column, Nationality column, Agent column, Status column (with colored indicators), Actions column (with buttons: View, Edit, Delete).</p>' +
            '<h2>Task 3: Adding a New Worker – Complete 31-Step Process</h2>' +
            '<h3>Step 1: Locate Add Button</h3>' +
            '<p>On the Workers page, locate the <strong>"Add New Worker"</strong> button. The button is usually green. It has a <strong>+</strong> (plus) icon. It says "Add New Worker" or similar text.</p>' +
            '<h3>Step 2: Click Add Button</h3>' +
            '<p><strong>Click</strong> the "Add New Worker" button. A form will appear (usually as a popup or modal window). The form may cover part of the screen. The form title says "Add New Worker".</p>' +
            '<h3>Step 3: Sidebar Navigation</h3>' +
            '<p>You will see a <strong>sidebar navigation</strong> on the left side of the form. Sections available: <strong>Basic Info</strong> (👤 icon) – Currently active (highlighted). <strong>Professional</strong> (💼 icon). <strong>Contact</strong> (📧 icon). <strong>Documents</strong> (📄 icon).</p>' +
            '<h3>Step 4: Start with Basic Information</h3>' +
            '<p>Start with <strong>Basic Information</strong> section (already open).</p>' +
            '<h3>Step 5: Full Name Field</h3>' +
            '<p>Find the <strong>"Full Name"</strong> field. It has a label "Full Name" with an asterisk (*) indicating it\'s required. Click inside this field. Type the worker\'s full name. Example: "Ahmed Mohammed Ali".</p>' +
            '<h3>Step 6: Gender Field</h3>' +
            '<p>Find the <strong>"Gender"</strong> dropdown field. Label says "Gender" with asterisk (*). Click on the dropdown arrow. Select either "Male" or "Female". Click to select.</p>' +
            '<h3>Step 7: Age Field</h3>' +
            '<p>Find the <strong>"Age"</strong> field (if available). Click inside the field. Type the worker\'s age as a number. Example: "25". Only numbers allowed.</p>' +
            '<h3>Step 8: Marital Status</h3>' +
            '<p>Find the <strong>"Marital Status"</strong> dropdown (if available). Click the dropdown. Select: Single, Married, Divorced, or Widowed.</p>' +
            '<h3>Step 9: Date of Birth</h3>' +
            '<p>Find the <strong>"Date of Birth"</strong> field (if available). Click inside the field. A calendar may appear. Select the date OR type date in format: YYYY-MM-DD. Example: "1998-05-15".</p>' +
            '<h3>Step 10: Nationality</h3>' +
            '<p>Find the <strong>"Nationality"</strong> field. Click inside the field. Type the country name OR select from dropdown if available. Example: "Egypt", "Bangladesh", "India".</p>' +
            '<h3>Step 11: Passport Number</h3>' +
            '<p>Find the <strong>"Passport Number"</strong> field. Click inside the field. Type the passport number exactly as it appears. Example: "A12345678". This is usually required.</p>' +
            '<h3>Step 12: Passport Issue Date</h3>' +
            '<p>Find the <strong>"Passport Issue Date"</strong> field (if available). Click inside the field. Select or type the date when passport was issued.</p>' +
            '<h3>Step 13: Passport Expiry Date</h3>' +
            '<p>Find the <strong>"Passport Expiry Date"</strong> field (if available). Click inside the field. Select or type the date when passport expires.</p>' +
            '<h3>Step 14: Switch to Professional Section</h3>' +
            '<p>Click on <strong>"Professional"</strong> section in the sidebar (💼 icon). The form will switch to Professional Details section.</p>' +
            '<h3>Step 15: Select Agent</h3>' +
            '<p>Find the <strong>"Agent"</strong> dropdown. This is usually required. Click the dropdown arrow. Select the agent from the list. If no agents exist, you must create one first (see Agents section).</p>' +
            '<h3>Step 16: Select Subagent</h3>' +
            '<p>Find the <strong>"Subagent"</strong> dropdown (optional). Click the dropdown. Select a subagent if applicable. Can be left empty.</p>' +
            '<h3>Step 17: Visa Type</h3>' +
            '<p>Find the <strong>"Visa Type"</strong> dropdown (if available). Click the dropdown. Select the type of visa. Example: "Work Visa", "Visit Visa".</p>' +
            '<h3>Step 18: Job Category</h3>' +
            '<p>Find the <strong>"Job Category"</strong> field. Click inside the field. Type the job category OR select from dropdown. Example: "Construction", "Domestic Worker", "Driver".</p>' +
            '<h3>Step 19: Salary</h3>' +
            '<p>Find the <strong>"Salary"</strong> field (if available). Click inside the field. Type the salary amount as a number. Example: "1500". Do not include currency symbols.</p>' +
            '<h3>Step 20: Status</h3>' +
            '<p>Find the <strong>"Status"</strong> dropdown. Click the dropdown. Select initial status: <strong>Pending</strong> – New worker, awaiting approval. <strong>Active</strong> – Approved and active. <strong>Inactive</strong> – Not currently active. For new workers, usually select "Pending".</p>' +
            '<h3>Step 21: Switch to Contact Section</h3>' +
            '<p>Click on <strong>"Contact"</strong> section in the sidebar (📧 icon).</p>' +
            '<h3>Step 22: Phone Number</h3>' +
            '<p>Find the <strong>"Phone Number"</strong> field. Click inside the field. Type the phone number. Include country code if needed. Example: "+966501234567".</p>' +
            '<h3>Step 23: Email</h3>' +
            '<p>Find the <strong>"Email"</strong> field (if available). Click inside the field. Type the email address. Example: "worker@example.com". Must be a valid email format.</p>' +
            '<h3>Step 24: Address</h3>' +
            '<p>Find the <strong>"Address"</strong> field (if available). Click inside the field. Type the full address. Can be multiple lines.</p>' +
            '<h3>Step 25: Switch to Documents Section</h3>' +
            '<p>Click on <strong>"Documents"</strong> section in the sidebar (📄 icon).</p>' +
            '<h3>Step 26: Document Types Available</h3>' +
            '<p>You will see document sections for: <strong>Identity Document</strong>, <strong>Passport</strong>, <strong>Medical Certificate</strong>, <strong>Police Clearance</strong>, <strong>Visa</strong>, <strong>Ticket</strong>.</p>' +
            '<h3>Step 27: Upload Documents</h3>' +
            '<p>For each document type you want to upload: Find the document section (e.g., "Identity Document"). Click the <strong>"UPLOAD"</strong> button next to that document type. A file browser window will open. Navigate to the file on your computer. Select the file (PDF, JPG, PNG formats usually accepted). Click <strong>"Open"</strong> in the file browser. Wait for upload to complete. You will see the file name appear.</p>' +
            '<h3>Step 28: Review and Save</h3>' +
            '<p>After filling all required fields: Scroll to the bottom of the form. You will see two buttons: <strong>"Cancel"</strong> button (usually gray or red). <strong>"Save Worker"</strong> button (usually green or blue).</p>' +
            '<h3>Step 29: Click Save</h3>' +
            '<p><strong>Click</strong> the <strong>"Save Worker"</strong> button. Wait for the system to process. You may see a loading indicator.</p>' +
            '<h3>Step 30: Success</h3>' +
            '<p>If successful: A success message will appear (usually green). The form will close automatically. The new worker will appear in the workers table. You may see a message like "Worker added successfully".</p>' +
            '<h3>Step 31: If Errors</h3>' +
            '<p>If there are errors: Error messages will appear (usually red). Common errors: "Full Name is required", "Passport Number already exists", "Please select an Agent". Fix the errors and click "Save Worker" again.</p>' +
            '<h2>Task 4: Searching for a Worker</h2>' +
            '<h3>Step 1: Locate Search Box</h3>' +
            '<p>On the Workers page, locate the <strong>search box</strong>. It\'s usually on the right side, near the top. Has a search icon (🔍) inside or next to it. Placeholder text says "Search by ID, name, email..." or similar.</p>' +
            '<h3>Step 2: Click Inside Search Box</h3>' +
            '<p><strong>Click</strong> inside the search box. The cursor will appear. The box may highlight.</p>' +
            '<h3>Step 3: Type Search Term</h3>' +
            '<p>Type your search term. You can search by: Worker name, Passport number, Worker ID, Email address. Example: Type "Ahmed" to find workers named Ahmed.</p>' +
            '<h3>Step 4: Automatic Filtering</h3>' +
            '<p>As you type, the table will <strong>automatically filter</strong>. Workers matching your search will appear. Other workers will be hidden. No need to press Enter.</p>' +
            '<h3>Step 5: Clear Search</h3>' +
            '<p>To clear the search: <strong>Delete</strong> all text in the search box. OR click the X icon if available. All workers will appear again.</p>' +
            '<h2>Task 5: Filtering Workers by Status</h2>' +
            '<h3>Step 1: Locate Status Filter</h3>' +
            '<p>On the Workers page, locate the <strong>Status filter dropdown</strong>. It\'s usually next to the search box. Currently shows "All Status".</p>' +
            '<h3>Step 2: Click Dropdown</h3>' +
            '<p><strong>Click</strong> on the dropdown arrow. A menu will appear with options: All Status, Active, Inactive, Pending, Suspended, (Other statuses if available).</p>' +
            '<h3>Step 3: Select Status</h3>' +
            '<p><strong>Click</strong> on the status you want to filter by. Example: Click "Active" to see only active workers.</p>' +
            '<h3>Step 4: Table Updates</h3>' +
            '<p>The table will <strong>immediately update</strong>. Only workers with that status will be shown. Other workers will be hidden.</p>' +
            '<h3>Step 5: Show All Again</h3>' +
            '<p>To see all workers again: Click the dropdown again. Select "All Status".</p>' +
            '<h2>Task 6: Viewing Worker Details</h2>' +
            '<h3>Step 1: Find Worker</h3>' +
            '<p>In the workers table, find the worker you want to view. Use search or scroll to find them.</p>' +
            '<h3>Step 2: Find View Button</h3>' +
            '<p>In the <strong>Actions</strong> column (rightmost column), find the <strong>"View"</strong> button. It usually has an eye icon (👁️). OR says "View".</p>' +
            '<h3>Step 3: Click View</h3>' +
            '<p><strong>Click</strong> the "View" button. A detailed view window will open. Shows all worker information: Personal details, Professional information, Contact information, Documents list, Status history.</p>' +
            '<h3>Step 4: Scroll Through Details</h3>' +
            '<p>Scroll through the details to see all information.</p>' +
            '<h3>Step 5: Close View</h3>' +
            '<p>To close the view: Click the <strong>X</strong> button (top right corner). OR click outside the window. OR press <strong>Esc</strong> key on keyboard.</p>' +
            '<h2>Task 7: Editing a Worker</h2>' +
            '<h3>Step 1: Find Worker</h3>' +
            '<p>In the workers table, find the worker you want to edit. Use search or scroll to find them.</p>' +
            '<h3>Step 2: Find Edit Button</h3>' +
            '<p>In the <strong>Actions</strong> column, find the <strong>"Edit"</strong> button. It usually has a pencil icon (✏️). OR says "Edit".</p>' +
            '<h3>Step 3: Click Edit</h3>' +
            '<p><strong>Click</strong> the "Edit" button. The worker form will open (same as Add form). All fields will be <strong>pre-filled</strong> with current worker data. Form title says "Edit Worker" instead of "Add New Worker".</p>' +
            '<h3>Step 4: Make Changes</h3>' +
            '<p>Make your changes: Click on any field you want to change. Edit the information. You can navigate between sections using the sidebar.</p>' +
            '<h3>Step 5: Save Changes</h3>' +
            '<p>After making changes: Scroll to the bottom. Click <strong>"Save Worker"</strong> button (or "Update Worker"). Wait for confirmation.</p>' +
            '<h3>Step 6: Success</h3>' +
            '<p>If successful: Success message appears. Form closes. Updated information appears in the table.</p>' +
            '<h2>Task 8: Changing Worker Status</h2>' +
            '<h3>Step 1: Find Worker</h3>' +
            '<p>Find the worker in the table.</p>' +
            '<h3>Step 2: Look at Status Column</h3>' +
            '<p>Look at the <strong>Status</strong> column. You will see the current status (e.g., "Pending", "Active"). Status may be color-coded.</p>' +
            '<h3>Step 3: Click Status</h3>' +
            '<p><strong>Click</strong> on the status text OR find the status dropdown in Actions column. A dropdown menu may appear. OR an edit form opens.</p>' +
            '<h3>Step 4: Select New Status</h3>' +
            '<p>Select the new status from the dropdown: <strong>Pending</strong> – Awaiting approval. <strong>Active</strong> – Approved and active. <strong>Inactive</strong> – Not active. <strong>Suspended</strong> – Temporarily suspended. <strong>Deployed</strong> – Currently deployed. <strong>Returned</strong> – Has returned.</p>' +
            '<h3>Step 5: Update</h3>' +
            '<p><strong>Click</strong> "Update" or "Save" button. Status changes immediately. Color indicator updates.</p>' +
            '<h2>Task 9: Uploading Documents to an Existing Worker</h2>' +
            '<h3>Step 1: Open Worker for Editing</h3>' +
            '<p>Open the worker for editing (see Task 7).</p>' +
            '<h3>Step 2: Go to Documents Section</h3>' +
            '<p>Click on <strong>"Documents"</strong> section in the sidebar (📄 icon).</p>' +
            '<h3>Step 3: Find Document Type</h3>' +
            '<p>Find the document type you want to upload. Example: "Medical Certificate".</p>' +
            '<h3>Step 4: Click Upload</h3>' +
            '<p>In that document section, find the <strong>"UPLOAD"</strong> button. Click the button.</p>' +
            '<h3>Step 5: File Browser Opens</h3>' +
            '<p>File browser opens: Navigate to the file location on your computer. Select the file (PDF, JPG, PNG). Click <strong>"Open"</strong>.</p>' +
            '<h3>Step 6: Wait for Upload</h3>' +
            '<p>Wait for upload to complete. You\'ll see a progress indicator. File name appears when done.</p>' +
            '<h3>Step 7: Fill Related Fields</h3>' +
            '<p>Fill in related fields if available: Document number, Issue date, Expiry date.</p>' +
            '<h3>Step 8: Save</h3>' +
            '<p>Click <strong>"Save Worker"</strong> at the bottom. Document is saved with the worker record.</p>' +
            '<h2>Task 10: Deleting a Worker</h2>' +
            '<h3>Step 1: Find Worker</h3>' +
            '<p>Find the worker in the table.</p>' +
            '<h3>Step 2: Find Delete Button</h3>' +
            '<p>In the <strong>Actions</strong> column, find the <strong>"Delete"</strong> button. Usually has a trash icon (🗑️). May be red colored.</p>' +
            '<h3>Step 3: Click Delete</h3>' +
            '<p><strong>Click</strong> the "Delete" button. A confirmation dialog will appear. Message: "Are you sure you want to delete this worker?".</p>' +
            '<h3>Step 4: Confirm</h3>' +
            '<p><strong>Click</strong> "Yes" or "Delete" to confirm. OR click "Cancel" to cancel.</p>' +
            '<h3>Step 5: If Confirmed</h3>' +
            '<p>If confirmed: Worker is deleted. Success message appears. Worker disappears from table.</p>' +
            '<p><strong>⚠️ Warning:</strong> Deletion is usually permanent. Make sure you want to delete before confirming.</p>' +
            '<h2>Task 11: Bulk Operations (Selecting Multiple Workers)</h2>' +
            '<h3>Step 1: Find Checkbox Column</h3>' +
            '<p>In the workers table, find the <strong>checkbox column</strong> (leftmost column). Each row has a checkbox.</p>' +
            '<h3>Step 2: Select Workers</h3>' +
            '<p><strong>Click</strong> the checkbox next to each worker you want to select. Checkbox will be checked (✓). Row may highlight.</p>' +
            '<h3>Step 3: Select Multiple</h3>' +
            '<p>Select multiple workers: Click checkboxes for all workers you want to include. You can select as many as needed.</p>' +
            '<h3>Step 4: Bulk Action Buttons</h3>' +
            '<p>After selecting workers: Bulk action buttons become active (no longer grayed out). Buttons available: <strong>Activate</strong> – Set all selected to Active. <strong>Deactivate</strong> – Set all selected to Inactive. <strong>Pending</strong> – Set all selected to Pending. <strong>Suspended</strong> – Set all selected to Suspended. <strong>Delete</strong> – Delete all selected.</p>' +
            '<h3>Step 5: Click Bulk Action</h3>' +
            '<p><strong>Click</strong> the bulk action button you want. Example: Click "Activate" to activate all selected workers.</p>' +
            '<h3>Step 6: Confirmation</h3>' +
            '<p>Confirmation dialog appears. Message: "Are you sure you want to [action] X workers?". X = number of selected workers.</p>' +
            '<h3>Step 7: Confirm Action</h3>' +
            '<p><strong>Click</strong> "Yes" or "Confirm" to proceed. Action is applied to all selected workers. Success message appears.</p>' +
            '<h3>Step 8: Select All</h3>' +
            '<p>To select all workers at once: Look for <strong>"Select All"</strong> checkbox in table header (if available). Click it to select/deselect all.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Follow each step carefully – don\'t skip steps.</li><li>Save frequently when filling long forms.</li><li>Double-check required fields before saving.</li><li>Use search and filters to find workers quickly.</li><li>Be careful with bulk operations – verify selections.</li></ul>',
            estimated_time: 60,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-6-1',
            title: 'Worker Management – Full Explanation',
            overview: 'Complete guide to the Workers section: adding workers, documents, status, and daily management.',
            content: '<h2>What the Workers Section Does</h2><p>The <strong>Workers</strong> section is where you register and manage workers (employees or labour). You store personal data, documents (e.g. ID, visa, contract), and status (e.g. active, suspended, completed). Workers are often linked to an agent or sub-agent and to cases or contracts.</p>' +
            '<h2>Tables, Forms, and Buttons in Workers</h2><p>In the Workers section you use a <strong>table</strong> (list of workers), <strong>forms</strong> (to add or edit a worker), and <strong>buttons</strong> (Add Worker, Edit, Delete, Export, Search, Filters). For a full guide to how tables, forms, and buttons work in the program, read the tutorial <strong>How to Use Tables, Forms, and Buttons – Trainee Guide</strong> in the Getting Started category.</p>' +
            '<h2>Opening the Workers Section</h2><p>Click <strong>Workers</strong> in the left menu. You will see a <strong>table</strong>: each row is one worker; columns show name, status, agent, dates, etc. Use the <strong>search box</strong> to find by name or ID, and <strong>filter dropdowns</strong> (e.g. by status or agent) to narrow the list. Use <strong>pagination</strong> (First, Previous, Next, Last) if there are many pages. Click a row or the <strong>Edit</strong> (pencil) button to open that worker.</p>' +
            '<h2>Adding a New Worker</h2><ol><li>Click the <strong>Add Worker</strong> (or "+ New") button at the top of the page.</li><li>A <strong>form</strong> opens (in a modal or on a new page). Fill in all <strong>required fields</strong> (marked with *): e.g. name, nationality, ID number, contact, agent, visa type, start date.</li><li>Use dropdowns for status, agent, and other predefined options. Use the file upload for documents if the form allows.</li><li>Click <strong>Save</strong>. The new worker appears in the table. Then open that worker and use the documents area to upload ID, visa, contract, or other files.</li></ol>' +
            '<h2>Managing Documents and Status</h2><p>Open a worker record (click the row or Edit). Use the <strong>documents</strong> tab or section to upload and label files. Change the worker <strong>status</strong> (e.g. active, suspended, left) in the form when their situation changes. If the system tracks Musaned or other external statuses, update those as required. Click <strong>Save</strong> after any change.</p>' +
            '<h2>Expert Tips</h2><ul><li>Upload and verify documents as soon as you have them to avoid delays.</li><li>Use filters (by status, agent, or date) to find workers who need attention.</li><li>Keep names and IDs consistent so searches and reports are reliable.</li></ul>',
            estimated_time: 18,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-6-2',
            title: 'Worker Form – Every Field Explained (Deep)',
            overview: 'Complete list of worker form fields: personal data, documents, status, dates, and how to fill them correctly.',
            content:
            '<h2>Why This Matters</h2><p>When you add or edit a worker, the form has many fields. Filling them correctly keeps records accurate and helps with search, reports, and compliance. Required fields are usually marked with <strong>*</strong>. The program may require: <strong>full name</strong>, <strong>identity number</strong>, <strong>passport number</strong>, <strong>nationality</strong>, and <strong>agent</strong>. Other fields depend on your company and the form layout.</p>' +
            '<h2>Personal and Identity</h2><p><strong>Full name (Worker name)</strong> – Full name of the worker. Required in most setups.<br><strong>Identity number</strong> – National ID number. Must be unique; the program checks for duplicates.<br><strong>Identity date</strong> – Date of identity document if applicable.<br><strong>Passport number</strong> – Passport number. Must be unique.<br><strong>Passport date / Passport expiry</strong> – Issue or expiry date of passport.<br><strong>Nationality</strong> – Country of nationality. Required.<br><strong>Gender</strong> – Male/Female or as defined.<br><strong>Date of birth / Birth date</strong> – Worker’s date of birth.<br><strong>Place of birth</strong> – City or country if captured.<br><strong>Marital status</strong> – Single, Married, etc.<br><strong>Language</strong> – Preferred or spoken language.</p>' +
            '<h2>Contact and Address</h2><p><strong>Phone (Contact number)</strong> – Mobile or phone number.<br><strong>Email</strong> – Email address.<br><strong>Address</strong> – Full address.<br><strong>Country / City</strong> – If the form has separate fields.</p>' +
            '<h2>Agent and SubAgent</h2><p><strong>Agent</strong> – Select the agent (main partner) this worker belongs to. Usually required.<br><strong>SubAgent</strong> – Optionally select a sub-agent linked to that agent.</p>' +
            '<h2>Documents and Numbers (Police, Medical, Visa, Ticket)</h2><p><strong>Police number / Police date</strong> – Police clearance reference and date.<br><strong>Medical number / Medical date</strong> – Medical check reference and date.<br><strong>Visa number / Visa date</strong> – Visa reference and date.<br><strong>Ticket number / Ticket date</strong> – Flight or travel ticket reference and date.<br>Each may have a <strong>status</strong> field (e.g. pending, received, verified) and an <strong>issues</strong> field for notes. Use the <strong>Documents</strong> section in the worker record to upload actual files (ID, passport, visa, contract).</p>' +
            '<h2>Document Status Fields</h2><p>You may see <strong>identity_status</strong>, <strong>passport_status</strong>, <strong>police_status</strong>, <strong>medical_status</strong>, <strong>visa_status</strong>, <strong>ticket_status</strong>. Set to received/verified when the document is on file; use pending or issues when something is missing or has a problem.</p>' +
            '<h2>Work and Status</h2><p><strong>Status</strong> – Main worker status: e.g. <em>pending</em> (just added, not yet active), <em>active</em> (currently working or under contract), <em>inactive</em>, <em>suspended</em>, <em>completed</em> or <em>left</em>. Changing status helps you filter and report. Required; do not leave blank.<br><strong>Job title</strong> – Role or job; may allow multiple (e.g. comma-separated or multi-select).<br><strong>Qualification / Skills</strong> – Education or skills if the form has them.<br><strong>Local experience / Abroad experience</strong> – Years or description if applicable.<br><strong>Arrival date / Departure date</strong> – When the worker arrived or left, for tracking.</p>' +
            '<h2>Emergency Contact</h2><p><strong>Emergency name</strong> – Contact person name.<br><strong>Emergency relation</strong> – Relationship to worker.<br><strong>Emergency phone</strong> – Phone number.<br><strong>Emergency address</strong> – Address if needed.</p>' +
            '<h2>Musaned and External Systems</h2><p>If the program integrates with <strong>Musaned</strong> or similar, you may see extra status or issue fields. Update them when you sync or receive updates so the worker record matches the external system. Use the Documents area to attach any official documents.</p>' +
            '<h2>Validation and Errors</h2><p>Duplicate <strong>identity number</strong> or <strong>passport number</strong> will cause an error; use a different number or edit the existing worker. Required fields left empty will block save—fill all fields marked with * and click <strong>Save</strong> again.</p>' +
            '<h2>Expert Tips</h2><ul><li>Fill identity and passport accurately; they are used for search and compliance.</li><li>Set status to pending when first adding; change to active when the worker is officially on board.</li><li>Upload documents in the worker’s Documents section and update document status fields so the team knows what is on file.</li></ul>',
            estimated_time: 28,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    7: [
        {
            id: 'builtin-7-0',
            title: 'Finance & Billing (Accounting) – Full Explanation',
            overview: 'How Accounting works: accounts, transactions, and billing in the entire program.',
            content: '<h2>What the Accounting Section Is For</h2><p>The <strong>Accounting</strong> section (from the left menu) handles the financial side: chart of accounts, income and expenses, transactions, and billing. You can track money per agent, per worker, or per project depending on setup.</p>' +
            '<h2>Main Parts of Accounting</h2><p><strong>Chart of accounts</strong> – List of accounts (e.g. cash, bank, revenue, expenses).<br><strong>Transactions</strong> – Each movement of money (date, amount, accounts, description, and optionally link to agent or case).<br><strong>Billing</strong> – Invoicing or billing; payments and balances are tracked here.</p>' +
            '<h2>Tables and Forms in Accounting</h2><p>Accounting uses <strong>tables</strong> to list accounts and transactions (with Search, Filters, and pagination) and <strong>forms</strong> to add or edit transactions. Buttons like <strong>New Transaction</strong> or <strong>Add Entry</strong> open the form. For a full guide to tables, forms, and buttons, see <strong>How to Use Tables, Forms, and Buttons – Trainee Guide</strong> in Getting Started.</p>' +
            '<h2>How to Record a Transaction</h2><ol><li>Open <strong>Accounting</strong> from the menu.</li><li>Click the button to add a transaction (e.g. <strong>New Transaction</strong> or <strong>Add Entry</strong>).</li><li>In the <strong>form</strong>, enter date, amount, debit and credit accounts (or choose a transaction type that fills them), description, and any link to agent/case/worker if required. Fill required fields (marked with *).</li><li>Click <strong>Save</strong>. Account balances update automatically.</li></ol>' +
            '<h2>Viewing Balances and Reports</h2><p>Use the account list or trial balance to see balances. Use <strong>Reports</strong> for profit/loss and other financial reports by period or category. Export data for backup or external reporting if the system allows.</p>' +
            '<h2>Accounting Sub-Pages (Deep)</h2><p>Depending on your menu and permissions, Accounting may include: <strong>Overview</strong> – summary of accounts and balances; <strong>Transactions</strong> – list of all entries with Search, Filters, and Add; <strong>Vouchers / Receipts / Payments</strong> – payment and receipt vouchers; <strong>Invoices</strong> – customer invoices; <strong>Bills</strong> – vendor bills; <strong>Customers / Vendors</strong> – parties you invoice or pay; <strong>Cost Centers</strong> – for allocating costs; <strong>Bank Guarantees</strong> – guarantee tracking; <strong>Financial Reports</strong> – profit/loss, balance sheet, trial balance by date range. Each area uses tables and forms: search, filter, add, edit, and export as in the rest of the program.</p>' +
            '<h2>Recording a Transaction (Step by Step)</h2><ol><li>Open <strong>Accounting</strong> and go to Transactions (or the equivalent).</li><li>Click <strong>New Transaction</strong> or <strong>Add Entry</strong>.</li><li>Enter <strong>date</strong>, <strong>amount</strong>, <strong>debit account</strong> and <strong>credit account</strong> (or pick a type that fills them), <strong>description</strong>. If your setup uses entity linking, select agent/case/worker if required.</li><li>Fill any required (*) fields. Click <strong>Save</strong>. Balances update automatically.</li></ol>' +
            '<h2>Expert Tips</h2><ul><li>Reconcile bank and cash accounts regularly to avoid errors.</li><li>Use consistent descriptions and categories so reports are meaningful.</li><li>Export or back up accounting data on a schedule.</li></ul>',
            estimated_time: 25,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-7-2',
            title: 'Accounting System – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for every Accounting task: opening module, understanding tabs, chart of accounts, journal entries (9 steps), invoices (11 steps), bills (9 steps), bank accounts, transactions, and financial reports.',
            content:
            '<h2>Task 1: Opening Accounting Module</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, <strong>Click</strong> <strong>"Accounting"</strong> in left menu (💰 icon). OR click Accounting card.</p>' +
            '<h3>Step 2: Page Loads</h3>' +
            '<p>Accounting page loads. You will see <strong>tabs</strong> at the top: Control Panel (Dashboard), Chart of Accounts, Journal Entries, Invoices, Bills, Banking &amp; Cash, Financial Reports, And more...</p>' +
            '<h2>Task 2: Understanding Accounting Tabs</h2>' +
            '<h3>Step 1: Control Panel</h3>' +
            '<p><strong>Click</strong> on <strong>"Control Panel"</strong> tab (first tab). Shows overview dashboard. Displays: Total Invoices, Total Bills, Bank Balances, Accounts Receivable, Accounts Payable, Charts and graphs.</p>' +
            '<h2>Task 3: Chart of Accounts</h2>' +
            '<h3>Step 1: Click Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Chart of Accounts"</strong> tab.</p>' +
            '<h3>Step 2: Tree Structure</h3>' +
            '<p>You will see a tree structure of accounts: <strong>Assets</strong> – What you own. <strong>Liabilities</strong> – What you owe. <strong>Equity</strong> – Your investment. <strong>Income</strong> – Money coming in. <strong>Expenses</strong> – Money going out.</p>' +
            '<h3>Step 3: Add Account</h3>' +
            '<p>To add a new account: <strong>Click</strong> <strong>"Add Account"</strong> button. Fill in: Account Name (required), Account Code (required), Account Type (dropdown), Parent Account (if sub-account). <strong>Click</strong> <strong>"Save"</strong>.</p>' +
            '<h3>Step 4: Edit Account</h3>' +
            '<p>To edit an account: <strong>Click</strong> account name OR <strong>Click</strong> Edit icon. Make changes. <strong>Click</strong> <strong>"Save"</strong>.</p>' +
            '<h2>Task 4: Creating a Journal Entry – 9-Step Process</h2>' +
            '<h3>Step 1: Click Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Journal Entries"</strong> tab.</p>' +
            '<h3>Step 2: Click New</h3>' +
            '<p><strong>Click</strong> <strong>"New Journal Entry"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Form opens with fields.</p>' +
            '<h3>Step 4: Fill Date</h3>' +
            '<p>Fill in <strong>"Date"</strong> field. Click date field. Select date from calendar OR type: YYYY-MM-DD.</p>' +
            '<h3>Step 5: Reference Number</h3>' +
            '<p>Fill in <strong>"Reference Number"</strong> (if available). System may auto-generate. OR type your reference.</p>' +
            '<h3>Step 6: Description</h3>' +
            '<p>Fill in <strong>"Description"</strong> field. Type what this entry is for. Example: "Payment for office supplies".</p>' +
            '<h3>Step 7: Add Line Items</h3>' +
            '<p>Add <strong>Line Items</strong>: <strong>Line 1:</strong> <strong>Click</strong> Account dropdown – Select account. <strong>Click</strong> Debit field – Enter amount (e.g., "1000"). Leave Credit empty. Add description. <strong>Line 2:</strong> <strong>Click</strong> Account dropdown – Select different account. Leave Debit empty. <strong>Click</strong> Credit field – Enter SAME amount (e.g., "1000"). Add description.</p>' +
            '<h3>Step 8: Balance Check</h3>' +
            '<p><strong>IMPORTANT:</strong> Ensure <strong>Total Debits = Total Credits</strong>. System will show totals. If not equal, you\'ll see an error. Adjust amounts until balanced.</p>' +
            '<h3>Step 9: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save"</strong> or <strong>"Post Entry"</strong> button. Entry is saved. Success message appears.</p>' +
            '<h2>Task 5: Creating an Invoice (Receivable) – 11-Step Process</h2>' +
            '<h3>Step 1: Click Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Invoices"</strong> tab.</p>' +
            '<h3>Step 2: Click New</h3>' +
            '<p><strong>Click</strong> <strong>"New Invoice"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Fill in invoice form.</p>' +
            '<h3>Step 4: Select Customer</h3>' +
            '<p>Select <strong>"Customer"</strong> dropdown. <strong>Click</strong> dropdown. Select customer from list. If customer doesn\'t exist, create one first.</p>' +
            '<h3>Step 5: Invoice Date</h3>' +
            '<p>Fill in <strong>"Invoice Date"</strong>. Select today\'s date or invoice date.</p>' +
            '<h3>Step 6: Due Date</h3>' +
            '<p>Fill in <strong>"Due Date"</strong>. Select when payment is due. Usually 30 days after invoice date.</p>' +
            '<h3>Step 7: Invoice Number</h3>' +
            '<p>Fill in <strong>"Invoice Number"</strong>. System may auto-generate. OR type your invoice number.</p>' +
            '<h3>Step 8: Add Items</h3>' +
            '<p>Add <strong>Invoice Items</strong>: <strong>Click</strong> <strong>"Add Item"</strong> button. Fill in: Description (e.g., "Consulting Services"), Quantity (e.g., "10"), Unit Price (e.g., "100"), Total calculates automatically. Add more items if needed.</p>' +
            '<h3>Step 9: Review Total</h3>' +
            '<p>Review <strong>Total Amount</strong>. System calculates total. Check if correct.</p>' +
            '<h3>Step 10: Notes</h3>' +
            '<p>Add <strong>Notes</strong> (optional). Type any additional notes.</p>' +
            '<h3>Step 11: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Invoice"</strong> button. Invoice created. Appears in Invoices list. Customer now owes this amount.</p>' +
            '<h2>Task 6: Creating a Bill (Payable) – 9-Step Process</h2>' +
            '<h3>Step 1: Click Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Bills"</strong> tab.</p>' +
            '<h3>Step 2: Click New</h3>' +
            '<p><strong>Click</strong> <strong>"New Bill"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Fill in bill form (similar to invoice).</p>' +
            '<h3>Step 4: Select Vendor</h3>' +
            '<p>Select <strong>"Vendor"</strong> dropdown. Select vendor from list. Vendor = company you owe money to.</p>' +
            '<h3>Step 5: Bill Date</h3>' +
            '<p>Fill in <strong>"Bill Date"</strong>. Date you received the bill.</p>' +
            '<h3>Step 6: Due Date</h3>' +
            '<p>Fill in <strong>"Due Date"</strong>. When you need to pay.</p>' +
            '<h3>Step 7: Bill Number</h3>' +
            '<p>Fill in <strong>"Bill Number"</strong>. Bill number from vendor.</p>' +
            '<h3>Step 8: Add Items</h3>' +
            '<p>Add <strong>Bill Items</strong>: Description, Quantity, Unit Price, Total.</p>' +
            '<h3>Step 9: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Bill"</strong> button. Bill created. You now owe this amount.</p>' +
            '<h2>Task 7: Managing Bank Accounts</h2>' +
            '<h3>Step 1: Click Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Banking &amp; Cash"</strong> tab.</p>' +
            '<h3>Step 2: See List</h3>' +
            '<p>You will see list of bank accounts.</p>' +
            '<h3>Step 3: Add Bank Account</h3>' +
            '<p>To add a bank account: <strong>Click</strong> <strong>"Add Bank Account"</strong> button. Fill in: Bank Name (e.g., "Al Rajhi Bank"), Account Number, Account Type (Checking, Savings, etc.), Opening Balance. <strong>Click</strong> <strong>"Save"</strong>.</p>' +
            '<h3>Step 4: Record Transaction</h3>' +
            '<p>To record a transaction: <strong>Click</strong> on a bank account. <strong>Click</strong> <strong>"New Transaction"</strong> button. Fill in: Date, Type (Deposit or Withdrawal), Amount, Description, Related Account (if applicable). <strong>Click</strong> <strong>"Save"</strong>.</p>' +
            '<h2>Task 8: Generating Financial Reports</h2>' +
            '<h3>Step 1: Click Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Financial Reports"</strong> tab.</p>' +
            '<h3>Step 2: See List</h3>' +
            '<p>You will see list of available reports: Trial Balance, Balance Sheet, Income Statement, Cash Flow Statement, Profit &amp; Loss, Accounts Receivable Aging, Accounts Payable Aging, And more...</p>' +
            '<h3>Step 3: Select Report Type</h3>' +
            '<p>Select <strong>Report Type</strong> from dropdown. <strong>Click</strong> dropdown. Select report you want. Example: "Balance Sheet".</p>' +
            '<h3>Step 4: Select Date Range</h3>' +
            '<p>Select <strong>Date Range</strong>: <strong>From Date:</strong> Click and select start date. <strong>To Date:</strong> Click and select end date. Example: From "2026-01-01" To "2026-12-31".</p>' +
            '<h3>Step 5: Generate</h3>' +
            '<p><strong>Click</strong> <strong>"Generate Report"</strong> button. Report generates. Displays in table format.</p>' +
            '<h3>Step 6: Export</h3>' +
            '<p>To export report: <strong>Click</strong> <strong>"Export"</strong> button (if available). Choose format: Excel, PDF, or Print. File downloads or prints.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Always ensure debits equal credits in journal entries.</li><li>Review invoice and bill totals before saving.</li><li>Reconcile bank accounts regularly.</li><li>Use consistent account codes for better organization.</li></ul>',
            estimated_time: 50,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-7-1',
            title: 'Accounting System – Complete Step-by-Step Guide',
            overview: 'Complete guide to all Accounting tabs: Control Panel, Chart of Accounts, Journal Entries, Invoices, Bills, Banking, and Financial Reports.',
            content:
            '<h2>What is the Accounting System?</h2>' +
            '<p>The Accounting module is a complete financial management system for tracking money, transactions, invoices, bills, and generating financial reports. It handles all financial aspects of your business: what you own (assets), what you owe (liabilities), income, expenses, and cash flow.</p>' +
            '<h2>Accessing Accounting</h2>' +
            '<p>There are two ways to access Accounting: Click <strong>"Accounting"</strong> in the left menu (💰 icon), or click the <strong>Accounting card</strong> on the Dashboard. Both methods take you to the Accounting page.</p>' +
            '<h2>Understanding Accounting Tabs – Complete Breakdown</h2>' +
            '<p>When you open Accounting, you will see tabs at the top. Each tab represents a different area of financial management. Click a tab to switch between areas.</p>' +
            '<h3>1. Control Panel (Dashboard)</h3>' +
            '<p><strong>What it shows:</strong> Overview of financial status with quick statistics: Total Invoices (count of all invoices), Total Bills (count of all bills), Bank Balances (current balance in each bank account), Accounts Receivable (money customers owe you), Accounts Payable (money you owe vendors), Charts showing financial trends (income over time, expenses, cash flow).</p>' +
            '<p><strong>How to use:</strong> Review the overview to see financial health at a glance. Click any statistic to drill down to details. Use charts to spot trends. This is your financial command center.</p>' +
            '<h3>2. Chart of Accounts</h3>' +
            '<p><strong>What it is:</strong> List of all accounts in your system. Accounts are organized into categories: <strong>Assets</strong> (what you own: cash, bank, equipment, inventory), <strong>Liabilities</strong> (what you owe: loans, bills payable, accounts payable), <strong>Equity</strong> (your investment: capital, retained earnings), <strong>Income</strong> (money coming in: revenue, sales, service income), <strong>Expenses</strong> (money going out: salaries, rent, utilities, supplies).</p>' +
            '<p><strong>How to use:</strong> View accounts in a tree structure (parent accounts with sub-accounts). Click account name to see details (balance, transactions). Add new account using "Add Account" button (enter account name, type, parent account if sub-account, code). Edit account by clicking edit icon (change name, type, code). Delete account (only if no transactions exist).</p>' +
            '<h3>3. Journal Entries</h3>' +
            '<p><strong>What it is:</strong> Record manual transactions with debits and credits. Journal entries are the foundation of accounting – every transaction affects at least two accounts.</p>' +
            '<p><strong>Creating a Journal Entry – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "New Journal Entry"</strong> button.</li>' +
            '<li><strong>Enter date</strong> (transaction date) and reference number (optional, for your records).</li>' +
            '<li><strong>Add line items:</strong> For each line: Select Account (from dropdown), Enter Debit amount OR Credit amount (not both – one must be zero), Add description (what this transaction is for). Click "Add Line" to add more lines. You need at least two lines (one debit, one credit).</li>' +
            '<li><strong>Ensure debits = credits</strong> – The system will warn if totals do not balance. Total debits must equal total credits. Fix any imbalance before saving.</li>' +
            '<li><strong>Click "Save"</strong> – Entry is recorded and account balances update automatically.</li></ol>' +
            '<h3>4. Invoices (Receivables)</h3>' +
            '<p><strong>What it is:</strong> Create invoices for money customers owe you. Track payments received. Invoices represent sales or services you provided.</p>' +
            '<p><strong>Creating an Invoice – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "New Invoice"</strong> button.</li>' +
            '<li><strong>Fill in:</strong> Customer Name (select from dropdown or create new), Invoice Date (date invoice was issued), Due Date (when payment is due), Items/Services (add line items: description, quantity, unit price, total), Amount (total invoice amount, calculated automatically), Tax (if applicable), Notes (optional).</li>' +
            '<li><strong>Click "Save"</strong> – Invoice is created and appears in receivables.</li>' +
            '<li><strong>Invoice status:</strong> Unpaid (customer has not paid), Partially Paid (partial payment received), Paid (fully paid). Update status as payments are received.</li></ol>' +
            '<h3>5. Bills (Payables)</h3>' +
            '<p><strong>What it is:</strong> Record bills you need to pay. Track payments made. Bills represent expenses or purchases from vendors.</p>' +
            '<p><strong>Creating a Bill – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "New Bill"</strong> button.</li>' +
            '<li><strong>Fill in:</strong> Vendor Name (select from dropdown or create new), Bill Date (date bill was received), Due Date (when payment is due), Items/Services (add line items: description, quantity, unit price, total), Amount (total bill amount), Tax (if applicable), Notes (optional).</li>' +
            '<li><strong>Click "Save"</strong> – Bill is created and appears in payables.</li>' +
            '<li><strong>Bill status:</strong> Unpaid (you have not paid), Partially Paid (partial payment made), Paid (fully paid). Update status as you make payments.</li></ol>' +
            '<h3>6. Banking & Cash</h3>' +
            '<p><strong>What it is:</strong> Manage bank accounts, record bank transactions, reconcile bank statements.</p>' +
            '<p><strong>Adding a Bank Account – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "Add Bank Account"</strong> button.</li>' +
            '<li><strong>Enter:</strong> Bank Name (name of bank), Account Number (account number), Account Type (checking, savings, etc.), Opening Balance (starting balance when you add the account), Currency (if multi-currency), Notes (optional).</li>' +
            '<li><strong>Click "Save"</strong> – Bank account is added and appears in account list.</li></ol>' +
            '<p><strong>Recording a Bank Transaction – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Select bank account</strong> from list.</li>' +
            '<li><strong>Click "New Transaction"</strong> button.</li>' +
            '<li><strong>Enter:</strong> Date (transaction date), Type (Deposit – money coming in, Withdrawal – money going out), Amount (transaction amount), Description (what this transaction is for), Reference Number (check number, transaction ID, etc.), Linked Account (if this affects another account).</li>' +
            '<li><strong>Click "Save"</strong> – Transaction is recorded and bank balance updates.</li></ol>' +
            '<p><strong>Reconciling Bank Statements:</strong> Periodically, match your bank statement with transactions in the system. Mark transactions as reconciled when they match. This helps catch errors and ensures accuracy.</p>' +
            '<h3>7. Financial Reports</h3>' +
            '<p><strong>What it is:</strong> Generate various financial reports for analysis, management, and compliance.</p>' +
            '<p><strong>Available Reports:</strong> Trial Balance (list of all accounts with balances), Balance Sheet (assets, liabilities, equity at a point in time), Income Statement (revenue and expenses over a period), Cash Flow Statement (cash inflows and outflows), Profit & Loss (revenue minus expenses), Accounts Receivable Aging (who owes you and for how long), Accounts Payable Aging (who you owe and for how long), And many more depending on your setup.</p>' +
            '<p><strong>Generating a Report – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "Financial Reports"</strong> tab.</li>' +
            '<li><strong>Select report type</strong> from dropdown (e.g. Trial Balance, Balance Sheet).</li>' +
            '<li><strong>Choose date range:</strong> From Date (start date), To Date (end date). Some reports may need a specific date (e.g. Balance Sheet as of a date).</li>' +
            '<li><strong>Set filters</strong> (if available): Account, Department, Project, etc.</li>' +
            '<li><strong>Click "Generate Report"</strong> button.</li>' +
            '<li><strong>Report displays</strong> in table or chart format.</li>' +
            '<li><strong>Export options:</strong> Print (print the report), Export to Excel (download as spreadsheet), Export to PDF (download as PDF file).</li></ol>' +
            '<h2>Understanding Accounting Colors</h2>' +
            '<p>The system uses colors: Green – Positive balances, income, assets. Red – Negative balances, expenses, liabilities. Blue – Information, neutral, notes. Learn these colors to quickly understand financial status.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Reconcile bank and cash accounts regularly (weekly or monthly) to avoid errors.</li><li>Use consistent descriptions and categories so reports are meaningful.</li><li>Export or back up accounting data on a schedule for safety.</li><li>Ensure debits equal credits in journal entries – unbalanced entries cause errors.</li><li>Update invoice and bill status as payments are made.</li></ul>',
            estimated_time: 40,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    8: [
        {
            id: 'builtin-8-0',
            title: 'Reports & Analytics – Full Explanation',
            overview: 'How to generate and use reports across the entire program.',
            content: '<h2>What Reports Are For</h2><p>The <strong>Reports</strong> section lets you generate reports and analytics from agents, workers, cases, accounting, HR, and other data. Use it to monitor performance, prepare for management, and meet compliance or audit needs.</p>' +
            '<h2>Opening Reports</h2><p>Click <strong>Reports</strong> in the left menu. You may see categories (e.g. Agents, HR, Financial) or a list of report types. Your menu may also show <strong>Individual Reports</strong> or <strong>Financial Reports</strong> under Accounting.</p>' +
            '<h2>How to Run a Report</h2><ol><li>Choose a report type or category (e.g. agent summary, worker list, financial report).</li><li>Set filters: date range, agent, status, or other criteria offered.</li><li>Click Run or Generate. The report appears on screen (table or chart).</li><li>Use Export (e.g. Excel or PDF) to share or archive.</li></ol>' +
            '<h2>Reports Section – Deep</h2><p><strong>Reports</strong> (left menu) may show categories or a list of report types. Common types: <strong>Agent reports</strong> – agents and subagents summary, counts, activity; <strong>Worker reports</strong> – worker list by status, agent, nationality, dates; <strong>Case reports</strong> – cases by status, date; <strong>HR reports</strong> – employees, attendance, payroll; <strong>Financial reports</strong> – profit/loss, balance sheet, trial balance, often by date range. <strong>Individual Reports</strong> (sometimes under Accounting or a separate menu) may show per-entity or per-period reports. <strong>Activity logs / History</strong> – system or module history (e.g. who changed what and when). Your menu may label these differently; use filters (date, agent, status) to narrow results. Export to Excel or PDF for sharing or audit.</p>' +
            '<h2>Expert Tips</h2><ul><li>Use date ranges to compare periods (e.g. this month vs last month).</li><li>Save or bookmark frequently used report settings if the system allows.</li><li>If you need a report you do not see, ask your administrator—it may be a permission or configuration.</li></ul>',
            estimated_time: 18,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-8-1',
            title: 'Reports Module – Complete Step-by-Step Guide',
            overview: 'Complete guide to generating reports: types available, filters, export options, and how to use each report type.',
            content:
            '<h2>What is the Reports Module?</h2>' +
            '<p>The Reports module generates various reports from your data. Use it to monitor performance, prepare for management, meet compliance needs, and analyze business trends. Reports pull data from all sections: Agents, Workers, Cases, HR, Accounting, Contacts.</p>' +
            '<h2>Accessing Reports</h2>' +
            '<p>There are two ways to access Reports: Click <strong>"Reports"</strong> in the left menu (📊 icon), or click the <strong>Reports card</strong> on the Dashboard. Both methods take you to the Reports page.</p>' +
            '<h2>Types of Reports Available – Complete List</h2>' +
            '<h3>Worker Reports</h3>' +
            '<p><strong>Worker List</strong> – Complete list of all workers with details (name, passport, nationality, agent, status). Filter by status, agent, nationality, date range. Export to Excel or PDF.</p>' +
            '<p><strong>Worker Status Report</strong> – Summary by status (how many pending, active, deployed, returned). Shows counts and percentages. Useful for tracking recruitment pipeline.</p>' +
            '<p><strong>Worker by Agent Report</strong> – Workers grouped by agent. Shows how many workers each agent has, active vs inactive. Helps evaluate agent performance.</p>' +
            '<p><strong>Worker by Nationality Report</strong> – Workers grouped by country. Shows distribution of workers by nationality. Useful for compliance and planning.</p>' +
            '<h3>Financial Reports</h3>' +
            '<p><strong>Income Statement</strong> – Revenue and expenses over a period. Shows profit or loss. Filter by date range. Essential for financial analysis.</p>' +
            '<p><strong>Balance Sheet</strong> – Assets, liabilities, and equity at a specific date. Shows financial position. Select date to see balances as of that date.</p>' +
            '<p><strong>Cash Flow</strong> – Cash inflows and outflows over a period. Shows where money came from and where it went. Helps with cash management.</p>' +
            '<p><strong>Profit & Loss</strong> – Revenue minus expenses. Similar to income statement but may have different format or detail level.</p>' +
            '<h3>HR Reports</h3>' +
            '<p><strong>Employee List</strong> – Complete list of all employees with details (name, department, position, status). Filter by department, status, hire date.</p>' +
            '<p><strong>Attendance Report</strong> – Attendance records for a period. Shows present, absent, late, leave for each employee. Filter by employee, date range, department.</p>' +
            '<p><strong>Salary Report</strong> – Salary details for a period. Shows base salary, deductions, bonuses, net salary. Filter by employee, month, department.</p>' +
            '<p><strong>Payroll Summary</strong> – Total payroll costs for a period. Shows total salaries, deductions, net pay. Useful for budgeting.</p>' +
            '<h3>Agent Reports</h3>' +
            '<p><strong>Agent Summary</strong> – Overview of all agents with statistics (total workers, active workers, status). Shows agent performance at a glance.</p>' +
            '<p><strong>Agent Activity Report</strong> – Activity by agent over a period. Shows workers added, cases created, transactions linked. Helps evaluate agent relationships.</p>' +
            '<h3>Case Reports</h3>' +
            '<p><strong>Case Status Report</strong> – Cases grouped by status (open, in progress, pending, resolved, closed). Shows case pipeline.</p>' +
            '<p><strong>Case by Agent/Worker Report</strong> – Cases linked to specific agents or workers. Shows case distribution.</p>' +
            '<h3>Activity Logs / History</h3>' +
            '<p><strong>System Activity Log</strong> – Who did what and when. Shows user actions across the system. Filter by user, date, action type. Useful for audit and troubleshooting.</p>' +
            '<p><strong>Module History</strong> – History for a specific module (e.g. Workers history, Accounting history). Shows changes made to records.</p>' +
            '<h2>Generating a Report – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click "Reports"</strong> in the left menu.</li>' +
            '<li><strong>Select report type</strong> from dropdown or category list (e.g. Worker Reports → Worker List, Financial Reports → Income Statement).</li>' +
            '<li><strong>Choose filters:</strong> Date Range (From Date and To Date – select dates using date picker), Status (select status if report supports it: All, Active, Inactive, Pending, etc.), Entity (select Agent, Worker, Department, etc. if applicable), Other filters (department, nationality, priority, etc. depending on report type).</li>' +
            '<li><strong>Click "Generate Report"</strong> or "Run Report" button.</li>' +
            '<li><strong>Report displays</strong> in table format (or chart for some reports). Review the data.</li>' +
            '<li><strong>Export options</strong> (if available): Print (opens print dialog to print report), Export to Excel (downloads as .xlsx file – opens in Excel), Export to PDF (downloads as .pdf file – opens in PDF viewer), Export to CSV (downloads as .csv file – opens in spreadsheet).</li>' +
            '<li><strong>Save settings</strong> (if available) – Some reports let you save filter preferences for reuse. Click "Save Settings" and give it a name. Next time, select saved settings from dropdown.</li></ol>' +
            '<h2>Understanding Report Formats</h2>' +
            '<p><strong>Table Format:</strong> Most reports show data in tables with rows and columns. Columns show different fields (name, date, amount, status, etc.). Rows show individual records. Tables are sortable (click column header to sort). Tables are paginated if there are many rows.</p>' +
            '<p><strong>Chart Format:</strong> Some reports show charts (bar charts, line charts, pie charts). Charts visualize trends or distributions. Hover over chart elements to see values. Charts may have legends explaining colors or categories.</p>' +
            '<p><strong>Summary Format:</strong> Some reports show summaries (totals, averages, counts). Summary reports give high-level overview without listing every record.</p>' +
            '<h2>Using Report Filters Effectively</h2>' +
            '<p><strong>Date Range:</strong> Always set appropriate date range. Use "This Month", "Last Month", "This Year", "Custom Range" options. Custom range lets you select any start and end date.</p>' +
            '<p><strong>Status Filter:</strong> Filter by status to focus on specific states (e.g. only Active workers, only Open cases). Use "All" to see everything.</p>' +
            '<p><strong>Entity Filter:</strong> Filter by agent, worker, department, etc. to see data for specific entities. Useful for focused analysis.</p>' +
            '<p><strong>Combining Filters:</strong> Use multiple filters together (e.g. Date Range + Status + Agent) for precise results. Clear filters to reset.</p>' +
            '<h2>Exporting Reports – Complete Guide</h2>' +
            '<p><strong>Export to Excel:</strong> Click "Export to Excel" button. File downloads to your computer (usually in Downloads folder). Open file in Excel. Data is formatted in spreadsheet. You can edit, add formulas, create charts in Excel.</p>' +
            '<p><strong>Export to PDF:</strong> Click "Export to PDF" button. File downloads as PDF. Open in PDF viewer. PDF preserves formatting and is good for sharing or printing. Cannot edit PDF easily.</p>' +
            '<p><strong>Export to CSV:</strong> Click "Export to CSV" button. File downloads as CSV (comma-separated values). Opens in Excel or any spreadsheet program. CSV is simple text format, easy to import into other systems.</p>' +
            '<p><strong>Print:</strong> Click "Print" button or use Ctrl+P. Print dialog opens. Select printer, set options (pages, copies), click Print. Report prints on paper.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Use date ranges to compare periods (e.g. this month vs last month, this year vs last year).</li><li>Save or bookmark frequently used report settings if the system allows – saves time.</li><li>Export reports regularly for backup or external analysis.</li><li>If you need a report you do not see, ask your administrator – it may be a permission issue or the report may need to be configured.</li><li>Use filters to narrow results – large reports can be slow and hard to read.</li><li>Export to Excel for further analysis – add your own calculations or charts.</li></ul>',
            estimated_time: 30,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-8-2',
            title: 'Reports – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for Reports: opening page, generating reports (7 steps), exporting reports.',
            content:
            '<h2>Task 1: Opening Reports Page</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, <strong>Click</strong> <strong>"Reports"</strong> in left menu (📊 icon).</p>' +
            '<h3>Step 2: Page Loads</h3>' +
            '<p>Reports page loads. Shows available report types.</p>' +
            '<h2>Task 2: Generating a Report – 7-Step Process</h2>' +
            '<h3>Step 1: Select Report Type</h3>' +
            '<p>Select <strong>"Report Type"</strong> from dropdown. <strong>Click</strong> dropdown. Select report you need. Examples: Worker List Report, Worker Status Report, Financial Report, Attendance Report.</p>' +
            '<h3>Step 2: Select Date Range</h3>' +
            '<p>Select <strong>"Date Range"</strong> (if applicable). <strong>From Date:</strong> Click and select start date. <strong>To Date:</strong> Click and select end date.</p>' +
            '<h3>Step 3: Select Filters</h3>' +
            '<p>Select <strong>"Filters"</strong> (if available). Status filter, Entity filter (Agent, Worker, etc.), Other filters as needed.</p>' +
            '<h3>Step 4: Generate</h3>' +
            '<p><strong>Click</strong> <strong>"Generate Report"</strong> button. Report generates. Displays in table format.</p>' +
            '<h3>Step 5: Review Data</h3>' +
            '<p>Review the report data. Scroll through results. Check totals and summaries.</p>' +
            '<h3>Step 6: Export</h3>' +
            '<p>To export report: <strong>Click</strong> <strong>"Export"</strong> button. Choose format: <strong>Print</strong> – Opens print dialog. <strong>Excel</strong> – Downloads as .xlsx file. <strong>PDF</strong> – Downloads as .pdf file.</p>' +
            '<h3>Step 7: File Downloads</h3>' +
            '<p>If exporting: File downloads to your computer. Usually in "Downloads" folder. Open file to view.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Select appropriate date ranges for accurate reports.</li><li>Use filters to narrow results.</li><li>Export reports for backup and sharing.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    9: [
        {
            id: 'builtin-9-0',
            title: 'Notifications & Automation – Full Explanation',
            overview: 'How notifications and automation work in the program.',
            content: '<h2>What Notifications Do</h2><p>The program can send <strong>notifications</strong> (e.g. new case, payment received, document expiring). You see them in the <strong>Notifications</strong> area (bell icon or menu item) and sometimes as on-screen alerts.</p>' +
            '<h2>How to Use Notifications</h2><ol><li>Click <strong>Notifications</strong> in the left menu (or the bell icon).</li><li>Read the list; mark as read or open the related record (e.g. a case or worker) to take action.</li><li>If there are notification settings, choose which events you want to be notified about and how (in-app, email).</li></ol>' +
            '<h2>Automation (If Available)</h2><p>Some setups allow <strong>automation</strong> (e.g. auto-status change when a document is uploaded, or reminders). This is usually configured in System Settings or by an administrator.</p>' +
            '<h2>Notifications – Deep</h2><p>Open <strong>Notifications</strong> from the left menu (or the bell icon in the header). You see a <strong>list</strong> of notifications: each row may show title, message, date/time, and whether it is read or unread. Click a notification to open it or to go to the related record (e.g. a case, worker, or payment). Use <strong>Mark as read</strong> (or similar) so you do not lose track of what you have handled. If there is a <strong>Mark all as read</strong> button, use it to clear the list. The Dashboard may show a count of <strong>unread</strong> notifications—click it to open the Notifications page. If notification <strong>settings</strong> exist (e.g. in profile or System Settings), you can choose which events generate notifications and whether you receive them in-app or by email. Do not ignore critical alerts (e.g. document expiring, payment overdue); act on them and then mark as read.</p>' +
            '<h2>Expert Tips</h2><ul><li>Do not ignore critical notifications (e.g. overdue or expiring items).</li><li>Review notification and automation rules periodically so they stay relevant.</li></ul>',
            estimated_time: 15,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-1',
            title: 'HR Management – Complete Step-by-Step Guide',
            overview: 'Complete guide to HR module: Employees, Attendance, Salaries, Advances, Documents, and Cars management.',
            content:
            '<h2>What is HR Management?</h2>' +
            '<p>The HR module manages employees, attendance, salaries, advances, and other HR-related functions. HR Employees are your internal staff (different from Workers, which are external labour). The HR module helps you track who works for your company, their attendance, payroll, and other HR tasks.</p>' +
            '<h2>Accessing HR</h2>' +
            '<p>There are two ways to access HR: Click <strong>"HR"</strong> in the left menu (👔 icon), or click the <strong>HR card</strong> on the Dashboard. Both methods take you to the HR page.</p>' +
            '<h2>Understanding HR Dashboard</h2>' +
            '<p>When you open HR, you will see a dashboard with stat cards showing: Total Employees (count of all employees), Active Employees (currently active), Inactive Employees (no longer active), Terminated Employees (left the company), Today\'s Attendance (attendance records for today), Pending Salaries (salaries not yet processed). Below the stats, you will see module cards for different HR functions.</p>' +
            '<h2>HR Module Cards – Complete Breakdown</h2>' +
            '<p>The HR page shows module cards. Click a card to open that module. Each card has: Module name (e.g. Employees, Attendance), Icon (visual identifier), Statistics (count or summary), View button (opens module list), Add/Process button (creates new record or processes).</p>' +
            '<h3>1. Employees Module</h3>' +
            '<p><strong>What it is:</strong> View all employees, add new employees, edit employee information. Employees are your internal staff.</p>' +
            '<p><strong>Employees Table:</strong> Shows list of employees with columns: Employee ID (unique identifier), Name (full name), Email, Department, Position/Job Title, Status (Active/Inactive/Terminated), Hire Date, Actions (View, Edit, Delete).</p>' +
            '<p><strong>Adding an Employee – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "Add Employee"</strong> button (usually green).</li>' +
            '<li><strong>Fill in the form:</strong> Employee ID (auto-generated or enter manually – must be unique), Full Name (required), Email (required, must be unique), Position/Job Title (select or type), Department (select from dropdown or type), Hire Date (date employee started), Salary (monthly salary amount), Contact Information (phone, address), Status (Active/Inactive – usually Active for new employees).</li>' +
            '<li><strong>Click "Save"</strong> button.</li>' +
            '<li><strong>Employee is created</strong> – appears in the employees table.</li></ol>' +
            '<p><strong>Editing an Employee:</strong> Find employee in table, click Edit button, modify information, click Save. Changes are applied immediately.</p>' +
            '<p><strong>Viewing Employee Details:</strong> Click View button to see full employee record, attendance history, salary history, advances, documents, and related information.</p>' +
            '<h3>2. Attendance Module</h3>' +
            '<p><strong>What it is:</strong> Record daily attendance, view attendance history, generate attendance reports. Track when employees come to work.</p>' +
            '<p><strong>Attendance Table:</strong> Shows attendance records with columns: Date, Employee Name, Check-In Time, Check-Out Time, Hours Worked, Status (Present/Absent/Late/Leave), Actions (Edit, Delete).</p>' +
            '<p><strong>Recording Attendance – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "Attendance"</strong> tab or module card.</li>' +
            '<li><strong>Click "Mark Attendance"</strong> button (usually at top or in a prominent location).</li>' +
            '<li><strong>Select employee</strong> from dropdown (or search for employee name).</li>' +
            '<li><strong>Select date</strong> (default is today, but you can select any date).</li>' +
            '<li><strong>Choose status:</strong> Present (employee is present), Absent (employee is absent), Late (employee arrived late), Leave (employee is on leave).</li>' +
            '<li><strong>If Present:</strong> Enter Check-In Time (when employee arrived), Enter Check-Out Time (when employee left, or leave blank if still working), Hours Worked (calculated automatically or enter manually).</li>' +
            '<li><strong>Click "Save"</strong> button.</li>' +
            '<li><strong>Attendance is recorded</strong> – appears in attendance table.</li></ol>' +
            '<p><strong>Viewing Attendance History:</strong> Use the attendance table to see all records. Filter by employee, date range, or status. Export to Excel for reporting.</p>' +
            '<h3>3. Salaries Module</h3>' +
            '<p><strong>What it is:</strong> Manage employee salaries, process salary payments, view salary history. Handle payroll.</p>' +
            '<p><strong>Salaries Table:</strong> Shows salary records with columns: Employee Name, Month/Period, Base Salary, Deductions, Bonuses, Net Salary, Status (Pending/Processed/Paid), Payment Date, Actions.</p>' +
            '<p><strong>Processing Salary – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "Salaries"</strong> tab or module card.</li>' +
            '<li><strong>Select employee</strong> from dropdown or table.</li>' +
            '<li><strong>Select month</strong> (the period you are processing salary for).</li>' +
            '<li><strong>View calculated salary:</strong> Base Salary (employee\'s monthly salary), Deductions (if any: advances, taxes, other deductions), Bonuses (if any: performance bonus, overtime), Net Salary (Base Salary + Bonuses - Deductions).</li>' +
            '<li><strong>Review the calculation</strong> – make sure amounts are correct.</li>' +
            '<li><strong>Click "Process Payment"</strong> or "Process Salary" button.</li>' +
            '<li><strong>Record payment</strong> – The system may ask you to record this in the accounting system. Link to bank account or cash account.</li>' +
            '<li><strong>Salary is processed</strong> – status changes to Processed or Paid, and record appears in salary history.</li></ol>' +
            '<h3>4. Advances Module</h3>' +
            '<p><strong>What it is:</strong> Record advance payments to employees, track advance recovery. Advances are money given to employees before their salary.</p>' +
            '<p><strong>Advances Table:</strong> Shows advance records with columns: Employee Name, Advance Date, Amount, Reason, Status (Pending/Recovered), Recovery Date, Actions.</p>' +
            '<p><strong>Recording an Advance – Step-by-Step:</strong></p>' +
            '<ol><li><strong>Click "Advances"</strong> tab or module card.</li>' +
            '<li><strong>Click "New Advance"</strong> or "Add Advance" button.</li>' +
            '<li><strong>Select employee</strong> from dropdown.</li>' +
            '<li><strong>Enter amount</strong> (how much advance is being given).</li>' +
            '<li><strong>Enter reason</strong> (why advance is being given – e.g. emergency, medical, etc.).</li>' +
            '<li><strong>Select date</strong> (date advance is given).</li>' +
            '<li><strong>Click "Save"</strong> button.</li>' +
            '<li><strong>Advance is recorded</strong> – appears in advances table. The advance will be deducted from employee\'s next salary automatically (or you can mark it as recovered manually).</li></ol>' +
            '<h3>5. Documents Module</h3>' +
            '<p><strong>What it is:</strong> Manage employee documents (ID, contracts, certificates, etc.). Upload and organize documents for employees.</p>' +
            '<p><strong>Uploading Employee Documents:</strong> Select employee, click Upload Document, choose document type (ID, Contract, Certificate, etc.), select file, click Upload. Document is attached to employee record.</p>' +
            '<h3>6. Cars Module (if available)</h3>' +
            '<p><strong>What it is:</strong> Manage company vehicles, assign vehicles to employees. Track who has which company car.</p>' +
            '<p><strong>Adding a Car:</strong> Click Add Car, enter car details (make, model, plate number, etc.), click Save. Car is added to system.</p>' +
            '<p><strong>Assigning Car to Employee:</strong> Open car record, select employee from dropdown, set assignment date, click Save. Car is now assigned to that employee.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Record attendance daily to keep accurate records.</li><li>Process salaries on a schedule (e.g. end of month).</li><li>Track advances so they are recovered from salaries.</li><li>Keep employee documents organized and up to date.</li><li>Use HR reports to analyze attendance patterns and payroll costs.</li></ul>',
            estimated_time: 35,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-4',
            title: 'HR Management – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for HR Management: opening module, adding employees (12 steps), recording attendance (10 steps), processing salary (6 steps), recording advances (9 steps).',
            content:
            '<h2>Task 1: Opening HR Module</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, <strong>Click</strong> <strong>"HR"</strong> in left menu (👔 icon).</p>' +
            '<h3>Step 2: Page Loads</h3>' +
            '<p>HR page loads. Shows tabs: Employees, Attendance, Salaries, Advances, Cars.</p>' +
            '<h2>Task 2: Adding an Employee – 12-Step Process</h2>' +
            '<h3>Step 1: Click Employees Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Employees"</strong> tab.</p>' +
            '<h3>Step 2: Click Add Button</h3>' +
            '<p><strong>Click</strong> <strong>"Add Employee"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Fill in employee form.</p>' +
            '<h3>Step 4: Employee ID</h3>' +
            '<p>Fill in <strong>"Employee ID"</strong>. System may auto-generate. OR type custom ID. Example: "EMP001".</p>' +
            '<h3>Step 5: Full Name</h3>' +
            '<p>Fill in <strong>"Full Name"</strong> (required). Type employee\'s full name.</p>' +
            '<h3>Step 6: Position</h3>' +
            '<p>Fill in <strong>"Position"</strong> or <strong>"Job Title"</strong>. Type job title. Example: "Manager", "Accountant".</p>' +
            '<h3>Step 7: Department</h3>' +
            '<p>Fill in <strong>"Department"</strong>. Type or select department. Example: "HR", "Accounting", "Sales".</p>' +
            '<h3>Step 8: Hire Date</h3>' +
            '<p>Fill in <strong>"Hire Date"</strong>. Select date employee was hired.</p>' +
            '<h3>Step 9: Salary</h3>' +
            '<p>Fill in <strong>"Salary"</strong>. Type monthly salary amount. Example: "5000".</p>' +
            '<h3>Step 10: Contact Information</h3>' +
            '<p>Fill in contact information: Phone Number, Email, Address.</p>' +
            '<h3>Step 11: Status</h3>' +
            '<p>Select <strong>"Status"</strong>. Active or Inactive.</p>' +
            '<h3>Step 12: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Employee"</strong> button. Employee added. Success message appears.</p>' +
            '<h2>Task 3: Recording Attendance – 10-Step Process</h2>' +
            '<h3>Step 1: Click Attendance Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Attendance"</strong> tab.</p>' +
            '<h3>Step 2: Click Mark Attendance</h3>' +
            '<p><strong>Click</strong> <strong>"Mark Attendance"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Fill in attendance form.</p>' +
            '<h3>Step 4: Select Employee</h3>' +
            '<p>Select <strong>"Employee"</strong> dropdown. <strong>Click</strong> dropdown. Select employee name.</p>' +
            '<h3>Step 5: Select Date</h3>' +
            '<p>Select <strong>"Date"</strong>. Click date field. Select the date for attendance. Usually today\'s date.</p>' +
            '<h3>Step 6: Select Status</h3>' +
            '<p>Select <strong>"Status"</strong> dropdown: <strong>Present</strong> – Employee is present. <strong>Absent</strong> – Employee is absent. <strong>Late</strong> – Employee arrived late. <strong>Leave</strong> – Employee on leave. <strong>Half Day</strong> – Employee worked half day.</p>' +
            '<h3>Step 7: Check In Time</h3>' +
            '<p>Fill in <strong>"Check In Time"</strong> (if Present). Type time: HH:MM format. Example: "09:00".</p>' +
            '<h3>Step 8: Check Out Time</h3>' +
            '<p>Fill in <strong>"Check Out Time"</strong> (if Present). Type time: HH:MM format. Example: "17:00".</p>' +
            '<h3>Step 9: Notes</h3>' +
            '<p>Add <strong>"Notes"</strong> (optional). Type any notes about attendance.</p>' +
            '<h3>Step 10: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Attendance"</strong> button. Attendance recorded. Appears in attendance list.</p>' +
            '<h2>Task 4: Processing Salary – 6-Step Process</h2>' +
            '<h3>Step 1: Click Salaries Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Salaries"</strong> tab.</p>' +
            '<h3>Step 2: See List</h3>' +
            '<p>You will see list of employees.</p>' +
            '<h3>Step 3: Find Employee</h3>' +
            '<p>Find the employee. Use search if needed.</p>' +
            '<h3>Step 4: Click Process</h3>' +
            '<p><strong>Click</strong> <strong>"Process Salary"</strong> button next to employee. OR select month from dropdown first.</p>' +
            '<h3>Step 5: Review Calculation</h3>' +
            '<p>Review salary calculation: Base Salary, Deductions (if any), Bonuses (if any), Net Salary (final amount).</p>' +
            '<h3>Step 6: Process Payment</h3>' +
            '<p><strong>Click</strong> <strong>"Process Payment"</strong> button. Salary processed. Payment recorded in accounting. Success message appears.</p>' +
            '<h2>Task 5: Recording an Advance Payment – 9-Step Process</h2>' +
            '<h3>Step 1: Click Advances Tab</h3>' +
            '<p><strong>Click</strong> <strong>"Advances"</strong> tab.</p>' +
            '<h3>Step 2: Click New Advance</h3>' +
            '<p><strong>Click</strong> <strong>"New Advance"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Fill in advance form.</p>' +
            '<h3>Step 4: Select Employee</h3>' +
            '<p>Select <strong>"Employee"</strong> dropdown. Select employee receiving advance.</p>' +
            '<h3>Step 5: Amount</h3>' +
            '<p>Fill in <strong>"Amount"</strong>. Type advance amount. Example: "1000".</p>' +
            '<h3>Step 6: Date</h3>' +
            '<p>Fill in <strong>"Date"</strong>. Select date advance is given.</p>' +
            '<h3>Step 7: Reason</h3>' +
            '<p>Fill in <strong>"Reason"</strong>. Type reason for advance. Example: "Medical emergency".</p>' +
            '<h3>Step 8: Recovery Method</h3>' +
            '<p>Select <strong>"Recovery Method"</strong> (if available). Monthly deduction, One-time recovery, Custom schedule.</p>' +
            '<h3>Step 9: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Advance"</strong> button. Advance recorded. Will be deducted from future salary. Success message appears.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Record attendance daily for accurate records.</li><li>Process salaries on schedule (end of month).</li><li>Track advances carefully.</li><li>Keep employee information up to date.</li></ul>',
            estimated_time: 45,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-2',
            title: 'Cases Management – Complete Step-by-Step Guide',
            overview: 'Complete guide to managing cases: adding cases, updating status, adding notes, linking to workers and agents.',
            content:
            '<h2>What is Cases Management?</h2>' +
            '<p>Cases module helps you track and manage various cases, files, or projects. A case can represent a contract file, a recruitment case, a project file, a customer issue, or any other file you need to track. Cases help you organize work and track progress.</p>' +
            '<h2>Accessing Cases</h2>' +
            '<p>There are two ways to access Cases: Click <strong>"Cases"</strong> in the left menu (📋 icon), or click the <strong>Cases card</strong> on the Dashboard. Both methods take you to the Cases page.</p>' +
            '<h2>Understanding the Cases Page – Every Element</h2>' +
            '<p>When you open Cases, you will see: <strong>Search bar</strong> – Find cases quickly by typing case title, case number, or description. Results filter as you type. <strong>Status filters</strong> – Dropdown to filter by status (All, Open, In Progress, Pending, Resolved, Closed, Urgent). Select a status to show only cases with that status. <strong>Priority filter</strong> – Filter by priority (All, Low, Medium, High, Urgent). <strong>Agent filter</strong> – Filter cases linked to a specific agent. <strong>Worker filter</strong> – Filter cases linked to a specific worker. <strong>Date filter</strong> – Filter by date range (created date or due date). <strong>Add Case button</strong> – Usually green, top right. Click to create new case. <strong>Cases table</strong> – List of all cases with columns: Case Number (unique identifier), Case Title, Status (color-coded), Priority (color-coded), Assigned To (user assigned to case), Related Entity (Agent/Worker name if linked), Created Date, Due Date (if applicable), Actions (View, Edit, Delete buttons).</p>' +
            '<h2>Adding a New Case – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click "Add Case"</strong> button (usually green, top right).</li>' +
            '<li><strong>Fill in the form:</strong></li>' +
            '<li><strong>Case Title</strong> (required, marked with *) – Enter a descriptive title for the case (e.g. "Contract for Agent ABC", "Worker Visa Application", "Customer Complaint #123").</li>' +
            '<li><strong>Case Number</strong> – Usually auto-generated by the system, but you can enter manually if allowed. Must be unique.</li>' +
            '<li><strong>Description</strong> – Detailed description of the case. Explain what this case is about, what needs to be done, or any important information.</li>' +
            '<li><strong>Priority</strong> – Select from dropdown: Low (not urgent), Medium (normal priority), High (important), Urgent (needs immediate attention). Priority affects how cases are displayed and sorted.</li>' +
            '<li><strong>Status</strong> – Select initial status: Open (case is open and active), In Progress (work is ongoing), Pending (waiting for action or information), Resolved (issue is resolved), Closed (case is closed). Status helps track case progress.</li>' +
            '<li><strong>Assigned To</strong> – Select user from dropdown (who is responsible for this case). You can only assign to users who have access to Cases.</li>' +
            '<li><strong>Related Entity</strong> – Optionally link case to: Agent (select agent from dropdown), Worker (select worker from dropdown), Other entity (if applicable). Linking helps connect cases to related records.</li>' +
            '<li><strong>Dates:</strong> Created Date (usually today, auto-filled), Due Date (when case should be completed, optional).</li>' +
            '<li><strong>Click "Save"</strong> button.</li>' +
            '<li><strong>Case is created</strong> – appears in the cases table immediately.</li></ol>' +
            '<h2>Updating Case Status – Step-by-Step</h2>' +
            '<ol><li><strong>Find the case</strong> in the table (use search or filters if needed).</li>' +
            '<li><strong>Click the status dropdown</strong> in the Status column, or click Edit button to change status in the form.</li>' +
            '<li><strong>Select new status:</strong> <strong>Open</strong> – Case is open and active. <strong>In Progress</strong> – Work is ongoing on this case. <strong>Pending</strong> – Waiting for action, information, or decision. <strong>Resolved</strong> – Issue is resolved, case is complete. <strong>Closed</strong> – Case is closed (final status).</li>' +
            '<li><strong>Status updates</strong> automatically – color changes (green for resolved/closed, yellow for pending/in progress, red for urgent, blue for open).</li>' +
            '<li><strong>If editing:</strong> Click Save to apply status change.</li></ol>' +
            '<h2>Adding Notes to Cases – Complete Guide</h2>' +
            '<ol><li><strong>Open a case</strong> (click View button or case title).</li>' +
            '<li><strong>Find "Notes" section</strong> (usually a tab or section in the case detail page).</li>' +
            '<li><strong>Type your note</strong> in the note input field. Notes can include: Updates on case progress, Important information or findings, Decisions made, Next steps or actions needed, Communication summaries.</li>' +
            '<li><strong>Click "Add Note"</strong> or "Save Note" button.</li>' +
            '<li><strong>Note is saved</strong> with timestamp (date and time) and your username. Notes appear in chronological order (newest first or oldest first, depending on system).</li>' +
            '<li><strong>Edit or delete notes</strong> (if you have permission) by clicking Edit or Delete button next to the note.</li></ol>' +
            '<h2>Viewing Case Details</h2>' +
            '<p>Click View button to see: All case information (title, description, status, priority), Assigned user, Related entities (linked agent, worker), Notes history (all notes added to the case), Documents (if any files are attached), Activity log (history of status changes and actions), Dates (created date, due date, resolved date).</p>' +
            '<h2>Editing a Case</h2>' +
            '<ol><li><strong>Find the case</strong> in the table.</li>' +
            '<li><strong>Click Edit button</strong> (pencil icon ✏️).</li>' +
            '<li><strong>Modify any information:</strong> Title, Description, Status, Priority, Assigned To, Related Entity, Due Date.</li>' +
            '<li><strong>Click Save</strong> – changes are applied.</li></ol>' +
            '<h2>Searching Cases</h2>' +
            '<ol><li><strong>Type in search box</strong> at the top of the table.</li>' +
            '<li><strong>Search by:</strong> Case title, Case number, Description text, Related entity name (agent or worker).</li>' +
            '<li><strong>Results filter automatically</strong> as you type.</li>' +
            '<li><strong>Combine with filters:</strong> Use search AND status/priority/agent filters together for precise results.</li></ol>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Use consistent case titles so search works well.</li><li>Update status regularly so the team sees current progress.</li><li>Add notes whenever something important happens – creates a history.</li><li>Set due dates for urgent cases to track deadlines.</li><li>Link cases to agents or workers when relevant – helps with reporting.</li></ul>',
            estimated_time: 28,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-5',
            title: 'Cases Management – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for Cases Management: opening page, adding cases (10 steps), adding notes (6 steps), updating status (5 steps).',
            content:
            '<h2>Task 1: Opening Cases Page</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, <strong>Click</strong> <strong>"Cases"</strong> in left menu (📋 icon).</p>' +
            '<h3>Step 2: Page Loads</h3>' +
            '<p>Cases page loads. Shows all cases in a table.</p>' +
            '<h2>Task 2: Adding a New Case – 10-Step Process</h2>' +
            '<h3>Step 1: Click Add Button</h3>' +
            '<p><strong>Click</strong> <strong>"Add Case"</strong> button.</p>' +
            '<h3>Step 2: Form Opens</h3>' +
            '<p>Fill in case form.</p>' +
            '<h3>Step 3: Case Title</h3>' +
            '<p>Fill in <strong>"Case Title"</strong> (required). Type descriptive title. Example: "Worker Visa Application - Ahmed".</p>' +
            '<h3>Step 4: Case Number</h3>' +
            '<p>Fill in <strong>"Case Number"</strong>. System may auto-generate. OR type custom number.</p>' +
            '<h3>Step 5: Description</h3>' +
            '<p>Fill in <strong>"Description"</strong>. Type detailed description. Explain what the case is about.</p>' +
            '<h3>Step 6: Priority</h3>' +
            '<p>Select <strong>"Priority"</strong> dropdown: <strong>Low</strong> – Not urgent. <strong>Medium</strong> – Normal priority. <strong>High</strong> – Important. <strong>Urgent</strong> – Very important.</p>' +
            '<h3>Step 7: Status</h3>' +
            '<p>Select <strong>"Status"</strong> dropdown: <strong>Open</strong> – Case is open. <strong>In Progress</strong> – Work ongoing. <strong>Pending</strong> – Waiting for action. <strong>Resolved</strong> – Issue resolved. <strong>Closed</strong> – Case closed.</p>' +
            '<h3>Step 8: Assigned To</h3>' +
            '<p>Select <strong>"Assigned To"</strong> dropdown. Select user responsible for case.</p>' +
            '<h3>Step 9: Related Entity</h3>' +
            '<p>Select <strong>"Related Entity"</strong> (if available). Link to Agent, Worker, or other entity.</p>' +
            '<h3>Step 10: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Case"</strong> button. Case created. Appears in cases table.</p>' +
            '<h2>Task 3: Adding Notes to a Case – 6-Step Process</h2>' +
            '<h3>Step 1: Find Case</h3>' +
            '<p>Find the case in the table.</p>' +
            '<h3>Step 2: Click View or Edit</h3>' +
            '<p><strong>Click</strong> <strong>"View"</strong> or <strong>"Edit"</strong> button.</p>' +
            '<h3>Step 3: Find Notes Section</h3>' +
            '<p>Find <strong>"Notes"</strong> section.</p>' +
            '<h3>Step 4: Click Notes Area</h3>' +
            '<p><strong>Click</strong> inside notes area.</p>' +
            '<h3>Step 5: Type Note</h3>' +
            '<p>Type your note. Example: "Called client, waiting for documents".</p>' +
            '<h3>Step 6: Add Note</h3>' +
            '<p><strong>Click</strong> <strong>"Add Note"</strong> button. Note saved with timestamp. Appears in notes list.</p>' +
            '<h2>Task 4: Updating Case Status – 5-Step Process</h2>' +
            '<h3>Step 1: Find Case</h3>' +
            '<p>Find the case in the table.</p>' +
            '<h3>Step 2: Look at Status Column</h3>' +
            '<p>Look at <strong>Status</strong> column.</p>' +
            '<h3>Step 3: Click Status or Edit</h3>' +
            '<p><strong>Click</strong> on status OR <strong>Click</strong> Edit button.</p>' +
            '<h3>Step 4: Change Status</h3>' +
            '<p>Change status dropdown to new status.</p>' +
            '<h3>Step 5: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save"</strong>. Status updates. Color indicator changes.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Add notes regularly to track case progress.</li><li>Update status as work progresses.</li><li>Link cases to related entities for better organization.</li></ul>',
            estimated_time: 25,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-3',
            title: 'Contacts & Communications – Complete Step-by-Step Guide',
            overview: 'Complete guide to managing contacts and communications: adding contacts, sending communications, tracking history.',
            content:
            '<h2>What is Contacts Management?</h2>' +
            '<p>Contacts module stores contact information for clients, vendors, partners, and other important people. Contacts are different from Agents – contacts are general people you communicate with, while Agents are your business partners. Use Contacts to store information about customers, suppliers, partners, or any other people you need to contact.</p>' +
            '<h2>Accessing Contacts</h2>' +
            '<p>There are two ways to access Contacts: Click <strong>"Contact"</strong> in the left menu (📞 icon), or click the <strong>Contact card</strong> on the Dashboard. Both methods take you to the Contacts page.</p>' +
            '<h2>Understanding the Contacts Page</h2>' +
            '<p>When you open Contacts, you will see: <strong>Search bar</strong> – Find contacts quickly by name, email, phone, or company. <strong>Type filter</strong> – Filter by contact type (All, Client, Vendor, Partner, Other). <strong>Status filter</strong> – Filter by Active/Inactive. <strong>Add Contact button</strong> – Create new contact. <strong>Contacts table</strong> – List showing: Name, Company Name, Phone Number, Email Address, Contact Type, Status, Actions (View, Edit, Delete).</p>' +
            '<h2>Adding a New Contact – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click "Add Contact"</strong> button (usually green, top right).</li>' +
            '<li><strong>Fill in the form:</strong> Contact Name (required, marked with *) – Full name of the person. Company Name (if contact represents a company). Phone Number (primary phone number). Email Address (primary email). Address (full address including city and country). Contact Type (select from dropdown: Client, Vendor, Partner, Other – helps categorize contacts). Status (Active – contact is active, Inactive – contact is no longer active). Notes (optional field for additional information, agreements, or follow-up dates).</li>' +
            '<li><strong>Click "Save"</strong> button.</li>' +
            '<li><strong>Contact is created</strong> – appears in the contacts table.</li></ol>' +
            '<h2>Editing a Contact</h2>' +
            '<ol><li><strong>Find the contact</strong> in the table (use search if needed).</li>' +
            '<li><strong>Click Edit button</strong> (pencil icon ✏️).</li>' +
            '<li><strong>Modify information</strong> you need to change.</li>' +
            '<li><strong>Click Save</strong> – changes are applied.</li></ol>' +
            '<h2>Viewing Contact Details</h2>' +
            '<p>Click View button to see: All contact information, Communication history (all communications with this contact), Related records (if any cases or transactions are linked), Notes and additional information.</p>' +
            '<h2>Communications Module – Complete Guide</h2>' +
            '<p><strong>What it is:</strong> View and manage all communications (messages, emails, calls, meetings) with contacts. Track communication history.</p>' +
            '<p><strong>Accessing Communications:</strong> Click <strong>"Communications"</strong> in the left menu (💬 icon), or access from Contacts page (there may be a Communications link).</p>' +
            '<h2>Understanding the Communications Page</h2>' +
            '<p>When you open Communications, you will see: <strong>Search bar</strong> – Find communications by subject, contact name, or message content. <strong>Type filter</strong> – Filter by communication type (All, Email, Phone, Meeting, Other). <strong>Direction filter</strong> – Filter by Incoming (received) or Outgoing (sent). <strong>Priority filter</strong> – Filter by priority (All, Low, Medium, High, Urgent). <strong>Date filter</strong> – Filter by date range. <strong>Add Communication button</strong> – Create new communication record. <strong>Communications table</strong> – List showing: Date/Time, Contact Name, Type (Email/Phone/Meeting), Direction (Incoming/Outgoing), Subject, Priority, Status, Actions (View, Edit, Delete).</p>' +
            '<h2>Sending a Communication – Complete Step-by-Step</h2>' +
            '<ol><li><strong>Click "New Communication"</strong> or "Add Communication" button (usually green).</li>' +
            '<li><strong>Fill in the form:</strong> Select Contact (required – choose contact from dropdown or search). Choose Type (Email – email message, Phone – phone call, Meeting – in-person meeting, Other – other type). Select Direction (Incoming – you received this, Outgoing – you sent this). Enter Subject (brief summary of communication). Enter Message/Notes (detailed content of the communication – what was discussed, decisions made, action items). Set Priority (Low, Medium, High, Urgent – how important this communication is). Select Date/Time (when communication happened or will happen). Add Attachments (if applicable – attach files related to communication).</li>' +
            '<li><strong>Click "Send"</strong> or "Save" button.</li>' +
            '<li><strong>Communication is recorded</strong> – appears in communications table and is linked to the contact.</li></ol>' +
            '<h2>Viewing Communication History</h2>' +
            '<p>Use the communications table to see all communications. Filter by contact to see all communications with one person. Filter by date range to see communications in a period. Filter by type to see only emails, calls, or meetings. Export to Excel for reporting or backup.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Keep contact information up to date – update phone numbers and emails when they change.</li><li>Use contact type to categorize contacts (Client, Vendor, Partner) for better organization.</li><li>Record all important communications – creates a history you can refer to later.</li><li>Use priority to identify urgent communications that need follow-up.</li><li>Link communications to contacts correctly so history is accurate.</li></ul>',
            estimated_time: 30,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-6',
            title: 'Contacts Management – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for Contacts Management: opening page, adding contacts (10 steps), sending communications (8 steps).',
            content:
            '<h2>Task 1: Opening Contacts Page</h2>' +
            '<h3>Step 1: From Dashboard</h3>' +
            '<p>From Dashboard, <strong>Click</strong> <strong>"Contact"</strong> in left menu (📞 icon).</p>' +
            '<h3>Step 2: Page Loads</h3>' +
            '<p>Contacts page loads. Shows all contacts in a table.</p>' +
            '<h2>Task 2: Adding a New Contact – 10-Step Process</h2>' +
            '<h3>Step 1: Click Add Button</h3>' +
            '<p><strong>Click</strong> <strong>"Add Contact"</strong> button.</p>' +
            '<h3>Step 2: Form Opens</h3>' +
            '<p>Fill in contact form.</p>' +
            '<h3>Step 3: Contact Name</h3>' +
            '<p>Fill in <strong>"Contact Name"</strong> (required). Type full name. Example: "Mohammed Ali".</p>' +
            '<h3>Step 4: Company Name</h3>' +
            '<p>Fill in <strong>"Company Name"</strong> (if applicable). Type company name.</p>' +
            '<h3>Step 5: Phone Number</h3>' +
            '<p>Fill in <strong>"Phone Number"</strong>. Type phone number. Include country code.</p>' +
            '<h3>Step 6: Email</h3>' +
            '<p>Fill in <strong>"Email"</strong>. Type email address.</p>' +
            '<h3>Step 7: Address</h3>' +
            '<p>Fill in <strong>"Address"</strong>. Type full address.</p>' +
            '<h3>Step 8: Contact Type</h3>' +
            '<p>Select <strong>"Contact Type"</strong> dropdown: Client, Vendor, Partner, Other.</p>' +
            '<h3>Step 9: Status</h3>' +
            '<p>Select <strong>"Status"</strong>. Active or Inactive.</p>' +
            '<h3>Step 10: Save</h3>' +
            '<p><strong>Click</strong> <strong>"Save Contact"</strong> button. Contact added. Appears in contacts table.</p>' +
            '<h2>Task 3: Sending a Communication – 8-Step Process</h2>' +
            '<h3>Step 1: Click Communications</h3>' +
            '<p><strong>Click</strong> <strong>"Communications"</strong> in left menu (💬 icon).</p>' +
            '<h3>Step 2: Click New Communication</h3>' +
            '<p><strong>Click</strong> <strong>"New Communication"</strong> button.</p>' +
            '<h3>Step 3: Form Opens</h3>' +
            '<p>Fill in communication form.</p>' +
            '<h3>Step 4: Select Contact</h3>' +
            '<p>Select <strong>"Contact"</strong> dropdown. Select recipient.</p>' +
            '<h3>Step 5: Select Type</h3>' +
            '<p>Select <strong>"Type"</strong> dropdown: Email, Phone Call, Meeting, Letter, Other.</p>' +
            '<h3>Step 6: Subject</h3>' +
            '<p>Fill in <strong>"Subject"</strong>. Type subject line.</p>' +
            '<h3>Step 7: Message</h3>' +
            '<p>Fill in <strong>"Message"</strong>. Type your message.</p>' +
            '<h3>Step 8: Send</h3>' +
            '<p><strong>Click</strong> <strong>"Send"</strong> button. Communication sent. Recorded in history.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Keep contact information current.</li><li>Record all communications for history.</li><li>Use contact types for organization.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    10: [
        {
            id: 'builtin-10-0',
            title: 'Troubleshooting & FAQ – Full Explanation',
            overview: 'Common issues and how to resolve them when using the Ratib program.',
            content: '<h2>Page Not Loading or Blank Screen</h2><p>Refresh the page (F5 or Ctrl+R). Clear your browser cache and cookies for the site. Use a supported browser (Chrome, Firefox, Edge) and keep it updated. If the problem continues, try another device or network.</p>' +
            '<h2>Cannot Log In</h2><p>Check username and password (caps lock, keyboard language). Use "Forgot password" if available. If the account is locked or disabled, an administrator must enable it in System Settings or user management.</p>' +
            '<h2>Missing Menu or Button</h2><p>Menus and actions depend on <strong>permissions</strong>. If you do not see a section or button, your role may not have the right permission. Contact your administrator to have your role updated.</p>' +
            '<h2>Data Not Saving or Error Message</h2><p>Check required fields (often marked with *). Ensure dates and numbers are in the correct format. If you see an error message, read it—it often says which field or action failed. Try again; if it persists, note the message and contact support or your administrator.</p>' +
            '<h2>Reports or Numbers Look Wrong</h2><p>Check the date range and filters. Ensure data was entered correctly in the source section (e.g. Accounting, Workers). Export and check in Excel if needed. If still wrong, report to your administrator with the report name and filters used.</p>' +
            '<h2>More Troubleshooting – Deep</h2><p><strong>Slow or freezing page</strong> – Refresh once; if it continues, close other tabs or try another browser. Large tables (many rows) can be slow—use filters to reduce data.<br><strong>Export not downloading</strong> – Check pop-up blocker and download folder. Try a different format (CSV vs Excel) if offered.<br><strong>Form won’t open or modal is blank</strong> – Refresh the page and try again. If you have required dropdowns (e.g. Agent), ensure at least one option exists.<br><strong>Duplicate key or unique error</strong> – You are entering a value that must be unique (e.g. identity number, passport number). Change the value or edit the existing record that already has it.<br><strong>Session expired / Logged out unexpectedly</strong> – Log in again. If it happens often, your session may be short; avoid leaving the tab idle for long.<br><strong>Wrong language or numbers</strong> – Use English and Western numerals (0–9) in fields; avoid pasting from other scripts if the system expects English only.<br><strong>Permission denied (403) or Unauthorized (401)</strong> – Your role does not have permission for that action. Contact your administrator to have the right permission added to your role.</p>' +
            '<h2>Expert Tips</h2><ul><li>Use the Help &amp; Learning Center (this section) for step-by-step guides.</li><li>Keep your browser and device updated for best compatibility.</li></ul>',
            estimated_time: 22,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-10-1',
            title: 'Troubleshooting – Complete User Guide',
            overview: 'Complete troubleshooting guide for common issues: login problems, missing modules, page loading, data saving, search, document upload, and more.',
            content:
            '<h2>Common Issues and Solutions – Complete Guide</h2>' +
            '<h3>Problem: Can\'t Log In</h3>' +
            '<p><strong>Possible Causes:</strong> Wrong username or password, Account is disabled, Browser issues, Session expired.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check username and password</strong> – Make sure Caps Lock is off. Check keyboard language (English vs Arabic). Type carefully – usernames and passwords are case-sensitive.</li>' +
            '<li><strong>Click "Forgot Password"</strong> – If available, click this link to reset your password. Follow instructions sent to your email.</li>' +
            '<li><strong>Clear browser cache</strong> – Clear cookies and cache for the site. In Chrome: Settings → Privacy → Clear browsing data → Cookies and cached images.</li>' +
            '<li><strong>Try different browser</strong> – Use Chrome, Firefox, or Edge. Some browsers may have compatibility issues.</li>' +
            '<li><strong>Contact administrator</strong> – If nothing works, your account may be disabled or locked. Administrator must enable it in System Settings.</li></ol>' +
            '<h3>Problem: Can\'t See a Module</h3>' +
            '<p><strong>Possible Causes:</strong> You don\'t have permission, Module is disabled.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check with administrator</strong> – Ask for permission. Your role may not have access to that module.</li>' +
            '<li><strong>Refresh page</strong> – Sometimes permissions update after refresh. Press F5 or click refresh button.</li>' +
            '<li><strong>Logout and login again</strong> – Refresh your session. Permissions are loaded when you log in.</li></ol>' +
            '<h3>Problem: Page Won\'t Load</h3>' +
            '<p><strong>Possible Causes:</strong> Internet connection issue, Server problem, Browser issue.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check internet connection</strong> – Make sure you\'re online. Try opening another website to test connection.</li>' +
            '<li><strong>Refresh page</strong> – Press F5 or click refresh button. Sometimes pages fail to load temporarily.</li>' +
            '<li><strong>Try different browser</strong> – Switch browsers (Chrome, Firefox, Edge). Some browsers may have issues.</li>' +
            '<li><strong>Clear browser cache</strong> – Clear cache and cookies. Old cached files can cause loading problems.</li>' +
            '<li><strong>Contact IT support</strong> – If problem persists, there may be a server issue. Contact IT support with details.</li></ol>' +
            '<h3>Problem: Can\'t Save Data</h3>' +
            '<p><strong>Possible Causes:</strong> Required fields missing, Validation errors, Server issue.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check for red error messages</strong> – Read error messages carefully. They tell you what\'s wrong.</li>' +
            '<li><strong>Fill required fields</strong> – Fields with * (asterisk) are required. Fill all required fields before saving.</li>' +
            '<li><strong>Check data format</strong> – Dates must be in correct format (e.g. YYYY-MM-DD). Numbers must be valid (no letters in number fields). Email must be valid format.</li>' +
            '<li><strong>Try again</strong> – Sometimes temporary server issues. Wait a moment and try saving again.</li>' +
            '<li><strong>Contact support</strong> – If problem continues, note the error message and contact support.</li></ol>' +
            '<h3>Problem: Search Not Working</h3>' +
            '<p><strong>Possible Causes:</strong> Typed incorrectly, No matching results, Search index issue.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check spelling</strong> – Make sure words are spelled correctly. Search is usually case-sensitive.</li>' +
            '<li><strong>Try different keywords</strong> – Use alternative search terms. Try partial words or different spellings.</li>' +
            '<li><strong>Clear search</strong> – Clear search box and try again. Sometimes search gets stuck.</li>' +
            '<li><strong>Use filters</strong> – Try using filters instead of search. Filters may work when search doesn\'t.</li></ol>' +
            '<h3>Problem: Can\'t Upload Document</h3>' +
            '<p><strong>Possible Causes:</strong> File too large, Wrong file type, Browser issue.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check file size</strong> – Make sure file is under size limit (usually 5MB or 10MB). Compress large files or use smaller files.</li>' +
            '<li><strong>Check file type</strong> – Use allowed formats (PDF, JPG, PNG, DOC, DOCX). Some systems only accept specific formats.</li>' +
            '<li><strong>Try different file</strong> – Test with another file to see if problem is with specific file.</li>' +
            '<li><strong>Try different browser</strong> – Switch browsers. Some browsers handle file uploads differently.</li>' +
            '<li><strong>Contact support</strong> – If nothing works, contact support with file details (size, type, error message).</li></ol>' +
            '<h3>Problem: Reports or Numbers Look Wrong</h3>' +
            '<p><strong>Possible Causes:</strong> Wrong date range, Incorrect filters, Data entry errors.</p>' +
            '<p><strong>Solutions:</strong></p>' +
            '<ol><li><strong>Check date range</strong> – Make sure From Date and To Date are correct. Reports only show data in selected range.</li>' +
            '<li><strong>Check filters</strong> – Review all filters (status, agent, department, etc.). Filters may exclude data you expect to see.</li>' +
            '<li><strong>Verify source data</strong> – Check the source section (e.g. Accounting, Workers) to ensure data was entered correctly.</li>' +
            '<li><strong>Export and check</strong> – Export report to Excel and review data manually.</li>' +
            '<li><strong>Report to administrator</strong> – If still wrong, report with report name and filters used.</li></ol>' +
            '<h3>Problem: Slow or Freezing Page</h3>' +
            '<p><strong>Solutions:</strong> Refresh once. If it continues, close other tabs or try another browser. Large tables (many rows) can be slow – use filters to reduce data. Clear browser cache. Check internet connection speed.</p>' +
            '<h3>Problem: Export Not Downloading</h3>' +
            '<p><strong>Solutions:</strong> Check pop-up blocker settings. Check download folder permissions. Try a different format (CSV vs Excel) if offered. Check browser download settings.</p>' +
            '<h3>Problem: Form Won\'t Open or Modal is Blank</h3>' +
            '<p><strong>Solutions:</strong> Refresh the page and try again. If you have required dropdowns (e.g. Agent), ensure at least one option exists. Clear browser cache. Try different browser.</p>' +
            '<h3>Problem: Duplicate Key or Unique Error</h3>' +
            '<p><strong>Solutions:</strong> You are entering a value that must be unique (e.g. identity number, passport number, email). Change the value or edit the existing record that already has it. Check if record already exists before adding new one.</p>' +
            '<h3>Problem: Session Expired / Logged Out Unexpectedly</h3>' +
            '<p><strong>Solutions:</strong> Log in again. If it happens often, your session may be short – avoid leaving the tab idle for long. Some systems log you out after inactivity for security.</p>' +
            '<h3>Problem: Wrong Language or Numbers</h3>' +
            '<p><strong>Solutions:</strong> Use English and Western numerals (0–9) in fields. Avoid pasting from other scripts if the system expects English only. Check keyboard language settings.</p>' +
            '<h3>Problem: Permission Denied (403) or Unauthorized (401)</h3>' +
            '<p><strong>Solutions:</strong> Your role does not have permission for that action. Contact your administrator to have the right permission added to your role. Refresh page after permissions are updated.</p>' +
            '<h2>Getting Help</h2>' +
            '<p>If you encounter problems:</p>' +
            '<ol><li><strong>Check Help Center</strong> – Search for your issue in Help &amp; Learning Center.</li>' +
            '<li><strong>Read Error Messages</strong> – Error messages often tell you what\'s wrong. Read them carefully.</li>' +
            '<li><strong>Contact Administrator</strong> – Reach out to your system administrator for permission or configuration issues.</li>' +
            '<li><strong>Report Bug</strong> – If you find a bug, report it with details: What you were trying to do, What happened instead, Error messages (if any), Screenshots (if possible).</li></ol>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Use the Help &amp; Learning Center (this section) for step-by-step guides.</li><li>Keep your browser and device updated for best compatibility.</li><li>Clear browser cache regularly to avoid loading issues.</li><li>Take screenshots of error messages to help support diagnose problems.</li></ul>',
            estimated_time: 35,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    11: [
        {
            id: 'builtin-11-0',
            title: 'Best Practices – Full Explanation',
            overview: 'Recommended ways to use the program for consistency and efficiency.',
            content: '<h2>Data Entry and Naming</h2><p>Use <strong>consistent naming</strong> for agents, workers, and accounts (same spelling and format). Fill required fields and use the same date format everywhere. This keeps search and reports accurate.</p>' +
            '<h2>Regular Tasks</h2><p>Review and update data on a schedule: e.g. worker status weekly, documents when received, accounting reconciliation monthly. Assign clear responsibilities so nothing is missed.</p>' +
            '<h2>Security and Access</h2><p>Do not share passwords. Use roles with the minimum permissions needed. Log out when leaving a shared computer. Admins should review user and permission lists periodically.</p>' +
            '<h2>Using the Program as a Team</h2><p>Document your own procedures (who does what, in which order) and share them with the team. Use the Help &amp; Learning Center for training. Report unclear or missing features so they can be improved or documented.</p>' +
            '<h2>Best Practices – Deep</h2><p><strong>Naming</strong> – Use the same spelling and format for agent names, worker names, and account names across the system. Avoid abbreviations unless everyone uses the same ones.<br><strong>Dates</strong> – Use one date format (e.g. YYYY-MM-DD or company standard) everywhere. Do not mix formats in the same field type.<br><strong>Required fields</strong> – Always fill required (*) fields before saving; optional fields can be filled later but required ones block save and cause repeated errors.<br><strong>Status</strong> – Update worker status, case status, and agent status when things change so filters and reports reflect reality.<br><strong>Documents</strong> – Upload and attach documents in the correct place (worker documents, case attachments) and update document status so the team knows what is on file.<br><strong>Reconciliation</strong> – Reconcile accounting (bank, cash) on a schedule. Review worker and case lists periodically for stale or incorrect data.<br><strong>Backups and export</strong> – Export important lists or reports on a schedule if the system allows; keep backups as per company policy.<br><strong>Security</strong> – Do not share passwords. Log out on shared computers. Admins: limit admin accounts and give roles only the permissions they need.</p>' +
            '<h2>Expert Tips</h2><ul><li>Document team procedures for onboarding and training.</li><li>Share tips with colleagues to keep usage consistent.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-11-1',
            title: 'Tips & Best Practices – Complete User Guide',
            overview: 'Complete guide to best practices: general tips, data entry tips, navigation tips, security tips, performance tips, and quick reference.',
            content:
            '<h2>General Tips</h2>' +
            '<ol><li><strong>Save Frequently</strong> – Don\'t forget to click Save after making changes. Some forms may auto-save, but always check. Losing work is frustrating.</li>' +
            '<li><strong>Use Search</strong> – Most pages have search functionality – use it! Search is faster than scrolling through long lists.</li>' +
            '<li><strong>Check Status Colors</strong> – Green = Good, Red = Needs Attention. Learn the color coding to quickly identify status.</li>' +
            '<li><strong>Read Messages</strong> – System messages tell you if something succeeded or failed. Read success and error messages carefully.</li>' +
            '<li><strong>Use Filters</strong> – Filter tables to find what you need quickly. Filters reduce data and make pages faster.</li></ol>' +
            '<h2>Data Entry Tips</h2>' +
            '<ol><li><strong>Fill Required Fields</strong> – Fields marked with * (asterisk) are required. Fill them before saving to avoid errors.</li>' +
            '<li><strong>Double-Check Information</strong> – Verify data before saving. Wrong data causes problems later. Check names, numbers, dates.</li>' +
            '<li><strong>Use Consistent Format</strong> – Enter dates, phone numbers consistently. Use same format everywhere (e.g. YYYY-MM-DD for dates).</li>' +
            '<li><strong>Upload Documents</strong> – Keep documents organized and named clearly. Use descriptive file names (e.g. "Passport_John_Smith_2024.pdf").</li>' +
            '<li><strong>Don\'t Leave Fields Blank</strong> – Fill optional fields when possible. Complete records are more useful than incomplete ones.</li></ol>' +
            '<h2>Navigation Tips</h2>' +
            '<ol><li><strong>Use Dashboard</strong> – Start from Dashboard to see overview. Dashboard shows key numbers and shortcuts.</li>' +
            '<li><strong>Breadcrumbs</strong> – Use breadcrumbs (if shown) to navigate back. Breadcrumbs show your current location.</li>' +
            '<li><strong>Menu Icons</strong> – Icons help identify modules quickly. Learn the icons for common modules.</li>' +
            '<li><strong>Keyboard Shortcuts</strong> – Some pages support keyboard shortcuts (Ctrl+S to save, Ctrl+F to search). Learn shortcuts to work faster.</li>' +
            '<li><strong>Bookmark Important Pages</strong> – Bookmark frequently used pages in your browser for quick access.</li></ol>' +
            '<h2>Security Tips</h2>' +
            '<ol><li><strong>Logout When Done</strong> – Always logout when finished, especially on shared computers. Don\'t leave your session open.</li>' +
            '<li><strong>Don\'t Share Password</strong> – Keep your password private. Don\'t write it down or share with others.</li>' +
            '<li><strong>Report Issues</strong> – Report any suspicious activity or security concerns to your administrator immediately.</li>' +
            '<li><strong>Check Permissions</strong> – If you can\'t access something, ask administrator. Don\'t try to bypass permissions.</li>' +
            '<li><strong>Use Strong Passwords</strong> – Use passwords with letters, numbers, and symbols. Change passwords regularly.</li></ol>' +
            '<h2>Performance Tips</h2>' +
            '<ol><li><strong>Use Filters</strong> – Filter large lists instead of scrolling. Filters reduce data and make pages load faster.</li>' +
            '<li><strong>Search Instead of Browse</strong> – Use search for faster results. Search is usually faster than browsing through pages.</li>' +
            '<li><strong>Close Unused Tabs</strong> – Close browser tabs you\'re not using. Too many tabs slow down your browser.</li>' +
            '<li><strong>Clear Browser Cache</strong> – If pages load slowly, clear cache. Old cached files can slow down loading.</li>' +
            '<li><strong>Use Pagination</strong> – Don\'t try to load all records at once. Use pagination to view data in smaller chunks.</li></ol>' +
            '<h2>Quick Reference Guide</h2>' +
            '<h3>Common Tasks – Step Count</h3>' +
            '<table class="help-table" style="width:100%; border-collapse: collapse;"><tr><th style="border:1px solid #ddd; padding:8px;">Task</th><th style="border:1px solid #ddd; padding:8px;">Steps</th></tr>' +
            '<tr><td style="border:1px solid #ddd; padding:8px;">Add Worker</td><td style="border:1px solid #ddd; padding:8px;">3 steps</td></tr>' +
            '<tr><td style="border:1px solid #ddd; padding:8px;">Upload Document</td><td style="border:1px solid #ddd; padding:8px;">4 steps</td></tr>' +
            '<tr><td style="border:1px solid #ddd; padding:8px;">Create Invoice</td><td style="border:1px solid #ddd; padding:8px;">4 steps</td></tr>' +
            '<tr><td style="border:1px solid #ddd; padding:8px;">Generate Report</td><td style="border:1px solid #ddd; padding:8px;">4 steps</td></tr>' +
            '<tr><td style="border:1px solid #ddd; padding:8px;">Record Attendance</td><td style="border:1px solid #ddd; padding:8px;">5 steps</td></tr>' +
            '<tr><td style="border:1px solid #ddd; padding:8px;">Change Status</td><td style="border:1px solid #ddd; padding:8px;">2 steps</td></tr></table>' +
            '<h3>Keyboard Shortcuts (Where Available)</h3>' +
            '<ul><li><strong>Ctrl + S</strong> – Save (on forms)</li>' +
            '<li><strong>Ctrl + F</strong> – Search (in tables)</li>' +
            '<li><strong>Esc</strong> – Close modals/popups</li>' +
            '<li><strong>Enter</strong> – Submit forms</li>' +
            '<li><strong>F5</strong> – Refresh page</li></ul>' +
            '<h3>Status Meanings</h3>' +
            '<p><strong>Workers:</strong> 🟡 Pending – Awaiting approval. 🟢 Approved – Ready to proceed. 🔴 Rejected – Application rejected. 🔵 Deployed – Currently deployed. ⚪ Returned – Has returned.</p>' +
            '<p><strong>Cases:</strong> 🔵 Open – Case is open. 🟡 In Progress – Work ongoing. 🟡 Pending – Waiting. 🟢 Resolved – Issue fixed. ⚪ Closed – Case closed.</p>' +
            '<p><strong>General:</strong> 🟢 Active – Currently active. 🔴 Inactive – Disabled/turned off.</p>' +
            '<h2>Training Checklist</h2>' +
            '<p>Use this checklist to track your learning:</p>' +
            '<ul><li>☐ I can log in successfully</li>' +
            '<li>☐ I understand the Dashboard</li>' +
            '<li>☐ I can navigate the menu</li>' +
            '<li>☐ I can add a new worker</li>' +
            '<li>☐ I can edit worker information</li>' +
            '<li>☐ I can upload documents</li>' +
            '<li>☐ I can change worker status</li>' +
            '<li>☐ I can add an agent</li>' +
            '<li>☐ I can add a subagent</li>' +
            '<li>☐ I can create an invoice</li>' +
            '<li>☐ I can create a bill</li>' +
            '<li>☐ I can generate a report</li>' +
            '<li>☐ I can record attendance</li>' +
            '<li>☐ I can add a case</li>' +
            '<li>☐ I can add a contact</li>' +
            '<li>☐ I can use the Help Center</li>' +
            '<li>☐ I know how to get help</li></ul>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Take your time – Don\'t rush, accuracy is important.</li>' +
            '<li>Save frequently – Don\'t lose your work.</li>' +
            '<li>Ask for help – It\'s better to ask than make mistakes.</li>' +
            '<li>Use the Help Center – It\'s there to help you succeed.</li>' +
            '<li>Practice regularly – The more you use the system, the more comfortable you\'ll become.</li></ul>',
            estimated_time: 30,
            difficulty_level: 'beginner',
            views_count: 0
        }
    ],
    12: [
        {
            id: 'builtin-12-0',
            title: 'Compliance & Legal – Full Explanation',
            overview: 'How to use the program in line with compliance and legal requirements.',
            content: '<h2>Why This Matters</h2><p>Your business must meet legal and regulatory requirements (e.g. labour, visa, tax, data protection). The Ratib program helps you keep records and workflows in one place so you can demonstrate compliance when needed.</p>' +
            '<h2>How the Program Supports Compliance</h2><p>Use the program to: keep <strong>accurate records</strong> (workers, contracts, transactions); track <strong>status and dates</strong> (visas, documents, payments); and generate <strong>reports</strong> for authorities or auditors. Do not delete or alter records that may be needed for audits; use status or notes instead.</p>' +
            '<h2>What You Should Do</h2><ol><li>Read any compliance or legal guidelines your company has provided.</li><li>Use the program as intended: enter data on time, attach documents, and run reports when required.</li><li>If you see a possible compliance risk (e.g. missing document, expired visa), report it through your company channel and follow up in the system.</li></ol>' +
            '<h2>Compliance – Deep</h2><p><strong>Records</strong> – The program stores workers, agents, cases, transactions, and history. Do not delete or alter records that may be needed for audits or authorities. Use status (e.g. inactive, closed) instead of deleting.<br><strong>Documents</strong> – Attach and keep identity, visa, contract, and other required documents in the worker or case record. Update document status and dates so you can prove what was on file and when.<br><strong>Dates and status</strong> – Keep visa expiry, passport expiry, and contract dates accurate. Use filters and reports to find expiring items and act on them in time.<br><strong>Reports</strong> – Run and export reports required by your company or regulators (e.g. worker list by status, financial reports by period). Keep exported copies as per company policy.<br><strong>Audit trail</strong> – The program may log who changed what and when (activity logs, global history). Do not try to bypass or erase these; they support compliance.<br><strong>Data protection</strong> – Follow company rules on who can see personal data. Use roles and permissions so only authorised users access sensitive sections. Do not share login details.</p>' +
            '<h2>Expert Tips</h2><ul><li>Keep records that may be needed for audits; avoid deleting important history.</li><li>When in doubt about a legal or compliance question, ask your supervisor or legal contact—do not rely on the program alone for legal advice.</li></ul>',
            estimated_time: 18,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-12-3',
            title: 'Quick Reference – Common Tasks Guide',
            overview: 'Quick reference guide for common tasks: logging out, changing password, printing pages.',
            content:
            '<h2>How to Logout</h2>' +
            '<h3>Step 1: Scroll to Bottom</h3>' +
            '<p>Scroll to bottom of left sidebar menu.</p>' +
            '<h3>Step 2: Click Logout</h3>' +
            '<p><strong>Click</strong> <strong>"Logout"</strong> (🚪 icon).</p>' +
            '<h3>Step 3: Confirmation</h3>' +
            '<p>Confirmation may appear. <strong>Click</strong> "Yes" to confirm. OR automatically logs out.</p>' +
            '<h3>Step 4: Logged Out</h3>' +
            '<p>You are logged out. Redirected to login page.</p>' +
            '<h2>How to Change Your Password</h2>' +
            '<h3>Step 1: Click Profile</h3>' +
            '<p><strong>Click</strong> your name or profile icon (top right).</p>' +
            '<h3>Step 2: Open Settings</h3>' +
            '<p><strong>Click</strong> <strong>"Profile"</strong> or <strong>"Settings"</strong>.</p>' +
            '<h3>Step 3: Find Change Password</h3>' +
            '<p>Find <strong>"Change Password"</strong> section.</p>' +
            '<h3>Step 4: Fill In</h3>' +
            '<p>Fill in: Current Password, New Password, Confirm New Password.</p>' +
            '<h3>Step 5: Update</h3>' +
            '<p><strong>Click</strong> <strong>"Update Password"</strong> button. Password changed. Success message appears.</p>' +
            '<h2>How to Print a Page</h2>' +
            '<h3>Step 1: Press Print Shortcut</h3>' +
            '<p>On any page, press <strong>Ctrl + P</strong> on keyboard. OR right-click and select "Print". OR click browser menu → Print.</p>' +
            '<h3>Step 2: Print Dialog Opens</h3>' +
            '<p>Print dialog opens.</p>' +
            '<h3>Step 3: Select Printer</h3>' +
            '<p>Select printer.</p>' +
            '<h3>Step 4: Click Print</h3>' +
            '<p><strong>Click</strong> <strong>"Print"</strong> button.</p>',
            estimated_time: 8,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-12-4',
            title: 'Important Notes for Trainees',
            overview: 'Important notes for trainees: before starting tasks, common mistakes to avoid, and getting help.',
            content:
            '<h2>Before Starting Any Task</h2>' +
            '<ol><li><strong>Make sure you\'re logged in</strong> – Check that you are logged into the system before starting any work.</li>' +
            '<li><strong>Check your permissions</strong> – You may not see all modules. If a module is missing, your role may not have permission. Contact your administrator.</li>' +
            '<li><strong>Save frequently</strong> – Don\'t lose your work. Click Save regularly, especially when entering large amounts of data.</li>' +
            '<li><strong>Read error messages</strong> – They tell you what\'s wrong. Error messages are usually in red and explain what needs to be fixed.</li>' +
            '<li><strong>Ask for help</strong> – Don\'t hesitate to ask questions. Use the Help Center or contact your administrator.</li></ol>' +
            '<h2>Common Mistakes to Avoid</h2>' +
            '<ol><li><strong>Don\'t click buttons multiple times</strong> – Wait for response. Clicking multiple times can cause errors or duplicate entries.</li>' +
            '<li><strong>Don\'t forget required fields</strong> – Fields with * are mandatory. Fill all required fields before saving.</li>' +
            '<li><strong>Don\'t close browser without saving</strong> – Save your work first. Closing the browser without saving will lose your work.</li>' +
            '<li><strong>Don\'t use browser back button</strong> – Use system navigation instead. The browser back button can cause issues with forms and data.</li>' +
            '<li><strong>Don\'t ignore error messages</strong> – Read and fix them. Error messages tell you exactly what\'s wrong.</li></ol>' +
            '<h2>Getting Help</h2>' +
            '<ol><li><strong>Use Help Center</strong> – Search for your question. The Help Center has tutorials for almost everything.</li>' +
            '<li><strong>Read error messages</strong> – They often tell you what to fix. Error messages are your first source of help.</li>' +
            '<li><strong>Contact administrator</strong> – For permission issues. If you can\'t access something, your administrator can help.</li>' +
            '<li><strong>Check this manual</strong> – Refer back to relevant sections. This detailed manual covers every task step-by-step.</li></ol>',
            estimated_time: 10,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-12-5',
            title: 'Training Completion Checklist',
            overview: 'Complete training checklist to ensure you\'ve learned all essential tasks: basic navigation, workers, agents, accounting, HR, cases, contacts, reports, and help.',
            content:
            '<h2>Training Completion Checklist</h2>' +
            '<p>Use this checklist to ensure you\'ve learned all essential tasks:</p>' +
            '<h3>Basic Navigation</h3>' +
            '<ul><li>☐ I can log in successfully</li>' +
            '<li>☐ I understand the Dashboard layout</li>' +
            '<li>☐ I can navigate between modules using the menu</li>' +
            '<li>☐ I know how to logout</li></ul>' +
            '<h3>Workers Management</h3>' +
            '<ul><li>☐ I can add a new worker with all required fields</li>' +
            '<li>☐ I can search for a worker</li>' +
            '<li>☐ I can filter workers by status</li>' +
            '<li>☐ I can view worker details</li>' +
            '<li>☐ I can edit worker information</li>' +
            '<li>☐ I can upload documents to a worker</li>' +
            '<li>☐ I can change worker status</li>' +
            '<li>☐ I can delete a worker</li>' +
            '<li>☐ I can perform bulk operations</li></ul>' +
            '<h3>Agents &amp; Subagents</h3>' +
            '<ul><li>☐ I can add a new agent</li>' +
            '<li>☐ I can edit an agent</li>' +
            '<li>☐ I can add a new subagent</li>' +
            '<li>☐ I understand the agent-subagent relationship</li></ul>' +
            '<h3>Accounting</h3>' +
            '<ul><li>☐ I can view the accounting dashboard</li>' +
            '<li>☐ I can create a journal entry</li>' +
            '<li>☐ I can create an invoice</li>' +
            '<li>☐ I can create a bill</li>' +
            '<li>☐ I can add a bank account</li>' +
            '<li>☐ I can record a bank transaction</li>' +
            '<li>☐ I can generate a financial report</li></ul>' +
            '<h3>HR Management</h3>' +
            '<ul><li>☐ I can add an employee</li>' +
            '<li>☐ I can record attendance</li>' +
            '<li>☐ I can process a salary</li>' +
            '<li>☐ I can record an advance payment</li></ul>' +
            '<h3>Cases &amp; Contacts</h3>' +
            '<ul><li>☐ I can add a new case</li>' +
            '<li>☐ I can update case status</li>' +
            '<li>☐ I can add notes to a case</li>' +
            '<li>☐ I can add a new contact</li>' +
            '<li>☐ I can send a communication</li></ul>' +
            '<h3>Reports &amp; Help</h3>' +
            '<ul><li>☐ I can generate a report</li>' +
            '<li>☐ I can export a report</li>' +
            '<li>☐ I can use the Help Center</li>' +
            '<li>☐ I can search for help</li></ul>' +
            '<h2>Congratulations! 🎉</h2>' +
            '<p>You have completed the detailed training manual. Practice these tasks regularly to become proficient with the Ratib Program system.</p>' +
            '<h2>Remember</h2>' +
            '<ul><li>Take your time</li>' +
            '<li>Follow steps carefully</li>' +
            '<li>Save your work frequently</li>' +
            '<li>Ask questions when needed</li>' +
            '<li>Use the Help Center</li></ul>' +
            '<h2>Good luck with your training! 🚀</h2>' +
            '<p><em>Document Version: 1.0.0<br>Last Updated: February 2026<br>For: New User Training</em></p>',
            estimated_time: 15,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-12-1',
            title: 'Help Center – Complete User Guide',
            overview: 'Complete guide to using the Help & Learning Center: browsing categories, searching tutorials, reading guides, and getting help anytime.',
            content:
            '<h2>What is the Help Center?</h2>' +
            '<p>The Help Center provides tutorials, guides, and answers to common questions. It is your comprehensive learning resource within the Ratib program. Use it to learn how to use any section, find answers to questions, and get training materials.</p>' +
            '<h2>Accessing Help Center</h2>' +
            '<p>There are multiple ways to access the Help Center:</p>' +
            '<ol><li><strong>Scroll down</strong> in the left menu and click <strong>"Help &amp; Learning Center"</strong> (📚 icon).</li>' +
            '<li><strong>Access from any page</strong> using the help icon (if available on the page).</li>' +
            '<li><strong>Use keyboard shortcut</strong> (if configured) to open Help Center quickly.</li></ol>' +
            '<h2>Understanding the Help Center Interface</h2>' +
            '<p>When you open the Help Center, you will see:</p>' +
            '<ul><li><strong>Categories sidebar</strong> – List of categories on the left side (Getting Started, Dashboard, User Management, Contracts &amp; Recruitment, Client Management, Worker Management, 🌍 Partner Agencies, Finance &amp; Billing, Reports &amp; Analytics, Notifications &amp; Automation, Troubleshooting &amp; FAQ, Best Practices, Compliance &amp; Legal).</li>' +
            '<li><strong>Search bar</strong> – At the top, type keywords to search for specific tutorials.</li>' +
            '<li><strong>Tutorial list or grid view</strong> – Shows tutorials in the selected category. You can toggle between list and grid view.</li>' +
            '<li><strong>Tutorial detail view</strong> – When you click a tutorial, it opens showing full content, rating, and related tutorials.</li></ul>' +
            '<h2>Browsing by Category – Complete Guide</h2>' +
            '<ol><li><strong>Categories are listed</strong> on the left sidebar. Each category groups related tutorials together.</li>' +
            '<li><strong>Click a category</strong> to see all tutorials in that category. For example:</li>' +
            '<ul><li><strong>Getting Started</strong> – Introduction tutorials, login guide, navigation guide, quick start checklist.</li>' +
            '<li><strong>Dashboard</strong> – Dashboard overview, understanding cards, using statistics.</li>' +
            '<li><strong>User Management</strong> – Managing users, roles, permissions.</li>' +
            '<li><strong>Worker Management</strong> – Adding workers, managing documents, changing status, bulk operations.</li>' +
            '<li><strong>🌍 Partner Agencies</strong> – Overseas partners; use View to open the deployment table (status, contracts, export).</li>' +
            '<li><strong>Accounting</strong> – Financial management, invoices, bills, banking, reports.</li>' +
            '<li><strong>HR Management</strong> – Employees, attendance, salaries, advances.</li>' +
            '<li><strong>Reports</strong> – Generating reports, export options, report types.</li>' +
            '<li><strong>And more...</strong> – Each category contains multiple detailed tutorials.</li></ul>' +
            '<li><strong>Tutorials appear</strong> in list or grid format. Each tutorial shows: Title, Overview (brief description), Estimated time (how long to read), Difficulty level (beginner, intermediate, advanced), Views count (how many times viewed).</li>' +
            '<li><strong>Click a tutorial</strong> to open and read the full guide.</li></ol>' +
            '<h2>Searching for Help – Complete Guide</h2>' +
            '<ol><li><strong>Type in the search box</strong> at the top of Help Center.</li>' +
            '<li><strong>Search by keywords:</strong> Use specific keywords to find what you need. Examples:</li>' +
            '<ul><li>"How to add worker" – Finds tutorials about adding workers.</li>' +
            '<li>"Create invoice" – Finds tutorials about creating invoices.</li>' +
            '<li>"Generate report" – Finds tutorials about generating reports.</li>' +
            '<li>"Change status" – Finds tutorials about changing status.</li>' +
            '<li>"Upload document" – Finds tutorials about uploading documents.</li></ul>' +
            '<li><strong>Results appear</strong> below the search box. Results show tutorials that match your keywords.</li>' +
            '<li><strong>Click a result</strong> to read the tutorial. The tutorial opens showing full content.</li>' +
            '<li><strong>Clear search</strong> by deleting text or clicking the X icon to see all tutorials again.</li></ol>' +
            '<h2>Reading a Tutorial – Complete Guide</h2>' +
            '<ol><li><strong>Click a tutorial</strong> from the list or search results.</li>' +
            '<li><strong>Tutorial opens</strong> showing:</li>' +
            '<ul><li><strong>Title</strong> – Name of the tutorial.</li>' +
            '<li><strong>Overview</strong> – Brief description of what the tutorial covers.</li>' +
            '<li><strong>Step-by-step instructions</strong> – Detailed guide with numbered steps.</li>' +
            '<li><strong>Tips and notes</strong> – Expert tips, best practices, and important notes.</li>' +
            '<li><strong>Related tutorials</strong> – Links to other related tutorials.</li>' +
            '<li><strong>Rating</strong> – Star rating (if available) to rate the tutorial.</li></ul>' +
            '<li><strong>Follow the steps</strong> to complete your task. Tutorials provide detailed explanations of every page, table, form, section, and button.</li>' +
            '<li><strong>Mark as complete</strong> (if option available) to track your learning progress.</li>' +
            '<li><strong>Rate the tutorial</strong> (if available) to provide feedback and help others.</li></ol>' +
            '<h2>Getting Help Anytime</h2>' +
            '<ul><li><strong>Look for help icons</strong> (❓) on pages – Some pages have contextual help icons that link to relevant tutorials.</li>' +
            '<li><strong>Click help icon</strong> for contextual help – Opens the Help Center with relevant tutorials.</li>' +
            '<li><strong>Search Help Center</strong> for specific topics – Use the search function to find answers quickly.</li>' +
            '<li><strong>Browse categories</strong> to explore all available tutorials.</li></ul>' +
            '<h2>Using Help Center for Training</h2>' +
            '<p>The Help Center is perfect for training:</p>' +
            '<ul><li><strong>New users</strong> – Start with "Getting Started" category to learn basics.</li>' +
            '<li><strong>Learning specific modules</strong> – Browse category for the module you want to learn (e.g. Worker Management, Accounting).</li>' +
            '<li><strong>Step-by-step guides</strong> – Follow detailed tutorials that explain every action.</li>' +
            '<li><strong>Reference material</strong> – Return to Help Center anytime you need to remember how to do something.</li></ul>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Bookmark the Help Center for quick access – it\'s your learning resource.</li>' +
            '<li>Use search to find specific topics quickly.</li>' +
            '<li>Read tutorials completely – they provide comprehensive explanations.</li>' +
            '<li>Rate tutorials to help improve content quality.</li>' +
            '<li>Return to Help Center regularly to learn new features and best practices.</li></ul>',
            estimated_time: 20,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-9-7',
            title: 'Help Center – Detailed Step-by-Step Manual',
            overview: 'Extremely detailed, step-by-step instructions for using the Help Center: opening, browsing by category (5 steps), searching (5 steps), using contextual help (4 steps).',
            content:
            '<h2>Task 1: Opening Help Center</h2>' +
            '<h3>Step 1: Scroll Down</h3>' +
            '<p>Scroll down in the left sidebar menu.</p>' +
            '<h3>Step 2: Click Help Center</h3>' +
            '<p><strong>Click</strong> <strong>"Help &amp; Learning Center"</strong> (📚 icon). OR look for help icon (❓) on any page.</p>' +
            '<h3>Step 3: Page Loads</h3>' +
            '<p>Help Center page loads. Shows categories on left. Tutorials list in center.</p>' +
            '<h2>Task 2: Browsing Tutorials by Category – 5-Step Process</h2>' +
            '<h3>Step 1: Look at Categories</h3>' +
            '<p>Look at <strong>Categories</strong> list on left side: Getting Started, Dashboard, User Management, Worker Management, Accounting, HR Management, And more...</p>' +
            '<h3>Step 2: Click Category</h3>' +
            '<p><strong>Click</strong> on a category. Example: <strong>Click</strong> "Worker Management". Tutorials in that category appear.</p>' +
            '<h3>Step 3: Click Tutorial</h3>' +
            '<p><strong>Click</strong> on a tutorial title. Tutorial opens. Shows step-by-step instructions.</p>' +
            '<h3>Step 4: Read Tutorial</h3>' +
            '<p>Read through the tutorial. Follow the steps. Tutorial explains how to perform tasks.</p>' +
            '<h3>Step 5: When Finished</h3>' +
            '<p>When finished: <strong>Click</strong> back arrow OR close button. Return to tutorials list.</p>' +
            '<h2>Task 3: Searching for Help – 5-Step Process</h2>' +
            '<h3>Step 1: Find Search Box</h3>' +
            '<p>Find the <strong>Search box</strong> at top of Help Center.</p>' +
            '<h3>Step 2: Click Inside</h3>' +
            '<p><strong>Click</strong> inside search box.</p>' +
            '<h3>Step 3: Type Search Term</h3>' +
            '<p>Type your search term. Example: "How to add worker". Example: "Create invoice". Example: "Generate report".</p>' +
            '<h3>Step 4: Wait for Results</h3>' +
            '<p>Press <strong>Enter</strong> OR wait for results. Matching tutorials appear below.</p>' +
            '<h3>Step 5: Click Result</h3>' +
            '<p><strong>Click</strong> on a search result. Tutorial opens. Relevant information highlighted.</p>' +
            '<h2>Task 4: Using Contextual Help – 4-Step Process</h2>' +
            '<h3>Step 1: Look for Help Icon</h3>' +
            '<p>While on any page, look for <strong>help icon</strong> (❓). Usually in top right corner. OR next to form fields.</p>' +
            '<h3>Step 2: Click Help Icon</h3>' +
            '<p><strong>Click</strong> the help icon. Contextual help opens. Shows help specific to that page/field.</p>' +
            '<h3>Step 3: Read Help</h3>' +
            '<p>Read the help information.</p>' +
            '<h3>Step 4: Close</h3>' +
            '<p><strong>Click</strong> outside or close button. Help closes.</p>' +
            '<h2>Expert Tips</h2>' +
            '<ul><li>Use Help Center regularly for learning.</li><li>Search for specific topics.</li><li>Use contextual help when available.</li></ul>',
            estimated_time: 15,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        },
        {
            id: 'builtin-12-2',
            title: 'Congratulations – You\'ve Completed Training!',
            overview: 'Congratulations message and next steps after completing the training guide.',
            content:
            '<h2>🎉 Congratulations!</h2>' +
            '<p>You have completed the Ratib Program User Training Guide! You now know how to:</p>' +
            '<ul><li>✅ Navigate the system</li>' +
            '<li>✅ Manage workers, agents, and subagents</li>' +
            '<li>✅ Use the accounting system</li>' +
            '<li>✅ Handle HR tasks</li>' +
            '<li>✅ Generate reports</li>' +
            '<li>✅ Get help when needed</li>' +
            '<li>✅ Use all major features of the program</li></ul>' +
            '<h2>Next Steps</h2>' +
            '<p>Now that you have completed the training guide, here are your next steps:</p>' +
            '<ol><li><strong>Practice</strong> – Try performing common tasks in the system. Practice makes perfect!</li>' +
            '<ul><li>Add a test worker</li>' +
            '<li>Create a test invoice</li>' +
            '<li>Generate a test report</li>' +
            '<li>Record test attendance</li></ul>' +
            '<li><strong>Explore</strong> – Discover features specific to your role. Different roles have access to different features.</li>' +
            '<li><strong>Ask Questions</strong> – Don\'t hesitate to ask for help. Your administrator or colleagues can assist you.</li>' +
            '<li><strong>Use Help Center</strong> – Refer to tutorials in the Help &amp; Learning Center when needed. The Help Center is always available.</li></ol>' +
            '<h2>Remember</h2>' +
            '<p>As you continue using the Ratib program, remember:</p>' +
            '<ul><li><strong>Take your time</strong> – Don\'t rush, accuracy is important. It\'s better to do things correctly than quickly.</li>' +
            '<li><strong>Save frequently</strong> – Don\'t lose your work. Click Save regularly, especially when entering large amounts of data.</li>' +
            '<li><strong>Ask for help</strong> – It\'s better to ask than make mistakes. Use the Help Center or contact your administrator.</li>' +
            '<li><strong>Use the Help Center</strong> – It\'s there to help you succeed. Bookmark it and refer to it often.</li>' +
            '<li><strong>Practice regularly</strong> – The more you use the system, the more comfortable you\'ll become.</li></ul>' +
            '<h2>Training Checklist Completion</h2>' +
            '<p>Review your training checklist:</p>' +
            '<ul><li>☐ I can log in successfully</li>' +
            '<li>☐ I understand the Dashboard</li>' +
            '<li>☐ I can navigate the menu</li>' +
            '<li>☐ I can add a new worker</li>' +
            '<li>☐ I can edit worker information</li>' +
            '<li>☐ I can upload documents</li>' +
            '<li>☐ I can change worker status</li>' +
            '<li>☐ I can add an agent</li>' +
            '<li>☐ I can add a subagent</li>' +
            '<li>☐ I can create an invoice</li>' +
            '<li>☐ I can create a bill</li>' +
            '<li>☐ I can generate a report</li>' +
            '<li>☐ I can record attendance</li>' +
            '<li>☐ I can add a case</li>' +
            '<li>☐ I can add a contact</li>' +
            '<li>☐ I can use the Help Center</li>' +
            '<li>☐ I know how to get help</li></ul>' +
            '<p>Check off items as you complete them. When all items are checked, you\'re fully trained!</p>' +
            '<h2>Happy Using Ratib Program! 🚀</h2>' +
            '<p>You are now ready to use the Ratib program effectively. Remember:</p>' +
            '<ul><li>The Help Center is always available for reference</li>' +
            '<li>Your administrator can help with permissions and configuration</li>' +
            '<li>Practice regularly to build confidence</li>' +
            '<li>Don\'t hesitate to ask questions</li></ul>' +
            '<p><strong>For technical support or questions, contact your system administrator.</strong></p>' +
            '<hr>' +
            '<p><em>Document Version: 1.0.0<br>Last Updated: February 2026<br>For: End Users</em></p>',
            estimated_time: 10,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-02-10'
        }
    ],
    13: [
        {
            id: 'builtin-13-0',
            title: '🌍 Partner Agencies – full guide',
            overview: 'Manage overseas partner agencies, open the deployment table with View, and update worker placement status.',
            content:
            '<h2>What is 🌍 Partner Agencies?</h2>' +
            '<p>This section lists <strong>overseas partner offices</strong> (recruitment partners) your agency works with. Each row shows contact details, country, and how many workers have been <strong>sent</strong> to that partner.</p>' +
            '<h2>Open the deployment table (View)</h2>' +
            '<ol><li>Go to <strong>🌍 Partner Agencies</strong> in the left menu (you need permission to view workers).</li>' +
            '<li>In the <strong>Workers Sent</strong> column, click <strong>View</strong> on the row for the agency you want.</li>' +
            '<li>A modal opens titled <strong>Deployments — [agency name]</strong> with one table: worker name, passport, country, agency, <strong>status</strong> (colored dropdown), contract &amp; timeline (days on assignment / days left), job title, salary, and actions.</li></ol>' +
            '<h2>What you can do in the table</h2>' +
            '<ul><li><strong>Status</strong> – Change deployment status (processing, deployed, returned, issue, transferred). The system saves when you pick a new value.</li>' +
            '<li><strong>Profile</strong> – Opens the worker record in read-only mode.</li>' +
            '<li><strong>Delete</strong> – Removes the deployment link for that worker to this partner (confirm when asked).</li>' +
            '<li><strong>Export CSV</strong> – Downloads the filtered list from the modal toolbar.</li>' +
            '<li><strong>Search / filters</strong> – Narrow rows by name, passport, country, job, or deployment status.</li></ul>' +
            '<h2>Tips</h2>' +
            '<ul><li>Keep partner agency records <strong>active</strong> only for offices you still work with.</li>' +
            '<li>Use contract dates so the timeline shows how long someone has been on assignment and when the contract ends.</li>' +
            '<li>If you do not see Partner Agencies, ask an administrator to grant <strong>view workers</strong> (or equivalent) access.</li></ul>',
            estimated_time: 12,
            difficulty_level: 'beginner',
            views_count: 0,
            updated_at: '2026-04-15'
        }
    ]
};
