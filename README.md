You are an expert WordPress plugin developer. Build a full-featured 
Live Chat & Help Desk WordPress plugin similar to "Support Board" 
(board.support) but WITHOUT any AI or chatbot integration. 
The plugin must be production-ready, secure, well-structured, 
and follow WordPress coding standards (WPCS).



FOLDER STRUCTURE

myhelpdesk-chat/
├── myhelpdesk-chat.php
├── uninstall.php
├── readme.txt
├── includes/
│   ├── class-mhd-loader.php
│   ├── class-mhd-activator.php
│   ├── class-mhd-deactivator.php
│   ├── class-mhd-ajax.php
│   ├── class-mhd-conversations.php
│   ├── class-mhd-messages.php
│   ├── class-mhd-agents.php
│   ├── class-mhd-departments.php
│   ├── class-mhd-tickets.php
│   ├── class-mhd-notifications.php
│   ├── class-mhd-email.php
│   ├── class-mhd-knowledge-base.php
│   ├── class-mhd-automations.php
│   ├── class-mhd-woocommerce.php
│   ├── class-mhd-reports.php
│   └── class-mhd-rest-api.php
├── admin/
│   ├── class-mhd-admin.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── conversations.php
│   │   ├── tickets.php
│   │   ├── agents.php
│   │   ├── departments.php
│   │   ├── knowledge-base.php
│   │   ├── saved-replies.php
│   │   ├── automations.php
│   │   ├── reports.php
│   │   └── settings.php
│   ├── css/admin-style.css
│   └── js/
│       ├── admin-conversations.js
│       ├── admin-tickets.js
│       └── admin-notifications.js
├── public/
│   ├── class-mhd-public.php
│   ├── views/
│   │   └── chat-widget.php
│   ├── css/chat-style.css
│   └── js/
│       ├── chat-widget.js
│       └── chat-notifications.js
└── languages/


DATABASE SCHEMA

Create all tables using dbDelta() in the Activator class.
Use $wpdb->prefix for all table names.

TABLE 1: {prefix}mhd_conversations
- id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- user_id       BIGINT UNSIGNED DEFAULT NULL (FK wp_users, nullable  guest)
- agent_id      BIGINT UNSIGNED DEFAULT NULL
- department_id INT UNSIGNED DEFAULT NULL
- status        ENUM('open','pending','resolved','archived') DEFAULT 'open'
- subject       VARCHAR(255) DEFAULT ''
- source        ENUM('chat','email','ticket','whatsapp','facebook',
                     'telegram','direct') DEFAULT 'chat'
- user_email    VARCHAR(255) DEFAULT ''
- user_name     VARCHAR(100) DEFAULT ''
- user_ip       VARCHAR(45) DEFAULT ''
- user_browser  VARCHAR(255) DEFAULT ''
- user_location VARCHAR(255) DEFAULT ''
- current_page  TEXT
- tags          TEXT (JSON array of tag strings)
- extra_data    LONGTEXT (JSON for misc metadata)
- created_at    DATETIME NOT NULL
- updated_at    DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 2: {prefix}mhd_messages
- id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- conversation_id  BIGINT UNSIGNED NOT NULL
- user_id          BIGINT UNSIGNED DEFAULT NULL
- agent_id         BIGINT UNSIGNED DEFAULT NULL
- message          LONGTEXT NOT NULL
- attachments      TEXT DEFAULT NULL (JSON array of URLs/filenames)
- message_type     ENUM('text','image','file','system','note',
                        'rich','email') DEFAULT 'text'
- is_read          TINYINT(1) DEFAULT 0
- created_at       DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 3: {prefix}mhd_agents
- id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- user_id       BIGINT UNSIGNED NOT NULL (FK wp_users)
- department_id INT UNSIGNED DEFAULT NULL
- role          ENUM('agent','supervisor','admin') DEFAULT 'agent'
- is_online     TINYINT(1) DEFAULT 0
- status        ENUM('active','away','offline') DEFAULT 'offline'
- max_chats     INT DEFAULT 5
- last_seen     DATETIME DEFAULT NULL
- profile_image VARCHAR(255) DEFAULT ''
- PRIMARY KEY (id)

