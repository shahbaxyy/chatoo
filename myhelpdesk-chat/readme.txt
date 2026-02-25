=== MyHelpDesk Chat ===
Contributors: myhelpdesk
Tags: live chat, help desk, customer support, chat widget, tickets
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A full-featured Live Chat & Help Desk plugin for WordPress. Manage conversations, tickets, knowledge base, agents, departments, and more — all from your dashboard.

== Description ==

MyHelpDesk Chat is a comprehensive customer support solution built natively for WordPress. It provides a modern live chat widget, a complete ticketing system, a knowledge base, and powerful agent management tools — all without any external dependencies or AI integrations.

**Key Features:**

* **Live Chat Widget** — A beautiful, customizable floating chat widget with real-time messaging, pre-chat forms, emoji support, file uploads, and typing indicators.
* **Conversations Dashboard** — Split-view admin interface to manage all customer conversations with filters, search, and real-time updates.
* **Ticket System** — Full ticket management with priorities, assignments, internal notes, and customer-facing forms.
* **Knowledge Base** — Create and organize help articles with categories, search, and in-widget article suggestions.
* **Agent Management** — Manage support agents with roles, departments, online status, and performance tracking.
* **Departments** — Organize your team with auto-assignment rules (round-robin, least-busy, or manual).
* **Saved Replies** — Create reusable message templates accessible via "/" shortcut in the chat composer.
* **Automations** — Rule-based triggers and actions to automate common workflows.
* **Reports & Analytics** — Visual charts and performance metrics with CSV export.
* **Notifications** — Desktop, email, sound, and in-dashboard notifications.
* **WooCommerce Integration** — View customer orders and profiles directly from the chat sidebar.
* **Email Piping** — Import support emails as conversations via IMAP.
* **REST API** — External access to conversations, tickets, and KB articles.
* **Shortcodes** — Embed chat, tickets, and knowledge base anywhere on your site.

**Shortcodes:**

* `[mhd_chat_widget]` — Embed chat widget
* `[mhd_ticket_form]` — Ticket submission form
* `[mhd_my_tickets]` — User's own ticket list
* `[mhd_knowledge_base]` — Full KB with search + categories
* `[mhd_kb_article id="5"]` — Single KB article by ID
* `[mhd_kb_search]` — KB search bar

== Installation ==

1. Upload the `myhelpdesk-chat` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **MyHelpDesk → Settings** to configure the plugin.
4. Add agents via **MyHelpDesk → Agents**.
5. The chat widget will automatically appear on your site's frontend.

== Frequently Asked Questions ==

= Does this plugin require any external service? =

No. MyHelpDesk Chat runs entirely on your WordPress installation with no external dependencies.

= Does it support WooCommerce? =

Yes. When WooCommerce is active, agents can see customer order history directly in the chat sidebar.

= Can guests use the chat without registering? =

Yes. Guests are tracked via cookies and their conversation history is preserved for returning visitors.

= Is the chat widget customizable? =

Yes. You can customize colors, dimensions, fonts, the chat header, welcome messages, and more from the Design settings tab.

== Changelog ==

= 1.0.0 =
* Initial release.
* Live chat widget with real-time messaging.
* Admin conversations dashboard with split-view.
* Ticket management system.
* Knowledge base with search.
* Agent and department management.
* Saved replies with "/" trigger.
* Automation engine with triggers and conditions.
* Reports with Chart.js visualizations.
* Notification system (desktop, email, sound).
* WooCommerce integration.
* Email piping via IMAP.
* REST API endpoints.
* Full settings page with 10 configuration tabs.
