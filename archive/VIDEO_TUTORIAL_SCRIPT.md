# Ratib Program - Complete Video Tutorial Script
## Comprehensive Guide for Creating Video Demonstrations

---

## Table of Contents

1. [Video 1: Introduction & Login](#video-1-introduction--login)
2. [Video 2: Dashboard Overview](#video-2-dashboard-overview)
3. [Video 3: User Profile Management](#video-3-user-profile-management)
4. [Video 4: Agents Management - Part 1](#video-4-agents-management---part-1)
5. [Video 5: Agents Management - Part 2](#video-5-agents-management---part-2)
6. [Video 6: Subagents Management](#video-6-subagents-management)
7. [Video 7: Workers Management - Part 1](#video-7-workers-management---part-1)
8. [Video 8: Workers Management - Part 2](#video-8-workers-management---part-2)
9. [Video 9: Workers Management - Part 3](#video-9-workers-management---part-3)
10. [Video 10: Cases Management](#video-10-cases-management)
11. [Video 11: Accounting System - Part 1](#video-11-accounting-system---part-1)
12. [Video 12: Accounting System - Part 2](#video-12-accounting-system---part-2)
13. [Video 13: Accounting System - Part 3](#video-13-accounting-system---part-3)
14. [Video 14: HR Management - Part 1](#video-14-hr-management---part-1)
15. [Video 15: HR Management - Part 2](#video-15-hr-management---part-2)
16. [Video 16: Contacts & Communications](#video-16-contacts--communications)
17. [Video 17: Reports System](#video-17-reports-system)
18. [Video 18: Notifications](#video-18-notifications)
19. [Video 19: System Settings - Part 1](#video-19-system-settings---part-1)
20. [Video 20: System Settings - Part 2](#video-20-system-settings---part-2)
21. [Video 21: Permissions & User Management](#video-21-permissions--user-management)

---

## Video 1: Introduction & Login
**Duration: 3-4 minutes**

### Opening (30 seconds)
"Welcome to the Ratib Program tutorial series. Ratib Program is a comprehensive business management system designed for managing workers, agents, subagents, accounting, HR, cases, and much more. In this first video, we'll cover how to access and log in to the system."

### What is Ratib Program? (1 minute)
"Ratib Program is a complete business management solution that includes:

- **Workers Management** - Complete lifecycle management for workers
- **Agents & Subagents** - Multi-level agent relationship management
- **Professional Accounting** - Double-entry bookkeeping system
- **HR Management** - Employee management, attendance, salaries
- **Cases Management** - Track and manage cases
- **Contacts & Communications** - Manage contacts and messages
- **Reports** - Comprehensive reporting system
- **Visa Management** - Visa processing and tracking

The system features role-based access control, biometric authentication, document management, and multi-currency support."

### Accessing the System (30 seconds)
"To access Ratib Program, navigate to your system URL. The default path is typically `http://localhost/ratibprogram/` for local installations. When you first visit, you'll be redirected to the login page if you're not already authenticated."

### Login Page Overview (1 minute)
"The login page features:

- **Animated background** with system branding
- **Two login methods**: Username & Password, or Fingerprint authentication
- **Dark mode toggle** in the top right corner
- **Forgot Password link** for password recovery

Let me show you the login interface. [Screen recording showing login page]"

### Username & Password Login (1 minute)
"To log in with username and password:

1. **Select 'Username & Password'** from the login method dropdown
2. **Enter your username** in the username field
3. **Enter your password** in the password field
4. **Click the Login button**

The system will verify your credentials and check your account status. If your account is active, you'll be redirected to the dashboard. If there's an error, you'll see an error message displayed on the page.

**Important**: Only active accounts can log in. Inactive accounts will see an error message."

### Fingerprint Login (30 seconds)
"For users who have registered their fingerprint:

1. **Select 'Fingerprint'** from the login method dropdown
2. **Place your finger on the scanner** when prompted
3. The system will authenticate using your biometric data

Note: Fingerprint registration must be done first through your profile settings, which we'll cover in a later video."

### Security Features (30 seconds)
"The login system includes several security features:

- **Password hashing** - Passwords are securely hashed using PHP's password_hash function
- **Session management** - Secure session handling
- **CSRF protection** - Cross-site request forgery protection
- **Account status checking** - Only active accounts can log in
- **Activity logging** - All login attempts are logged"

### Closing (15 seconds)
"That's it for the login process. In the next video, we'll explore the dashboard and understand the main navigation system. Thank you for watching!"

---

## Video 2: Dashboard Overview
**Duration: 4-5 minutes**

### Opening (30 seconds)
"Welcome back! In this video, we'll explore the dashboard - your central command center in Ratib Program. The dashboard provides an overview of all system modules and key statistics."

### Dashboard Layout (1 minute)
"After logging in, you're automatically redirected to the dashboard. The dashboard consists of:

- **Top Navigation Bar** - Contains links to all major modules
- **Welcome Message** - Dynamic greeting that flashes 'Welcome Back'
- **System Grid** - Cards showing statistics for each module
- **Recent Activities Section** - Shows the last 5 system activities

Let me show you the dashboard layout. [Screen recording showing dashboard]"

### Navigation Bar (1 minute)
"The top navigation bar provides quick access to all modules:

- **Dashboard** - Return to home
- **Agent** - Manage agents
- **SubAgent** - Manage subagents
- **Workers** - Manage workers
- **Cases** - Manage cases
- **Accounting** - Financial management
- **HR** - Human resources
- **Reports** - View reports
- **Contact** - Contact management
- **Communications** - Messages
- **Notifications** - System alerts
- **System Settings** - Configuration
- **Profile** - Your user profile
- **Logout** - Sign out

**Important**: Menu items are hidden based on your permissions. If you don't see a module, you don't have access to it."

### System Cards Overview (1.5 minutes)
"Each module has a card showing key statistics:

1. **Agents Card** - Shows total, active, and inactive agents
2. **SubAgents Card** - Shows total, active, and inactive subagents
3. **Workers Card** - Shows total, active, and inactive workers
4. **Cases Card** - Shows total cases, open cases, urgent cases, and resolved cases
5. **HR System Card** - Shows total employees, active, inactive, and terminated
6. **Accounting Card** - Shows invoices, bills, transactions, and customers
7. **Reports Card** - Shows total reports, today's reports, and this month's reports
8. **Contact Card** - Shows total, active, and inactive contacts
9. **Settings Card** - Shows total and active settings
10. **Visa Management Card** - Shows visa types statistics
11. **Notifications Card** - Shows total and new notifications
12. **My Profile Card** - Shows your username, role, and last login time

**Clicking any card** takes you directly to that module's main page."

### Recent Activities (1 minute)
"The Recent Activities section displays the last 5 activities performed in the system, including:

- **Activity description** - What was done
- **User** - Who performed the action
- **Timestamp** - When it happened

This provides a quick overview of recent system activity and helps track changes."

### Dashboard Features (30 seconds)
"Key dashboard features:

- **Real-time statistics** - All numbers update automatically
- **Permission-based visibility** - Cards only show if you have access
- **Quick navigation** - Click cards to jump to modules
- **Responsive design** - Works on desktop and mobile devices"

### Closing (15 seconds)
"That covers the dashboard overview. In the next video, we'll explore your user profile and how to manage your account settings. See you then!"

---

## Video 3: User Profile Management
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore the User Profile page where you can manage your personal information, change your password, register biometric authentication, and view your activity history."

### Accessing Your Profile (30 seconds)
"To access your profile:

1. **Click 'My Profile'** from the dashboard cards, OR
2. **Click your username** in the navigation bar (if available), OR
3. **Navigate directly** to the profile page

The profile page shows all your account information and settings."

### Profile Information Section (1.5 minutes)
"The profile page displays:

- **User ID** - Your unique system identifier
- **Username** - Your login username (cannot be changed)
- **Email** - Your email address
- **Phone** - Your phone number
- **Country & City** - Your location information
- **Role** - Your assigned role and description
- **Status** - Account status (Active/Inactive)
- **Last Login** - When you last logged in
- **Account Created** - When your account was created

**To edit your information:**

1. Click the **'Edit Profile'** button
2. A modal form will appear with editable fields
3. Update the information you want to change
4. Click **'Save Changes'** to update

**Note**: Some fields like username may be read-only depending on your permissions."

### Password Management (1 minute)
"To change your password:

1. Click **'Change Password'** button
2. Enter your **current password**
3. Enter your **new password** (must meet security requirements)
4. **Confirm the new password**
5. Click **'Update Password'**

**Password Requirements**:
- Minimum length (typically 8 characters)
- Should include letters and numbers
- Avoid common passwords

The system will verify your current password before allowing the change."

### Biometric Authentication Setup (1.5 minutes)
"Ratib Program supports two types of biometric authentication:

**1. Fingerprint Authentication:**

1. Click **'Register Fingerprint'** button
2. Place your finger on the scanner when prompted
3. Lift and place again for verification
4. The system will save your fingerprint template

**2. WebAuthn (Face/Biometric):**

1. Click **'Register WebAuthn'** button
2. Follow your browser's prompts to register your device
3. Use your device's biometric authentication (face, fingerprint, etc.)
4. The system will save your credential

**To use biometric login:**
- Go to the login page
- Select 'Fingerprint' from the login method dropdown
- Authenticate using your registered biometric

**To remove biometric authentication:**
- Click **'Remove Fingerprint'** or **'Remove WebAuthn'**
- Confirm the removal"

### Activity History (30 seconds)
"The profile page shows your **Activity History** - a log of all actions you've performed in the system, including:

- Module accessed
- Action performed (view, add, edit, delete)
- Timestamp
- Details

This helps you track your own activity and provides an audit trail."

### Profile Settings (30 seconds)
"Additional profile features include:

- **Biometric Authentication** - Fingerprint and WebAuthn registration (covered above)
- **Activity History** - View your complete activity log
- **Password Management** - Change your password securely
- **Account Information** - Update your contact details and location

**Note**: Theme settings (dark mode) are available on the login page and persist via browser storage. User-specific language preferences and notification settings are not currently available in the profile page."

### Closing (15 seconds)
"That covers user profile management. In the next video, we'll start exploring the Agents module - how to add, edit, and manage agents. See you then!"

---

## Video 4: Agents Management - Part 1
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore the Agents Management module. Agents are key entities in the system, representing your primary business partners or representatives."

### Accessing Agents Module (30 seconds)
"To access the Agents module:

1. **Click 'Agents'** from the dashboard card, OR
2. **Click 'Agent'** from the top navigation bar

You'll see the Agents Management page with a data table showing all agents."

### Agents Page Layout (1 minute)
"The Agents page consists of:

- **Statistics Cards** - Total, Active, and Inactive agent counts
- **Activity History Card** - Shows agent-related activity count
- **Search Bar** - Search agents by ID, name, email, etc.
- **Status Filter** - Filter by Active/Inactive status
- **Action Buttons** - Add New, Activate, Deactivate, Delete
- **Data Table** - Lists all agents with details
- **Pagination** - Navigate through multiple pages of agents

Let me show you the interface. [Screen recording]"

### Understanding Agent Data (1 minute)
"Each agent record contains:

- **Agent ID** - Unique identifier (formatted like AG-00001)
- **Name** - Full name of the agent
- **Email** - Contact email
- **Phone** - Contact phone number
- **Country & City** - Location information
- **Status** - Active or Inactive
- **Created Date** - When the agent was added
- **Last Updated** - Last modification date

The table is sortable by clicking column headers and searchable using the search bar."

### Adding a New Agent (1.5 minutes)
"To add a new agent:

1. **Click the 'Add New' button** (requires 'add_agent' permission)
2. A modal form will appear with fields:
   - **Name** (required)
   - **Email** (required, must be unique)
   - **Phone** (optional)
   - **Country** (dropdown selection)
   - **City** (dropdown, filtered by country)
   - **Status** (Active/Inactive, default: Active)
   - **Additional fields** as configured

3. **Fill in all required fields**
4. **Click 'Save'** to create the agent

**Important Notes**:
- Agent ID is automatically generated
- Email must be unique across all agents
- The system validates all inputs before saving
- Activity is logged automatically"

### Viewing Agent Details (30 seconds)
"To view full agent details:

1. **Click the 'View' button** (eye icon) for any agent
2. A modal will show complete agent information including:
   - All contact details
   - Status information
   - Creation and update timestamps
   - Linked records (subagents, workers, etc.)

Click 'Close' to return to the table."

### Closing (15 seconds)
"In the next video, we'll cover editing agents, bulk operations, and linking agents to other entities. Stay tuned!"

---

## Video 5: Agents Management - Part 2
**Duration: 4-5 minutes**

### Opening (30 seconds)
"Welcome back! In this video, we'll continue with Agents Management, covering editing, bulk operations, status management, and linking agents to other entities."

### Editing an Agent (1.5 minutes)
"To edit an agent:

1. **Click the 'Edit' button** (pencil icon) for the agent you want to modify
2. The edit modal will appear with current values pre-filled
3. **Modify the fields** you want to change:
   - Name, Email, Phone
   - Country and City
   - Status (Active/Inactive)
4. **Click 'Update'** to save changes

**Important**:
- Email uniqueness is validated on update
- Changes are logged in activity history
- Linked records may be affected by status changes
- You need 'edit_agent' permission to edit"

### Changing Agent Status (1 minute)
"Agents can have two statuses:

- **Active** - Agent is currently active and can be used
- **Inactive** - Agent is temporarily disabled

**To change status individually:**

1. Click the **status badge** in the table, OR
2. Use the **'Edit' button** and change status in the form

**To change status in bulk:**

1. **Select multiple agents** using checkboxes
2. Click **'Activate'** or **'Deactivate'** button
3. Confirm the action in the dialog
4. All selected agents will be updated

**Note**: Inactive agents won't appear in dropdowns for linking to other entities."

### Bulk Operations (1.5 minutes)
"The system supports bulk operations for efficiency:

**1. Bulk Activate:**
- Select multiple agents
- Click 'Activate' button
- All selected agents become Active

**2. Bulk Deactivate:**
- Select multiple agents
- Click 'Deactivate' button
- All selected agents become Inactive

**3. Bulk Delete:**
- Select multiple agents
- Click 'Delete' button
- Confirm deletion
- **Warning**: Deletion may affect linked records

**Selection Methods**:
- Click individual checkboxes
- Use 'Select All' on current page
- Use 'Select All' across all pages (if available)

**Important**: Bulk operations require appropriate permissions and may have restrictions based on linked records."

### Searching and Filtering (1 minute)
"To find specific agents:

**Search Bar:**
- Type any text (ID, name, email, phone)
- Results filter in real-time
- Searches across multiple fields

**Status Filter:**
- Select 'All Status', 'Active', or 'Inactive'
- Table updates immediately

**Combined Filters:**
- Use search + status filter together
- Pagination resets when filters change

**Clear Filters:**
- Clear search bar to show all
- Reset status to 'All Status'"

### Linking Agents to Other Entities (30 seconds)
"Agents can be linked to:

- **Subagents** - Agents can have multiple subagents
- **Workers** - Workers can be assigned to agents
- **Accounting Transactions** - Financial records
- **Cases** - Case assignments

When creating subagents or workers, you'll select an agent from a dropdown. Only active agents appear in these dropdowns."

### Activity History (30 seconds)
"Click the **'Activity History'** card to view:

- All agent-related activities
- Who made changes
- When changes occurred
- What was changed

This provides a complete audit trail for agent management."

### Closing (15 seconds)
"That covers Agents Management. In the next video, we'll explore Subagents Management - agents that work under main agents. See you then!"

---

## Video 6: Subagents Management
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore Subagents Management. Subagents are secondary agents that work under main agents, creating a hierarchical structure in your business."

### Understanding Subagents (1 minute)
"Subagents differ from agents in that they:

- **Belong to a parent Agent** - Each subagent must be linked to an agent
- **Have their own records** - Independent contact information and status
- **Can be linked to workers** - Workers can be assigned to subagents
- **Tracked separately** - Have their own statistics and history

This structure allows you to manage multi-level agent relationships."

### Accessing Subagents Module (30 seconds)
"To access Subagents:

1. **Click 'SubAgents'** from the dashboard card, OR
2. **Click 'SubAgent'** from the navigation bar

You'll see the Subagents Management page."

### Subagents Page Layout (1 minute)
"The Subagents page includes:

- **Status Cards** - Total, Active, and Inactive counts
- **Search & Filter** - Find subagents quickly
- **Add New Button** - Create new subagents
- **Bulk Actions** - Activate, Deactivate, Delete multiple
- **Data Table** - List of all subagents
- **Pagination** - Navigate through records

The layout is similar to Agents but includes an 'Agent' column showing the parent agent."

### Adding a New Subagent (1.5 minutes)
"To add a new subagent:

1. **Click 'Add New'** button
2. Fill in the form:
   - **Agent** (required) - Select parent agent from dropdown
   - **Name** (required) - Subagent's full name
   - **Email** (required, unique)
   - **Phone** (optional)
   - **Country & City** (location)
   - **Status** (Active/Inactive)

3. **Click 'Save'** to create

**Important**:
- Subagent ID is auto-generated (format: SUB-00001)
- Must select a parent Agent
- Email must be unique
- Only active agents appear in the Agent dropdown"

### Editing Subagents (1 minute)
"To edit a subagent:

1. **Click 'Edit'** button for the subagent
2. Modify fields in the edit modal
3. **Note**: You can change the parent Agent if needed
4. **Click 'Update'** to save

**Status Changes**:
- Change status individually or in bulk
- Inactive subagents won't appear in worker assignment dropdowns
- Status changes are logged"

### Bulk Operations (30 seconds)
"Subagents support the same bulk operations as agents:

- **Bulk Activate** - Activate multiple subagents
- **Bulk Deactivate** - Deactivate multiple
- **Bulk Delete** - Delete multiple (with confirmation)

Select multiple subagents using checkboxes, then choose the action."

### Linking Subagents (30 seconds)
"Subagents can be linked to:

- **Workers** - Assign workers to subagents
- **Accounting** - Financial transactions
- **Cases** - Case management

When creating workers, you can select both Agent and Subagent, showing the relationship hierarchy."

### Closing (15 seconds)
"That covers Subagents Management. Next, we'll dive into Workers Management - the core module for managing your workforce. See you in the next video!"

---

## Video 7: Workers Management - Part 1
**Duration: 4-5 minutes**

### Opening (30 seconds)
"Welcome to Workers Management - one of the most comprehensive modules in Ratib Program. In this video, we'll cover the basics: accessing workers, understanding the interface, and viewing worker records."

### Understanding Workers Module (1 minute)
"The Workers module manages:

- **Worker Profiles** - Complete personal and professional information
- **Document Management** - Passport, visa, medical, police certificates
- **Status Tracking** - Active, Inactive, Pending, Suspended, Deleted
- **Musaned Integration** - Link to Musaned system for worker status
- **Agent/Subagent Assignment** - Link workers to agents and subagents
- **History Tracking** - Complete audit trail

This is a critical module for managing your workforce."

### Accessing Workers Module (30 seconds)
"To access Workers:

1. **Click 'Workers'** from the dashboard card, OR
2. **Click 'Workers'** from the navigation bar

You'll see the Workers Management page with comprehensive statistics and a data table."

### Workers Page Layout (1.5 minutes)
"The Workers page is feature-rich:

**Statistics Cards:**
- **Total Workers** - All workers (excluding deleted)
- **Active Workers** - Currently active
- **Inactive Workers** - Temporarily inactive
- **Pending Workers** - Awaiting processing
- **Suspended Workers** - Suspended from work
- **Musaned Status** - Workers linked to Musaned system

**Controls:**
- **Search Bar** - Search by ID, name, passport, etc.
- **Status Filter** - Filter by worker status
- **Musaned Filter** - Filter by Musaned status
- **Add New Button** - Create new worker
- **Bulk Actions** - Multiple operations

**Data Table:**
- Shows worker ID, name, passport, status, agent, subagent
- Sortable columns
- Action buttons (View, Edit, Delete)"

### Understanding Worker Statuses (1 minute)
"Workers can have these statuses:

- **Active** - Worker is currently active and working
- **Inactive** - Worker is temporarily inactive
- **Pending** - Worker record is being processed
- **Suspended** - Worker is suspended from work
- **Deleted** - Worker is soft-deleted (hidden from normal view)

**Status Indicators:**
- Color-coded badges in the table
- Green = Active
- Yellow = Pending
- Red = Suspended
- Gray = Inactive"

### Viewing Worker Details (1 minute)
"To view complete worker information:

1. **Click 'View'** button (eye icon) for any worker
2. A detailed modal shows:
   - **Personal Information** - Name, passport, nationality, etc.
   - **Contact Details** - Phone, email, address
   - **Assignment** - Linked Agent and Subagent
   - **Documents** - All uploaded documents with status
   - **Musaned Information** - Musaned status and details
   - **History** - Activity log

The view is read-only. Use 'Edit' to make changes."

### Closing (15 seconds)
"In the next video, we'll cover adding new workers and filling in all the required information. Stay tuned!"

---

## Video 8: Workers Management - Part 2
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll cover adding new workers and managing worker information - the core functionality of the Workers module."

### Adding a New Worker (2 minutes)
"To add a new worker:

1. **Click 'Add New'** button
2. A comprehensive form appears with multiple sections:

**Section 1: Personal Information**
- **Worker ID** - Auto-generated (format: WRK-00001)
- **Full Name** (required) - Arabic and English names
- **Passport Number** (required, unique)
- **Nationality** - Select from dropdown
- **Date of Birth**
- **Gender** - Male/Female
- **Marital Status**

**Section 2: Contact Information**
- **Phone Number**
- **Email**
- **Address** - Full address details
- **Country & City**

**Section 3: Assignment**
- **Agent** - Select parent agent (required)
- **Subagent** - Select subagent (optional, filtered by agent)

**Section 4: Status**
- **Status** - Active, Inactive, Pending (default: Pending)

3. **Fill in all required fields** (marked with *)
4. **Click 'Save'** to create the worker

**Validation:**
- Passport number must be unique
- All required fields must be filled
- Agent must be selected"

### Editing Worker Information (1.5 minutes)
"To edit a worker:

1. **Click 'Edit'** button for the worker
2. The edit form appears with current values
3. **Modify any fields** you need to change:
   - Personal information
   - Contact details
   - Agent/Subagent assignment
   - Status

4. **Click 'Update'** to save changes

**Important Notes:**
- Passport number uniqueness is checked on update
- Changing Agent will filter Subagent options
- Status changes are logged
- Document management is separate (covered next)"

### Changing Worker Status (1 minute)
"To change worker status:

**Individual Status Change:**
1. Click the **status badge** in the table, OR
2. Use **'Edit'** and change status in the form

**Bulk Status Changes:**
1. **Select multiple workers** using checkboxes
2. Choose action:
   - **'Bulk Activate'** - Set to Active
   - **'Bulk Pending'** - Set to Pending
   - **'Bulk Suspend'** - Set to Suspended
   - **'Bulk Delete'** - Soft delete workers

3. **Confirm the action**

**Status Impact:**
- Active workers appear in reports and assignments
- Suspended workers are excluded from active operations
- Deleted workers are hidden but not permanently removed"

### Closing (15 seconds)
"In the next video, we'll cover document management and Musaned integration - critical features for worker management. See you then!"

---

## Video 9: Workers Management - Part 3
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this final Workers Management video, we'll cover document management, Musaned integration, and advanced features."

### Document Management (2 minutes)
"Workers can have multiple document types:

**Document Types:**
- **Passport** - Passport copy
- **Visa** - Visa documents
- **Medical Certificate** - Health clearance
- **Police Certificate** - Background check
- **Identity Card** - National ID
- **Ticket** - Travel tickets
- **Other Documents** - Additional files

**To Upload Documents:**

1. **Click 'View'** or **'Edit'** for a worker
2. Navigate to **'Documents'** tab/section
3. **Click 'Upload Document'**
4. Select document type from dropdown
5. **Choose file** (JPG, PDF, PNG supported)
6. **Add description** (optional)
7. **Click 'Upload'**

**Document Status:**
- **Pending** - Awaiting review
- **Approved** - Document verified
- **Rejected** - Document needs correction
- **Expired** - Document has expired

**To Update Document Status:**
1. Click on a document in the list
2. Change status dropdown
3. Add notes if needed
4. Save changes

**Bulk Document Operations:**
- Select multiple documents
- Update status for all selected
- Useful for batch approvals"

### Musaned Integration (1.5 minutes)
"Musaned is the Saudi Ministry of Human Resources system. Workers can be linked to Musaned records.

**To Link Worker to Musaned:**

1. **Click 'View'** or **'Edit'** for a worker
2. Navigate to **'Musaned'** section
3. **Enter Musaned ID** or search
4. **Click 'Link to Musaned'**
5. System will fetch Musaned data
6. **Verify information** matches
7. **Save the link**

**Musaned Status:**
- **Not Linked** - No Musaned record
- **Linked** - Connected to Musaned
- **Synced** - Data synchronized
- **Error** - Sync failed

**Updating Musaned Status:**
- System can auto-update from Musaned API
- Manual refresh available
- Status changes are logged"

### Searching and Filtering Workers (1 minute)
"Advanced search options:

**Search Bar:**
- Search by Worker ID, Name, Passport Number
- Real-time filtering
- Searches across multiple fields

**Status Filter:**
- Filter by Active, Inactive, Pending, Suspended
- 'All' shows all non-deleted workers

**Musaned Filter:**
- Filter by Musaned status
- Find linked/unlinked workers

**Combined Filters:**
- Use multiple filters together
- Reset filters to show all"

### Worker History (30 seconds)
"Each worker has a complete history:

- **View History** button in worker details
- Shows all changes made
- Who made changes
- When changes occurred
- What was changed

Provides full audit trail for compliance."

### Closing (15 seconds)
"That completes Workers Management. In the next video, we'll explore Cases Management - tracking and managing cases. See you then!"

---

## Video 10: Cases Management
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore Cases Management - a system for tracking and managing cases, issues, and tasks throughout your organization."

### Understanding Cases (1 minute)
"Cases in Ratib Program represent:

- **Customer Issues** - Problems or requests from clients
- **Internal Tasks** - Tasks assigned to team members
- **Follow-ups** - Items requiring attention
- **Projects** - Project tracking
- **Complaints** - Customer complaints

Each case has a status, priority, assignee, and detailed information."

### Accessing Cases Module (30 seconds)
"To access Cases:

1. **Click 'Cases'** from the dashboard card, OR
2. **Click 'Cases'** from the navigation bar

You'll see the Cases Management page."

### Cases Page Layout (1 minute)
"The Cases page includes:

- **Statistics Cards** - Total, Open, In Progress, Pending, Resolved, Closed, Urgent
- **Filters** - Status, Priority, Assignee filters
- **Search Bar** - Search cases by ID, title, description
- **Add New Button** - Create new case
- **Data Table** - List of all cases
- **Pagination** - Navigate through cases

Cases are displayed with color-coded status indicators."

### Understanding Case Statuses (1 minute)
"Cases can have these statuses:

- **Open** - Case is newly created
- **In Progress** - Case is being worked on
- **Pending** - Awaiting information or action
- **Resolved** - Case is resolved but not closed
- **Closed** - Case is completed and archived
- **Urgent** - High priority cases (separate priority level)

**Priority Levels:**
- **Low** - Normal priority
- **Medium** - Moderate priority
- **High** - Important priority
- **Urgent** - Critical priority"

### Adding a New Case (1.5 minutes)
"To add a new case:

1. **Click 'Add New'** button
2. Fill in the case form:

**Required Fields:**
- **Title** - Case title/summary
- **Description** - Detailed description
- **Status** - Initial status (usually 'Open')
- **Priority** - Low, Medium, High, Urgent

**Optional Fields:**
- **Assignee** - Assign to a user
- **Due Date** - Target completion date
- **Category** - Case category/type
- **Related Entity** - Link to Agent, Worker, etc.
- **Tags** - For organization

3. **Click 'Save'** to create the case

**Case ID** is auto-generated (format: CASE-00001)"

### Managing Cases (1 minute)
"**To Edit a Case:**
1. Click 'Edit' button
2. Update information
3. Change status as case progresses
4. Save changes

**To Change Status:**
- Click status badge, OR
- Edit case and change status
- Status changes are logged

**To Assign Cases:**
- Edit case and select assignee
- Assigned cases appear in assignee's dashboard
- Notifications sent on assignment"

### Case Activities (30 seconds)
"Each case tracks activities:

- **Comments** - Add comments/notes
- **Status Changes** - Track status updates
- **File Attachments** - Attach documents
- **Timeline** - View case history

Click 'View' to see full case details and activities."

### Closing (15 seconds)
"That covers Cases Management. Next, we'll explore the Accounting System - a comprehensive financial management module. See you in the next video!"

---

## Video 11: Accounting System - Part 1
**Duration: 4-5 minutes**

### Opening (30 seconds)
"Welcome to the Accounting System - a professional double-entry bookkeeping system. In this video, we'll cover the dashboard, overview, and basic concepts."

### Understanding the Accounting System (1 minute)
"The Accounting System provides:

- **Double-Entry Bookkeeping** - Every transaction affects two accounts
- **Chart of Accounts** - Organized account structure
- **Journal Entries** - Record all transactions
- **Banking** - Bank account management
- **Invoices & Bills** - Receivables and payables
- **Financial Reports** - Comprehensive statements
- **Multi-Currency** - Support for SAR, USD, EUR, GBP, JOD
- **Entity Integration** - Link to Agents, Subagents, Workers, HR

This is a complete accounting solution."

### Accessing Accounting Module (30 seconds)
"To access Accounting:

1. **Click 'Accounting'** from the dashboard card, OR
2. **Click 'Accounting'** from the navigation bar

You'll see the Accounting Dashboard with financial overview."

### Accounting Dashboard (1.5 minutes)
"The Accounting Dashboard shows:

**Financial Overview Cards:**
- **Total Revenue** - All income
- **Total Expenses** - All costs
- **Net Profit** - Revenue minus expenses
- **Cash Balance** - Available cash
- **Accounts Receivable** - Money owed to you
- **Accounts Payable** - Money you owe

**Charts & Graphs:**
- Revenue trends
- Expense breakdowns
- Profit/loss visualization
- Cash flow charts

**Quick Actions:**
- Create Journal Entry
- Add Bank Transaction
- Create Invoice
- Create Bill
- View Reports

All numbers update in real-time."

### Accounting Navigation (1 minute)
"The Accounting module uses tabs for navigation:

**Main Tabs:**
- **Dashboard** - Financial overview
- **Journal Entries** - All transactions
- **Chart of Accounts** - Account master list
- **Banking & Cash** - Bank accounts
- **Vouchers** - Payment/receipt vouchers
- **Receivables** - Customer invoices
- **Payables** - Vendor bills
- **Reports** - Financial reports
- **Settings** - Accounting configuration

Click any tab to navigate to that section."

### Understanding Double-Entry (1 minute)
"Double-entry bookkeeping means:

- **Every transaction has two sides:**
  - **Debit** (left side)
  - **Credit** (right side)
  
- **Debits must equal Credits** - System enforces this

**Example:**
- Paying cash for expenses:
  - Debit: Expense Account (increases expense)
  - Credit: Cash Account (decreases cash)

The system validates that debits equal credits before saving."

### Closing (15 seconds)
"In the next video, we'll cover Chart of Accounts and Journal Entries - the foundation of accounting. See you then!"

---

## Video 12: Accounting System - Part 2
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll cover Chart of Accounts and Journal Entries - the core of the accounting system."

### Chart of Accounts (2 minutes)
"The Chart of Accounts is your master list of all accounts.

**Account Types:**
- **Assets** - What you own (Cash, Bank, Inventory)
- **Liabilities** - What you owe (Loans, Payables)
- **Equity** - Owner's equity (Capital, Retained Earnings)
- **Income** - Revenue sources (Sales, Services)
- **Expenses** - Costs (Salaries, Rent, Utilities)

**To View Chart of Accounts:**
1. Click **'Chart of Accounts'** tab
2. See hierarchical account structure
3. View account balances
4. Filter by account type

**To Add an Account:**
1. Click **'Add Account'** button
2. Fill in:
   - **Account Name** (required)
   - **Account Type** (required)
   - **Parent Account** (for hierarchy)
   - **Account Code** (optional, auto-generated)
   - **Description**
3. Click **'Save'**

**Account Hierarchy:**
- Parent accounts contain sub-accounts
- Balances roll up to parent accounts
- Organized for easy navigation"

### Journal Entries (2 minutes)
"Journal Entries record all transactions.

**To Create a Journal Entry:**

1. Click **'Journal Entries'** tab
2. Click **'New Journal Entry'** button
3. Fill in the form:

**Required Information:**
- **Date** - Transaction date
- **Reference Number** - Optional reference
- **Description** - Transaction description

**Entry Lines:**
- **Account** - Select account from dropdown
- **Debit Amount** - Enter debit (if applicable)
- **Credit Amount** - Enter credit (if applicable)
- **Description** - Line item description
- **Entity Link** - Link to Agent, Worker, etc. (optional)

4. **Add multiple lines** as needed
5. **System validates** debits equal credits
6. Click **'Save'** when balanced

**Important:**
- Total debits must equal total credits
- System shows running balance
- Can't save unbalanced entries
- Each entry is numbered sequentially"

### Editing Journal Entries (30 seconds)
"To edit a journal entry:

1. Find the entry in the list
2. Click **'Edit'** button
3. Modify lines or add new lines
4. Ensure debits equal credits
5. Click **'Update'**

**Note:** Some entries may be locked if they're part of closed periods."

### Viewing Journal Entries (30 seconds)
"Journal Entries table shows:

- **Entry Number** - Sequential ID
- **Date** - Transaction date
- **Description** - Entry description
- **Debit Total** - Total debits
- **Credit Total** - Total credits
- **Status** - Posted, Draft, etc.

Click **'View'** to see full entry details with all lines."

### Closing (15 seconds)
"In the next video, we'll cover Banking, Invoices, and Bills. Stay tuned!"

---

## Video 13: Accounting System - Part 3
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this final Accounting video, we'll cover Banking, Invoices, Bills, and Financial Reports."

### Banking & Cash Management (1.5 minutes)
"Manage your bank accounts and cash:

**To Add a Bank Account:**
1. Click **'Banking & Cash'** tab
2. Click **'Add Bank Account'**
3. Enter:
   - **Bank Name**
   - **Account Number**
   - **Account Type** (Checking, Savings)
   - **Opening Balance**
   - **Currency**
4. Click **'Save'**

**Bank Transactions:**
1. Select a bank account
2. Click **'Add Transaction'**
3. Enter:
   - **Date**
   - **Type** (Deposit, Withdrawal, Transfer)
   - **Amount**
   - **Description**
   - **Linked Account** (for double-entry)
4. Save transaction

**Bank Reconciliation:**
- Match transactions with bank statements
- Mark transactions as reconciled
- Identify discrepancies"

### Invoices (Receivables) (1.5 minutes)
"Invoices track money customers owe you.

**To Create an Invoice:**
1. Click **'Receivables'** tab
2. Click **'New Invoice'**
3. Fill in:
   - **Customer** - Select from customers list
   - **Invoice Date**
   - **Due Date**
   - **Invoice Number** (auto-generated)
   - **Currency**

**Invoice Lines:**
- **Description** - Item/service description
- **Quantity**
- **Unit Price**
- **Total** (auto-calculated)
- **Account** - Income account to credit

4. **Add multiple line items**
5. **Total calculates automatically**
6. Click **'Save Invoice'**

**Invoice Status:**
- **Draft** - Not yet sent
- **Sent** - Sent to customer
- **Paid** - Payment received
- **Overdue** - Past due date

**Recording Payment:**
- Click **'Record Payment'** on invoice
- Enter payment amount and date
- Select payment method
- System updates invoice status"

### Bills (Payables) (1 minute)
"Bills track money you owe to vendors.

**To Create a Bill:**
1. Click **'Payables'** tab
2. Click **'New Bill'**
3. Similar to invoices but:
   - Select **Vendor** instead of customer
   - Bills increase payables (liability)
   - Record payments to reduce balance

**Bill Management:**
- Track due dates
- Record payments
- View aging reports
- Link to vendors"

### Financial Reports (1 minute)
"Generate comprehensive financial reports:

**Available Reports:**
- **Trial Balance** - All account balances
- **Balance Sheet** - Assets, Liabilities, Equity
- **Income Statement** - Revenue and Expenses (Profit & Loss)
- **Cash Flow Statement** - Cash movements
- **General Ledger** - Detailed account transactions
- **Aging Reports** - Receivables and Payables aging

**To Generate Reports:**
1. Click **'Reports'** tab
2. Select report type
3. Choose date range
4. Click **'Generate'**
5. View, print, or export report

Reports can be exported to PDF or Excel."

### Closing (15 seconds)
"That completes the Accounting System overview. Next, we'll explore HR Management. See you in the next video!"

---

## Video 14: HR Management - Part 1
**Duration: 4-5 minutes**

### Opening (30 seconds)
"Welcome to HR Management - a comprehensive system for managing employees, attendance, payroll, and HR operations."

### Understanding HR Module (1 minute)
"The HR module manages:

- **Employees** - Employee records and information
- **Attendance** - Daily attendance tracking
- **Salaries** - Payroll management
- **Advances** - Employee advances/loans
- **Documents** - HR document management
- **Cars** - Company vehicle management
- **Settings** - HR configuration

This module integrates with the accounting system for payroll processing."

### Accessing HR Module (30 seconds)
"To access HR:

1. **Click 'HR System'** from the dashboard card, OR
2. **Click 'HR'** from the navigation bar

You'll see the HR Dashboard with module statistics."

### HR Dashboard (1.5 minutes)
"The HR Dashboard shows:

**Statistics Cards:**
- **Total Employees** - All employees
- **Today's Attendance** - Employees who checked in today
- **Pending Advances** - Unpaid advances
- **Pending Salaries** - Unprocessed salaries
- **Active Documents** - HR documents
- **Company Cars** - Vehicle count

**Module Navigation:**
- Click any card to go to that module
- Quick actions available
- Real-time statistics

Each card is clickable and takes you to the respective section."

### Employees Management (1.5 minutes)
"**To View Employees:**
1. Click **'Employees'** card or navigate to Employees section
2. See list of all employees
3. View employee details, status, department

**To Add an Employee:**
1. Click **'Add Employee'** button
2. Fill in employee form:
   - **Employee ID** (auto-generated)
   - **Full Name** (required)
   - **Email** (required, unique)
   - **Phone**
   - **Department**
   - **Position/Job Title**
   - **Hire Date**
   - **Salary** (base salary)
   - **Status** (Active, Inactive, Terminated)
   - **Address** and contact details

3. Click **'Save'** to create employee

**Employee Information:**
- Personal details
- Employment information
- Salary details
- Status tracking
- Document links"

### Editing Employees (30 seconds)
"To edit an employee:

1. Click **'Edit'** for the employee
2. Update information
3. Change status if needed
4. Update salary (creates history)
5. Click **'Update'**

Changes are logged in employee history."

### Closing (15 seconds)
"In the next video, we'll cover Attendance, Salaries, and Advances. Stay tuned!"

---

## Video 15: HR Management - Part 2
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll cover Attendance tracking, Salary management, and Employee Advances."

### Attendance Management (1.5 minutes)
"Track daily employee attendance:

**To Mark Attendance:**
1. Click **'Mark Attendance'** button from dashboard
2. Select **Date** (defaults to today)
3. For each employee:
   - **Check In** - Record check-in time
   - **Check Out** - Record check-out time
   - **Status** - Present, Absent, Late, Leave
   - **Notes** - Additional notes

4. Click **'Save Attendance'**

**Attendance Features:**
- **Bulk Entry** - Mark multiple employees at once
- **Time Tracking** - Automatic time calculation
- **Leave Management** - Track leaves and absences
- **Reports** - Generate attendance reports
- **History** - View attendance history

**Viewing Attendance:**
- Calendar view of attendance
- Monthly summaries
- Employee-specific attendance
- Export to Excel/PDF"

### Salary Management (1.5 minutes)
"Manage employee payroll:

**Salary Components:**
- **Base Salary** - Fixed monthly salary
- **Allowances** - Additional payments
- **Deductions** - Tax, advances, etc.
- **Net Salary** - Final amount

**To Process Salaries:**
1. Navigate to **'Salaries'** section
2. Select **Pay Period** (month/year)
3. System calculates:
   - Base salary
   - Allowances
   - Deductions (taxes, advances)
   - Net salary

4. **Review calculations**
5. **Approve** salaries
6. **Generate Payslips**
7. **Record Payment** (links to accounting)

**Salary Features:**
- **Auto-calculation** - Based on employee data
- **Deduction Management** - Automatic advance deductions
- **Payslip Generation** - Printable payslips
- **Accounting Integration** - Creates journal entries
- **History** - Salary payment history"

### Employee Advances (1.5 minutes)
"Manage employee advances/loans:

**To Add an Advance:**
1. Navigate to **'Advances'** section
2. Click **'New Advance'**
3. Fill in:
   - **Employee** - Select employee
   - **Amount** - Advance amount
   - **Date** - Advance date
   - **Purpose** - Reason for advance
   - **Repayment Plan** - How it will be repaid
   - **Status** - Pending, Approved, Repaid

4. Click **'Save'**

**Advance Management:**
- **Approval Workflow** - Approve/reject advances
- **Repayment Tracking** - Track repayments
- **Automatic Deduction** - Deduct from salary
- **Reports** - Advance reports
- **History** - Complete advance history

**Repayment:**
- Manual repayment recording
- Automatic salary deduction
- Track remaining balance
- Mark as fully repaid"

### HR Documents (30 seconds)
"Manage HR-related documents:

- **Employee Contracts**
- **ID Cards**
- **Certificates**
- **Letters**
- **Other Documents**

Upload, categorize, and track document expiration dates."

### Closing (15 seconds)
"That covers HR Management. Next, we'll explore Contacts & Communications. See you in the next video!"

---

## Video 16: Contacts & Communications
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore Contacts Management and the Communications system for managing relationships and messages."

### Understanding Contacts (1 minute)
"The Contacts module manages:

- **Customer Contacts** - Your customers
- **Vendor Contacts** - Your suppliers
- **Agent Contacts** - Agent representatives
- **Other Contacts** - Any business contacts

Each contact has complete information and can be linked to other entities."

### Accessing Contacts (30 seconds)
"To access Contacts:

1. **Click 'Contact'** from the dashboard card, OR
2. **Click 'Contact'** from the navigation bar

You'll see the Contacts Management page."

### Contacts Page Layout (1 minute)
"The Contacts page includes:

- **Statistics Cards** - Total, Active, Inactive contacts
- **Search Bar** - Search by name, email, phone, company
- **Status Filter** - Filter by Active/Inactive
- **Add New Button** - Create new contact
- **Data Table** - List of all contacts
- **Action Buttons** - View, Edit, Delete

Contacts are displayed with their status and type."

### Adding a Contact (1.5 minutes)
"To add a new contact:

1. **Click 'Add New'** button
2. Fill in the contact form:

**Required Fields:**
- **Name** - Contact's full name
- **Type** - Customer, Vendor, Agent, Other
- **Status** - Active or Inactive

**Contact Information:**
- **Email** - Contact email
- **Phone** - Primary phone
- **Mobile** - Mobile number
- **Company** - Company name
- **Position** - Job title

**Address Information:**
- **Country** - Select country
- **City** - Select city
- **Address** - Full address
- **Postal Code**

**Additional Information:**
- **Notes** - Additional notes
- **Tags** - For categorization
- **Linked Entities** - Link to Agents, Workers, etc.

3. **Click 'Save'** to create contact

**Contact ID** is auto-generated"

### Managing Contacts (1 minute)
"**To Edit a Contact:**
1. Click 'Edit' button
2. Update information
3. Change status if needed
4. Save changes

**To Change Status:**
- Click status badge, OR
- Edit contact and change status
- Inactive contacts won't appear in dropdowns

**Searching Contacts:**
- Use search bar for quick finding
- Filter by status
- Search across multiple fields"

### Communications System (1 minute)
"The Communications module manages messages:

**Accessing Communications:**
1. Click **'Communications'** from navigation
2. See message inbox/outbox

**Message Types:**
- **Email** - Email messages
- **SMS** - Text messages
- **Internal Notes** - System messages

**Features:**
- **Send Messages** - To contacts
- **Message Templates** - Reusable templates
- **Message History** - Complete history
- **Notifications** - Alert on new messages
- **Attachments** - Attach files to messages

**Sending a Message:**
1. Click **'New Message'**
2. Select **Recipient** (contact)
3. Choose **Message Type**
4. Enter **Subject** and **Message**
5. Attach files if needed
6. Click **'Send'**

Messages are logged and tracked."

### Closing (15 seconds)
"That covers Contacts & Communications. Next, we'll explore the Reports system. See you in the next video!"

---

## Video 17: Reports System
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore the Reports system - a comprehensive reporting solution for analyzing data across all modules."

### Understanding Reports (1 minute)
"The Reports module provides:

- **Module-Specific Reports** - Reports for each module
- **Combined Reports** - Cross-module reports
- **Activity Reports** - System activity logs
- **Financial Reports** - Accounting reports (covered in Accounting video)
- **Export Options** - PDF, Excel, CSV
- **Custom Date Ranges** - Filter by date
- **Print Functionality** - Direct printing

Reports help you analyze data and make informed decisions."

### Accessing Reports (30 seconds)
"To access Reports:

1. **Click 'Reports'** from the dashboard card, OR
2. **Click 'Reports'** from the navigation bar

You'll see the Reports page with available report types."

### Available Reports (2 minutes)
"**Workers Reports:**
- Worker list with details
- Status summary
- Document status reports
- Musaned status reports
- Agent/Subagent assignments

**Agents Reports:**
- Agent list
- Agent performance
- Subagent relationships
- Worker assignments per agent

**HR Reports:**
- Employee list
- Attendance reports
- Salary reports
- Advance reports
- Department summaries

**Cases Reports:**
- Case status summary
- Case by assignee
- Case by priority
- Case resolution time
- Case history

**Activity Reports:**
- System activity log
- User activity
- Module activity
- Date range activity

**Financial Reports:**
- (Covered in Accounting videos)
- Trial Balance, Balance Sheet, etc."

### Generating Reports (1.5 minutes)
"**To Generate a Report:**

1. **Select Report Type** from the list
2. **Choose Filters:**
   - Date range (From/To dates)
   - Status filters
   - Entity filters (Agent, Worker, etc.)
   - Other relevant filters

3. **Click 'Generate Report'**
4. **Report displays** in a table or chart format
5. **Options Available:**
   - **Export to PDF** - Download as PDF
   - **Export to Excel** - Download as Excel
   - **Export to CSV** - Download as CSV
   - **Print** - Print directly
   - **Email** - Email report (if configured)

**Report Features:**
- **Real-time Data** - Always current
- **Multiple Formats** - PDF, Excel, CSV
- **Customizable** - Filter as needed
- **Scheduled Reports** - Auto-generate (if configured)
- **Report History** - Save report configurations"

### Individual Reports (30 seconds)
"**Individual Reports** show detailed information for a specific entity:

- **Worker Report** - Complete worker profile
- **Agent Report** - Full agent details
- **Case Report** - Case with all activities
- **Employee Report** - HR employee report

Access individual reports from the respective module's 'View' or 'Report' button."

### Closing (15 seconds)
"That covers the Reports system. Next, we'll explore Notifications. See you in the next video!"

---

## Video 18: Notifications
**Duration: 3-4 minutes**

### Opening (30 seconds)
"In this video, we'll explore the Notifications system - how the system alerts you to important events and updates."

### Understanding Notifications (1 minute)
"Notifications in Ratib Program include:

- **System Alerts** - Important system events
- **Task Reminders** - Pending tasks and follow-ups
- **Status Changes** - Entity status updates
- **Document Alerts** - Document status changes
- **Payment Reminders** - Due payments
- **Assignment Notifications** - New assignments
- **Email Notifications** - Email-based alerts

Notifications keep you informed of important activities."

### Accessing Notifications (30 seconds)
"To access Notifications:

1. **Click 'Notifications'** from the dashboard card, OR
2. **Click 'Notifications'** from the navigation bar, OR
3. **Click the notification bell icon** in the header (if available)

You'll see your notification inbox."

### Notifications Page (1.5 minutes)
"The Notifications page shows:

**Notification List:**
- **Unread Notifications** - New notifications (highlighted)
- **Read Notifications** - Previously viewed
- **All Notifications** - Complete list

**Notification Information:**
- **Type** - Notification category
- **Title** - Notification title
- **Message** - Detailed message
- **Date/Time** - When notification was created
- **Status** - Read/Unread
- **Action Links** - Links to related entities

**Notification Badge:**
- Shows count of unread notifications
- Updates in real-time
- Appears in navigation bar"

### Managing Notifications (1 minute)
"**To Mark as Read:**
1. Click on a notification
2. It automatically marks as read, OR
3. Click 'Mark as Read' button

**To Mark All as Read:**
1. Click 'Mark All as Read' button
2. All notifications marked as read

**To Delete Notifications:**
1. Select notifications
2. Click 'Delete' button
3. Confirm deletion

**Notification Settings:**
- Configure notification preferences
- Choose notification types
- Set email notifications
- Configure alert sounds (if available)"

### Notification Types (30 seconds)
"Common notification types:

- **New Worker Added** - When a worker is created
- **Document Status Changed** - Document updates
- **Case Assigned** - New case assignment
- **Payment Due** - Payment reminders
- **Status Change** - Entity status updates
- **System Alerts** - System-wide alerts

Each type has its own icon and styling."

### Closing (15 seconds)
"That covers Notifications. Next, we'll explore System Settings. See you in the next video!"

---

## Video 19: System Settings - Part 1
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll explore System Settings - where you configure the system, manage settings, and customize the application."

### Understanding System Settings (1 minute)
"System Settings includes:

- **Visa Types** - Manage visa categories
- **Recruitment Countries** - Countries for recruitment
- **Job Categories** - Job type classifications
- **Age Specifications** - Age range settings
- **Appearance Specifications** - Physical appearance options
- **Status Specifications** - Status options
- **Request Statuses** - Request status types
- **Arrival Agencies** - Agency information
- **Arrival Stations** - Station locations
- **Worker Statuses** - Worker status options
- **HR Settings** - HR configuration
- **Office Manager Data** - Office information

These settings control dropdowns and options throughout the system."

### Accessing System Settings (30 seconds)
"To access System Settings:

1. **Click 'Settings'** from the dashboard card, OR
2. **Click 'System Settings'** from the navigation bar

You'll see the System Settings page with different setting categories."

### Settings Interface (1 minute)
"The Settings page uses tabs or sections:

**Setting Categories:**
- Each category has its own section
- Settings are organized by module
- Search functionality to find settings
- Add, Edit, Delete options for each setting

**Settings Table:**
- Lists all settings in the category
- Shows setting name, value, status
- Active/Inactive indicators
- Action buttons"

### Managing Settings (2 minutes)
"**To Add a Setting:**

1. **Select a category** (e.g., Visa Types)
2. **Click 'Add New'** button
3. Fill in the form:
   - **Name** - Setting name (required)
   - **Code** - Optional code
   - **Description** - Description
   - **Status** - Active/Inactive
   - **Additional fields** as needed

4. **Click 'Save'**

**To Edit a Setting:**
1. Click 'Edit' for the setting
2. Modify information
3. Change status if needed
4. Click 'Update'

**To Delete a Setting:**
1. Click 'Delete' button
2. Confirm deletion
3. **Warning**: Deleting may affect linked records

**Bulk Operations:**
- Activate/Deactivate multiple settings
- Delete multiple settings
- Import/Export settings (if available)"

### Setting Status (30 seconds)
"Settings can be:

- **Active** - Available in dropdowns
- **Inactive** - Hidden from dropdowns but not deleted

**Important**: Only active settings appear in dropdown menus throughout the system. Inactive settings are preserved for historical data."

### Closing (15 seconds)
"In the next video, we'll cover User Management and Permissions - critical for system security. Stay tuned!"

---

## Video 20: System Settings - Part 2
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this video, we'll continue with System Settings, covering advanced settings, configuration options, and system preferences."

### Advanced Settings (1.5 minutes)
"**HR Settings:**
- Department configurations
- Position/job title settings
- Salary structure settings
- Leave type configurations

**Office Manager Data:**
- Office name and details
- Contact information
- Address and location
- Business registration details

**Accounting Settings:**
- Fiscal year configuration
- Currency settings
- Default accounts
- Tax settings
- Reporting preferences

**System Preferences:**
- Language settings
- Date format
- Time zone
- Number format
- Email configuration"

### Configuration Management (1.5 minutes)
"**To Configure Settings:**

1. Navigate to the specific setting category
2. **View current configuration**
3. **Modify values** as needed
4. **Save changes**

**Important Configurations:**

**Email Settings:**
- SMTP server configuration
- Email templates
- Notification preferences

**Security Settings:**
- Password policies
- Session timeout
- Login attempts limit
- Two-factor authentication

**Integration Settings:**
- API configurations
- Third-party integrations
- Webhook settings

**Backup Settings:**
- Automatic backup schedule
- Backup retention
- Storage location"

### Settings Import/Export (1 minute)
"**To Export Settings:**
1. Select settings category
2. Click 'Export' button
3. Choose format (JSON, CSV, Excel)
4. Download file

**To Import Settings:**
1. Click 'Import' button
2. Select file
3. Preview imported data
4. Confirm import
5. Settings updated

**Use Cases:**
- Backup settings
- Transfer between systems
- Bulk updates
- Migration"

### Settings Validation (30 seconds)
"**System validates settings:**

- **Required fields** must be filled
- **Unique values** are enforced
- **Data format** validation
- **Dependencies** are checked
- **Warnings** for potentially problematic changes

Invalid settings are rejected with error messages."

### Closing (15 seconds)
"In the final video, we'll cover Permissions & User Management - essential for controlling access. See you then!"

---

## Video 21: Permissions & User Management
**Duration: 4-5 minutes**

### Opening (30 seconds)
"In this final video, we'll explore Permissions and User Management - critical for system security and access control."

### Understanding Permissions (1 minute)
"Ratib Program uses a **role-based access control (RBAC)** system:

- **Roles** - Groups of permissions (Admin, Manager, User, etc.)
- **Permissions** - Specific actions users can perform
- **User-Specific Permissions** - Override role permissions
- **Module Permissions** - Control access to modules
- **Action Permissions** - Control specific actions (view, add, edit, delete)

**Permission Hierarchy:**
1. **Admin** (role_id = 1) - Has all permissions by default
2. **Role Permissions** - Permissions assigned to roles
3. **User-Specific Permissions** - Individual user overrides

Users see only what they have permission to access."

### Accessing User Management (30 seconds)
"To access User Management:

1. Navigate to **'System Settings'**
2. Look for **'Users'** or **'User Management'** section, OR
3. Access through Admin panel (if available)

You'll see a list of all system users."

### User Management (1.5 minutes)
"**To Add a User:**

1. Click **'Add User'** button
2. Fill in user form:
   - **Username** (required, unique)
   - **Email** (required, unique)
   - **Password** (required, meets security requirements)
   - **Phone** (optional)
   - **Role** - Select from roles dropdown
   - **Status** - Active/Inactive
   - **Additional Information** - Name, address, etc.

3. **Assign Permissions** (if user-specific permissions enabled)
4. Click **'Save'** to create user

**To Edit a User:**
1. Click 'Edit' for the user
2. Update information
3. Change role if needed
4. Update permissions
5. Click 'Update'

**To Delete a User:**
1. Click 'Delete' button
2. Confirm deletion
3. **Warning**: Cannot delete users with active records"

### Role Management (1.5 minutes)
"**To Create a Role:**

1. Navigate to **'Roles'** section
2. Click **'Add Role'**
3. Fill in:
   - **Role Name** (required)
   - **Description**
   - **Permissions** - Select permissions for this role

4. Click **'Save'**

**To Edit a Role:**
1. Click 'Edit' for the role
2. Modify role name/description
3. **Add or Remove Permissions**
4. Click 'Update'

**Common Roles:**
- **Admin** - Full system access
- **Manager** - Management-level access
- **User** - Standard user access
- **Viewer** - Read-only access
- **Custom Roles** - Created as needed"

### Permission Management (1 minute)
"**Permission Categories:**

**Module Permissions:**
- `view_dashboard` - Access dashboard
- `view_agents` - View agents module
- `view_workers` - View workers module
- `view_accounting` - View accounting
- `view_hr_dashboard` - View HR module
- `view_reports` - View reports
- `manage_settings` - Access settings

**Action Permissions:**
- `add_agent` - Add new agents
- `edit_agent` - Edit agents
- `delete_agent` - Delete agents
- `add_worker` - Add workers
- `edit_worker` - Edit workers
- `delete_worker` - Delete workers

**To Assign Permissions:**
1. Select user or role
2. Check/uncheck permission checkboxes
3. Permissions are organized by module
4. Save changes

**Permission Enforcement:**
- UI elements hidden if no permission
- API endpoints check permissions
- Unauthorized actions are blocked"

### Permission Best Practices (30 seconds)
"**Best Practices:**

- **Principle of Least Privilege** - Give minimum necessary permissions
- **Role-Based First** - Use roles for common permission sets
- **User-Specific Sparingly** - Only for exceptions
- **Regular Audits** - Review permissions periodically
- **Document Roles** - Keep role descriptions clear
- **Test Permissions** - Verify access controls work"

### Closing (30 seconds)
"Congratulations! You've completed the complete Ratib Program tutorial series. You now understand:

- Login and authentication
- Dashboard navigation
- All major modules (Agents, Subagents, Workers, Cases, Accounting, HR)
- Contacts and Communications
- Reports and Notifications
- System Settings
- Permissions and User Management

Thank you for watching! For additional help, refer to the system documentation or contact support."

---

## Additional Notes for Video Creation

### General Tips:
1. **Screen Recording**: Use clear, high-resolution screen recordings
2. **Voice Narration**: Speak clearly and at a moderate pace
3. **Highlighting**: Use cursor highlighting or zoom effects for important areas
4. **Transitions**: Smooth transitions between sections
5. **Captions**: Add captions for accessibility
6. **Timing**: Keep each segment to 3-5 minutes as specified

### What to Show:
- **Actual System**: Use real system screenshots/recordings
- **Step-by-Step**: Show each click and action clearly
- **Error Handling**: Show what happens with errors
- **Validation**: Demonstrate form validation
- **Success Messages**: Show success confirmations

### What to Explain:
- **Why**: Explain the purpose of each feature
- **When**: When to use each feature
- **How**: Step-by-step instructions
- **Important Notes**: Warnings and best practices

### Video Structure:
- **Introduction** (30 seconds)
- **Main Content** (3-4 minutes)
- **Closing** (15-30 seconds)

---

**End of Video Tutorial Script**