TABLE 4: {prefix}mhd_departments
- id          INT UNSIGNED NOT NULL AUTO_INCREMENT
- name        VARCHAR(100) NOT NULL
- description TEXT DEFAULT ''
- color       VARCHAR(10) DEFAULT '#0084ff'
- agents      TEXT DEFAULT NULL (JSON array of agent user IDs)
- created_at  DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 5: {prefix}mhd_tickets
- id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- conversation_id  BIGINT UNSIGNED DEFAULT NULL
- user_id          BIGINT UNSIGNED NOT NULL
- user_email       VARCHAR(255) DEFAULT ''
- subject          VARCHAR(255) NOT NULL
- status           ENUM('open','in_progress','resolved','closed')
                   DEFAULT 'open'
- priority         ENUM('low','medium','high','urgent') DEFAULT 'medium'
- assigned_agent_id BIGINT UNSIGNED DEFAULT NULL
- department_id    INT UNSIGNED DEFAULT NULL
- tags             TEXT DEFAULT NULL
- created_at       DATETIME NOT NULL
- updated_at       DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 6: {prefix}mhd_ticket_replies
- id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- ticket_id  BIGINT UNSIGNED NOT NULL
- user_id    BIGINT UNSIGNED DEFAULT NULL
- agent_id   BIGINT UNSIGNED DEFAULT NULL
- message    LONGTEXT NOT NULL
- attachments TEXT DEFAULT NULL
- is_note    TINYINT(1) DEFAULT 0
- created_at DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 7: {prefix}mhd_saved_replies
- id         INT UNSIGNED NOT NULL AUTO_INCREMENT
- name       VARCHAR(100) NOT NULL
- message    TEXT NOT NULL
- agent_id   BIGINT UNSIGNED DEFAULT NULL (NULL  global/all agents)
- category   VARCHAR(100) DEFAULT ''
- created_at DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 8: {prefix}mhd_notifications
- id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- agent_id        BIGINT UNSIGNED NOT NULL
- conversation_id BIGINT UNSIGNED DEFAULT NULL
- ticket_id       BIGINT UNSIGNED DEFAULT NULL
- type            ENUM('new_chat','new_message','new_ticket',
                       'ticket_reply','assigned') DEFAULT 'new_message'
- message         VARCHAR(255) NOT NULL
- is_read         TINYINT(1) DEFAULT 0
- created_at      DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 9: {prefix}mhd_kb_categories
- id         INT UNSIGNED NOT NULL AUTO_INCREMENT
- name       VARCHAR(100) NOT NULL
- slug       VARCHAR(100) NOT NULL
- icon       VARCHAR(50) DEFAULT ''
- created_at DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 10: {prefix}mhd_kb_articles
- id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- category_id INT UNSIGNED DEFAULT NULL
- title       VARCHAR(255) NOT NULL
- slug        VARCHAR(255) NOT NULL
- content     LONGTEXT NOT NULL
- excerpt     TEXT DEFAULT ''
- views       BIGINT DEFAULT 0
- helpful_yes INT DEFAULT 0
- helpful_no  INT DEFAULT 0
- status      ENUM('published','draft') DEFAULT 'published'
- created_at  DATETIME NOT NULL
- updated_at  DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 11: {prefix}mhd_automations
- id         INT UNSIGNED NOT NULL AUTO_INCREMENT
- name       VARCHAR(100) NOT NULL
- trigger    VARCHAR(100) NOT NULL 
             (e.g. 'new_conversation','message_received',
              'no_reply_time','page_url')
- conditions TEXT DEFAULT NULL (JSON)
- action     VARCHAR(100) NOT NULL
             (e.g. 'assign_agent','assign_dept','send_message',
              'send_email','change_status','add_tag')
- action_data TEXT DEFAULT NULL (JSON)
- is_active  TINYINT(1) DEFAULT 1
- created_at DATETIME NOT NULL
- PRIMARY KEY (id)

TABLE 12: {prefix}mhd_ratings
- id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
- conversation_id BIGINT UNSIGNED NOT NULL
- agent_id        BIGINT UNSIGNED DEFAULT NULL
- rating          TINYINT NOT NULL (1thumbs down, 5thumbs up)
- comment         TEXT DEFAULT ''
- created_at      DATETIME NOT NULL
- PRIMARY KEY (id)


FEATURE 1: CHAT WIDGET (FRONTEND)

Build a floating chat widget injected into every page (unless disabled).

- Floating bubble (bottom-right, position configurable in settings)
- Unread message badge counter on bubble
- On click: opens a smooth animated chat window
- Pre-chat form for guests: collect Name + Email (each toggleable)
- Department selector (optional, shows if multiple departments exist)
- GDPR consent checkbox with custom text (toggleable in settings)
- Welcome message shown at top of chat window (admin configurable)
- Real-time messaging via AJAX long-polling (every 3 seconds)
  - Throttle to 10-second interval when browser tab is hidden
    (use document.visibilitychange event)
- Show agent name, avatar, and online status in chat header
- Typing indicator: when agent types, user sees "Agent is typing..."
  - Implement via AJAX flag stored in transients
- Message composer supports:
  - Plain text
  - Emoji picker (lightweight unicode grid, no external library)
  - File/image upload (validate MIME type server-side)
  - Send on Enter key (Shift+Enter for new line)
- Rich messages: admin can send messages with buttons 
  (clickable links), image cards, and simple list formats
- Chat history loads automatically for returning users
  (identified by cookie + email match)
- Sound notification on new message (toggle on/off)
- Offline form: if no agents online, show a form to collect 
  name, email, and message — saved as a ticket automatically
- Chat end: show a rating widget (thumbs up/down + optional comment)
- Minimize and close (X) button on chat window
- "Powered by" branding toggle in settings
- Proactive chat: automatically open chat with a custom message 
  after X seconds on a specific page URL (configurable in automations)
- Popup message: show a speech-bubble message near the chat bubble
  without opening the full window (configurable)
- Subscribe message: show a form asking visitor to enter email 
  to subscribe to newsletters
- Follow-up message: if visitor leaves without chatting, show 
  a follow-up message when they return
- Social share: show social media links inside the chat widget
- Shortcode: [mhd_chat_widget] to embed widget in specific pages only
- Widget CSS is fully customizable via Design settings


FEATURE 2: ADMIN DASHBOARD — CONVERSATIONS

Main URL: WP Admin → MyHelpDesk → Conversations

- Split view: 
  LEFT PANEL — Conversations list sidebar
  RIGHT PANEL — Active conversation thread

LEFT PANEL (Conversations List):
- Show all conversations sorted by updated_at DESC
- Each row shows: user avatar, user name, subject/last message 
  preview, time, status badge (color), unread count badge
- Filter bar: by status, department, agent, source, date range
- Search box: search by user name, email, keyword in messages
- Tabs: All | Open | Pending | Resolved | Archived
- Real-time update: new conversations appear without page refresh
- One-click: mark as pending, resolved, archive
- Bulk actions: bulk assign, bulk resolve, bulk delete
- Sort options: newest first, oldest first, unread first

RIGHT PANEL (Conversation Thread):
- Show full message history, oldest to newest
- Each message shows: avatar, name, timestamp, message content
- Differentiate: user messages (left) vs agent messages (right)
- Internal notes (visible only to agents) shown in yellow
- File/image attachments shown inline or as download link
- Rich message preview (buttons, cards rendered)
- Email messages shown with email icon indicator
- Typing indicator shown when agent is composing

REPLY COMPOSER AREA:
- Text area with: bold, italic, link, emoji picker, file upload
- Saved replies: type "/" to trigger dropdown search of saved replies
- Switch between: Reply | Internal Note tabs
- Email reply option (send reply to user's email)
- Send button + Enter key support

CONVERSATION SIDEBAR (right metadata panel):
- User info: name, email, IP address, location, browser
- Page URL where chat was started
- Tags: add/remove conversation tags
- Assign to Agent dropdown
- Assign to Department dropdown
- Change Status dropdown
- Conversation source icon
- WooCommerce data: if WooCommerce active, show user's recent 
  orders (last 5), each with order ID, status, total, items
- Link to WordPress user profile (if registered)
- Previous conversations with this user (linked)
- Created date and updated date

DIRECT MESSAGE:
- Admin can initiate a new conversation with any registered user
- Select user from dropdown, write first message, send


FEATURE 3: ADMIN DASHBOARD — TICKETS

Main URL: WP Admin → MyHelpDesk → Tickets

- Tickets list with columns: ID, subject, user, status badge, 
  priority badge, assigned agent, department, created date
- Filter by: status, priority, agent, department, date
- Search by subject, user email
- Click ticket → opens full ticket thread view
- Ticket thread shows all replies in chronological order
- Reply form for agents (text + file upload + internal note toggle)
- Change ticket status (open → in_progress → resolved → closed)
- Change priority
- Assign to agent or department
- Add tags to ticket
- Email notification to user on each reply
- Frontend shortcode: [mhd_ticket_form] for users to submit tickets
  - Form fields: Name, Email, Subject, Message, Priority 
    (optional), File attachment
- User can submit without account (guest ticket)
- Optional: ticket submission requires login (settings toggle)
- User ticket status page: [mhd_my_tickets] shows logged-in 
  user's own tickets with status


FEATURE 4: AGENT MANAGEMENT

Main URL: WP Admin → MyHelpDesk → Agents

- List all registered agents with: avatar, name, email, role, 
  department, online status badge, last seen time
- Add agent: select existing WordPress user → assign as agent
  - Set role: Agent | Supervisor | Admin
  - Set department
  - Set max concurrent chats (default 5)
- Edit agent: change role, department, max chats
- Remove agent (removes from agents table, does not delete WP user)
- Agent availability: each agent can set their own status from 
  the admin header bar: Active | Away | Offline
- Agent profile: custom profile image upload (separate from WP avatar)
- Agent performance stats on their profile card: 
  total chats handled, total messages sent, average rating score
- Supervisor role: can see all agent conversations, 
  reassign chats, view reports for their department
- Add custom WP capability: 'mhd_agent' — used for access control
- Agents only see conversations assigned to them 
  (or their department) unless they are Supervisor/Admin


FEATURE 5: DEPARTMENTS

Main URL: WP Admin → MyHelpDesk → Departments

- Create/Edit/Delete departments
- Fields: Name, Description, Color (color picker), Agents (multi-select)
- Department shown in conversation list as a color badge
- Chat routing: user selects department in pre-chat form 
  (if enabled in settings)
- Auto-assign logic: when a new chat comes in for a department:
  - Option 1: Round-robin (assign to next available agent in cycle)
  - Option 2: Least-busy (assign to agent with fewest open chats)
  - Option 3: Manual only (no auto-assign, conversation sits unassigned)
  - Configure per department in settings


FEATURE 6: SAVED REPLIES

Main URL: WP Admin → MyHelpDesk → Saved Replies

- Create/Edit/Delete saved replies
- Fields: Name (shortcut label), Message (full reply text), 
  Category (grouping), Visibility (Global or Personal per agent)
- Global replies: visible to all agents
- Personal replies: visible only to the creating agent
- In chat composer: type "/" → live search dropdown appears 
  showing matching saved replies → click to insert
- HTML/rich text supported in saved reply content
- Sort/filter by category or agent


FEATURE 7: KNOWLEDGE BASE

Main URL: WP Admin → MyHelpDesk → Knowledge Base

ADMIN SIDE:
- Create/Edit/Delete KB categories (name, slug, icon)
- Create/Edit/Delete KB articles:
  - Title, Content (rich text editor — wp_editor), 
    Excerpt, Category, Status (published/draft)
- Article search in admin list
- View count tracking (auto-increment on article view)
- Helpful rating (yes/no) submitted by users on article page

FRONTEND:
- Shortcode: [mhd_knowledge_base] — renders full KB page with:
  - Search bar at top
  - Category grid with icon + article count
  - Click category → shows articles list
  - Click article → shows full article content
  - Helpful? Yes/No buttons at bottom of each article
  - Breadcrumb navigation
- Inside chat widget: before user starts a chat, show KB search
  - User types a query → matching articles shown as suggestions
  - User can read articles without starting a chat
  - "Still need help? Start chat" link below results


FEATURE 8: AUTOMATIONS & TRIGGERS

Main URL: WP Admin → MyHelpDesk → Automations

Build a rule-based automation engine (no AI). Each rule has:
- Name
- Trigger (when this happens):
  - New conversation started
  - New message received
  - Conversation has had no reply for X minutes
  - User visits a specific page URL
  - Conversation status changes to X
  - Ticket created
  - Ticket status changes
- Conditions (optional filters):
  - Source equals (chat/email/whatsapp...)
  - Department equals
  - User email contains
  - Current page URL contains
  - Time of day between X and Y
  - Agent is offline
- Action (do this):
  - Assign to agent (specific or auto)
  - Assign to department
  - Send message to user (auto-message text)
  - Send email to agent
  - Send email to user
  - Change conversation status
  - Add tag to conversation
  - Show popup message in chat widget

Examples to pre-configure as templates:
  1. "If no agents are online → Auto reply with offline message"
  2. "If conversation unread for 5 minutes → send follow-up message"
  3. "If user visits /pricing → auto open chat with proactive message"
  4. "If new chat from Sales dept → assign to least-busy Sales agent"
  5. "If ticket created → email assigned agent"


FEATURE 9: NOTIFICATIONS

Browser Desktop Notifications:
- Request permission on agent login
- Push desktop notification on: new chat, new message, 
  new ticket, assignment
- Use Web Notifications API (no service worker required)

Sound Alerts:
- Play sound on new message for both agent (admin) and user (frontend)
- Admin can toggle per-event sounds in settings
- Different sound for: new chat vs new message

In-Dashboard Notifications Bell:
- Bell icon in admin header with unread count badge
- Dropdown list of recent notifications with type icon
- Click notification → go to relevant conversation or ticket
- Mark all as read button
- Auto-poll every 5 seconds for new notifications

Email Notifications (via wp_mail):
- Agent receives email when:
  - New chat assigned to them
  - New message in their conversation (if not currently viewing it)
  - New ticket assigned to them
  - Ticket reply from user
- User receives email when:
  - Agent replies to their chat (if they are offline/away)
  - Their ticket is updated
  - Their ticket is resolved
- Email templates: HTML email layout, 
  fully customizable in settings
- Configurable: use default WordPress SMTP or custom SMTP settings
  - Custom SMTP: host, port, username, password, encryption
- "From" name and email configurable in settings

Flash Tab Notification:
- When agent tab is not active and a new message arrives, 
  flash the browser tab title between 
  "● New Message — MyHelpDesk" and the original title
- Stop flashing when tab is focused again


FEATURE 10: REPORTS

Main URL: WP Admin → MyHelpDesk → Reports

Dashboard Overview Cards:
- Total conversations (today / this week / this month)
- Total messages sent (all agents combined)
- Total tickets (open / resolved)
- Average first response time
- Average chat resolution time
- Customer satisfaction score (% positive ratings)

Charts (use Chart.js, included locally — no CDN):
- Conversations per day (last 30 days) — line chart
- Conversations by source (chat/ticket/email/whatsapp) — donut chart
- Tickets by status — bar chart
- Agent activity: messages sent per agent — bar chart

Agent Performance Table:
- Columns: Agent Name, Chats Handled, Avg Response Time, 
  Tickets Resolved, Positive Ratings, Negative Ratings
- Sortable columns

Filters:
- Date range picker (from → to)
- Filter by agent
- Filter by department

Export:
- Export report data as CSV
- Export conversations list as CSV


FEATURE 11: WOOCOMMERCE INTEGRATION

File: includes/class-mhd-woocommerce.php
Load only if WooCommerce is active (use function_exists check).

Agent Conversation Sidebar:
- Show customer's WooCommerce data in the right panel 
  (only if user is registered and has orders):
  - Last 5 orders: order ID, status badge, total, date, items list
  - Link to full order in WP Admin
  - Order tracking link (if available)

Agent Actions from Chat:
- Copy order tracking URL to clipboard button
- Send WooCommerce coupon code to user directly from chat 
  (dropdown of available coupons → inserts coupon code as message)
- Send a direct product link to user (search products by name → 
  insert product URL as a rich message card)

User Profile Enrichment:
- In conversation sidebar show: total orders count, 
  total spend amount, registered date, customer grade
  (First-time / Regular / VIP based on order count thresholds 
   configurable in settings)


FEATURE 12: OMNI-CHANNEL INBOX (EMAIL PIPING)

Email Piping:
- Admin configures a support email inbox (IMAP settings: 
  host, port, username, password, encryption)
- Plugin uses IMAP via PHP imap extension to check for 
  new emails every X minutes (WP Cron job)
- New emails → automatically create a conversation with 
  source  'email'
- Email replies from agent sent via wp_mail to original sender
- Threading: reply-to same conversation if email is a reply to 
  previous thread (match by subject/message-id)
- Email subject shown as conversation subject
- HTML and plain text email content both supported


FEATURE 13: DIRECT MESSAGE

- Admin/Agent can initiate a direct private message to any 
  registered WordPress user
- Multi-user broadcast: select multiple users → send same 
  message to all (creates separate conversations per user)
- Source shown as 'direct' in conversation list
- User receives it in their chat widget as a new conversation


FEATURE 14: REAL-TIME (AJAX POLLING)

Use WordPress AJAX system throughout.

Frontend (user chat):
- Poll every 3 seconds: actionmhd_get_messages
  - Sends: conversation_id, last_message_id, nonce
  - Returns: array of new messages since last_message_id
- Poll every 5 seconds: actionmhd_get_agent_status
  - Returns: agent name, avatar, online status
- Send typing indicator: actionmhd_set_typing
  - Stored in transient for 5 seconds
- Get typing status: actionmhd_get_typing
  - Returns whether agent is currently typing

Admin Dashboard (agent):
- Poll every 2 seconds: actionmhd_get_conversations_updates
  - Returns: new/updated conversations since last check
- Poll every 2 seconds in open thread: actionmhd_get_messages
  - Same as above, returns new messages
- On tab blur: reduce all polling intervals to 10 seconds
- On tab focus: restore original intervals

All AJAX handlers must:
- Verify nonce with check_ajax_referer()
- Check capabilities (current_user_can or mhd_agent)
- Sanitize all inputs
- Return wp_send_json_success() or wp_send_json_error()


FEATURE 15: USER REGISTRATION & LOGIN

- Chat widget can show login/register form inside the chat 
  before starting (optional, toggle in settings)
- If user is already logged into WordPress, pre-fill their 
  name/email automatically
- Guest chat: assign a unique guest ID via cookie 
  (mhd_guest_id) — lasts 30 days
- Returning guest: match by cookie → restore previous 
  conversation history
- Registered user: full conversation history across sessions
- User data displayed in agent sidebar: login status, 
  member since, last login


FEATURE 16: FIND, SORT & FILTER USERS

Main URL: WP Admin → MyHelpDesk → Users (tab in Conversations or separate)

- List all users who have ever started a conversation
- Columns: Name, Email, Total Conversations, Last Active, 
  Source, WooCommerce grade (if WC active)
- Search by name or email
- Sort by: last active, total conversations, registration date
- Click user → see all their conversations
- Export users list as CSV


FEATURE 17: RICH MESSAGES

Support special message types rendered in the chat widget:

1. Buttons message: message text + array of clickable buttons 
   (each button has a label and URL or action)
2. Card message: image + title + description + button
3. Slider message: multiple cards in a horizontal scrollable row
4. List message: numbered or bulleted list rendered visually
5. Video message: embed a video URL (YouTube/mp4) inline
6. Image message: show uploaded image inline in chat
7. Form message: mini inline form with fields (name, email, 
   select, etc.) — submitted response saved as a message

Admin creates rich messages using a visual builder in the 
message composer (a "+" icon opens a rich message picker).
Rich message JSON stored in messages table, rendered 
dynamically on both admin and frontend.


FEATURE 18: SETTINGS PAGE

Tab 1 — General:
- Enable/disable chat widget sitewide
- Show widget only for: everyone / logged-in users only / 
  guests only
- Exclude widget from specific page IDs (comma-separated)
- Widget language (uses WordPress locale by default)
- Date/time format for message timestamps
- Cookie duration for guest users (days)

Tab 2 — Chat Behavior:
- Welcome message (textarea, supports HTML)
- Offline message (shown when no agents online)
- Pre-chat form: enable/disable, which fields are required
- Department selection in pre-chat: enable/disable
- GDPR consent: enable/disable, consent text
- Auto-open chat after X seconds (0  disabled)
- Proactive message text and delay
- Popup message text and delay
- Subscribe message: enable/disable, field labels
- Follow-up message: enable/disable, message text, delay (hours)
- Chat rating: enable/disable, prompt text
- Allow file uploads: enable/disable
- Max file size (MB)
- Allowed file types (comma-separated extensions)

Tab 3 — Notifications:
- Email notifications: enable/disable per event
- "From" name and "From" email for outgoing emails
- Custom SMTP: enable/disable, host, port, user, pass, encryption
- Email templates: HTML editor for each template 
  (agent new chat, agent new message, user reply, ticket update)
- Desktop notification: enable/disable
- Sound notification: enable/disable, per-event sound toggle

Tab 4 — Design:
- Primary color (color picker)
- Secondary/background color
- Chat header text
- Chat bubble icon (upload or choose default icons)
- Widget width and height
- Font size (small / medium / large)
- Border radius
- Agent avatar: show/hide
- Custom CSS textarea (injected into widget)
- Live preview button (opens preview modal)

Tab 5 — Agents & Departments:
- Default department for new chats
- Auto-assign method per department (round-robin / least-busy / 
  manual)
- Agent away timeout: mark agent as away after X minutes 
  of inactivity
- Max total queue size (if all agents at max chats, 
  show offline form)
- Show agent name in widget header: enable/disable
- Show number of agents online: enable/disable

Tab 6 — Tickets:
- Enable/disable ticket system
- Require login to submit ticket: enable/disable
- Default ticket priority
- Auto-create ticket from offline form: enable/disable
- Ticket subjects: predefined list (comma-separated)

Tab 7 — Knowledge Base:
- Enable/disable KB
- KB page slug (used in shortcode)
- Show KB search in widget before chat: enable/disable
- Number of KB suggestions to show in widget

Tab 8 — Email Piping:
- Enable/disable email piping
- IMAP host, port, username, password, encryption
- Check frequency (minutes, uses WP Cron)
- Delete emails after import: enable/disable
- Test connection button

Tab 9 — WooCommerce:
- Enable/disable WooCommerce integration
- Show orders in agent sidebar: enable/disable
- Customer grade thresholds: 
  Regular (min orders), VIP (min orders)

Tab 10 — Advanced:
- Delete all plugin data on uninstall (toggle, default OFF)
- REST API: enable/disable, API key field (for external access)
- Debug mode: log AJAX errors to WP debug log
- Polling interval override (seconds) — for performance tuning
- Shortcodes reference (read-only table showing all shortcodes)
- System info panel (WP version, PHP version, DB tables status)


SHORTCODES

[mhd_chat_widget]          — Embed chat widget
[mhd_ticket_form]          — Ticket submission form
[mhd_my_tickets]           — User's own ticket list (logged-in)
[mhd_knowledge_base]       — Full KB with search + categories
[mhd_kb_article id"5"]    — Single KB article by ID
[mhd_kb_search]            — Just the KB search bar


REST API ENDPOINTS

Namespace: /wp-json/mhd/v1/
Authentication: API key in header X-MHD-API-Key (from settings)

GET  /conversations                    — List conversations
POST /conversations                    — Create conversation
GET  /conversations/{id}               — Get conversation + messages
POST /conversations/{id}/messages      — Send message
PUT  /conversations/{id}/status        — Update status
GET  /agents                           — List agents
GET  /departments                      — List departments
POST /tickets                          — Create ticket
GET  /tickets/{id}                     — Get ticket details
PUT  /tickets/{id}/status              — Update ticket status
POST /tickets/{id}/replies             — Add ticket reply
GET  /kb/articles                      — List KB articles
GET  /kb/articles/{slug}               — Get KB article by slug


SECURITY REQUIREMENTS

- All AJAX: check_ajax_referer() on every handler
- Admin pages: current_user_can('manage_options') or 
  custom cap 'mhd_agent'
- All inputs sanitized:
  - sanitize_text_field() for text
  - sanitize_email() for emails
  - sanitize_textarea_field() for multiline
  - wp_kses_post() for HTML message content
  - absint() for IDs and integers
- All outputs escaped:
  - esc_html(), esc_attr(), esc_url(), wp_kses()
- All DB queries via $wpdb->prepare() — zero raw queries
- File uploads: validate MIME type with wp_check_filetype(), 
  use wp_handle_upload(), store outside webroot for private files
- Rate limiting on AJAX with transients to prevent flooding
- IMAP password encrypted in options using 
  openssl_encrypt()/openssl_decrypt()
- API key hashed with wp_hash()
- No sensitive data exposed in REST responses without 
  authentication


CODE STANDARDS

- WordPress Coding Standards (WPCS) throughout
- Full OOP: all features in dedicated classes
- Loader class for all add_action / add_filter registrations
- PHPDoc on every function, class, and property
- All strings in __() / _e() with 'myhelpdesk-chat' text domain
- wp_enqueue_scripts for all JS/CSS (no hardcoded script tags)
- wp_localize_script() to pass PHP config to JavaScript
- Activation hook: create tables, set default options
- Deactivation hook: clear WP Cron jobs
- Uninstall hook: optionally delete all data (based on setting)
- Plugin action links (Settings, Docs) in plugins list
- Admin notice on activation with "Setup Guide" link


JAVASCRIPT STANDARDS

Public widget JS:
- Pure vanilla JS (no jQuery dependency)
- No external CDN libraries
- Single IIFE module pattern to avoid global scope pollution
- All AJAX via fetch() API with async/await
- Error handling and graceful fallback if AJAX fails
- Emoji picker: custom built (unicode grid, no library)
- Sound: Web Audio API to generate notification beep 
  (no external audio file needed)

Admin JS:
- jQuery allowed (WP bundled)
- Separate JS files per feature area
- Use wp.ajax for admin requests
- Chart.js (bundled locally) for reports charts
- No inline JavaScript (everything in enqueued files)


BUILD ORDER (STEP BY STEP)

Build in this exact order. After each step is confirmed 
working, move to the next:

Step 1:  Bootstrap file (myhelpdesk-chat.php), constants, 
         plugin headers, class loader
Step 2:  Activator — all 12 DB tables with dbDelta()
Step 3:  Admin menu, settings page skeleton (all 10 tabs, 
         save/retrieve with options API)
Step 4:  Conversations admin list view + single thread view
         (static HTML first, no real data yet)
Step 5:  Frontend chat widget HTML + CSS + basic open/close JS
Step 6:  AJAX handlers: start conversation, send message, 
         get messages (polling), set/get typing indicator
Step 7:  Wire frontend widget to AJAX handlers (real messages)
Step 8:  Agent management page (list, add, edit, remove)
Step 9:  Departments page + auto-assign logic
Step 10: Saved replies (CRUD + "/" trigger in composer)
Step 11: Ticket system (admin view + frontend shortcode)
Step 12: Notifications (bell, email, desktop, sound, tab flash)
Step 13: Knowledge Base (admin CRUD + frontend shortcode + 
         in-widget search)
Step 14: Automations engine (triggers + conditions + actions)
Step 15: Rich messages builder
Step 16: Reports dashboard (Chart.js charts + CSV export)
Step 17: WooCommerce integration sidebar
Step 18: Email piping (IMAP + WP Cron)
Step 19: Direct message + multi-user broadcast
Step 20: REST API endpoints
Step 21: Security audit pass (nonces, escaping, sanitization, 
         prepared statements)
Step 22: Final polish (responsive CSS, settings defaults, 
         activation notice, readme.txt)


START NOW — STEP 1 + STEP 2

Generate the complete, working PHP code for:

1. myhelpdesk-chat.php 
   (plugin headers, constants, require_once all class files, 
    activation/deactivation/uninstall hooks, Loader instantiation)

2. includes/class-mhd-loader.php 
   (collects add_action and add_filter calls, runs them all on 'run()')

3. includes/class-mhd-activator.php 
   (creates all 12 tables using dbDelta(), sets default plugin 
    options, registers custom capability 'mhd_agent')

Output complete, production-ready PHP code.
No stubs. No placeholders. No "// TODO" comments.
After I confirm Step 1+2 work, I will ask for Step 3.


