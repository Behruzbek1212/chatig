# ChatiG — AI Sales Platform

AI-powered sales automation platform for social-media sellers in Uzbekistan (Instagram channel built; Telegram later). A shop owner connects their Instagram account; an AI agent answers customers from the shop's real inventory and collects leads into a mini CRM. Paid SaaS from day one (subscription per store).

## Stack

- PHP 8.5 / Laravel 13, MySQL 8.4, Redis (queue + cache + debounce timers)
- Tests run on sqlite in-memory (see phpunit.xml); app/queue run on MySQL/Redis via Sail
- Docker via Laravel Sail (`docker compose up -d`, app on **port 8080**)
- OpenAI API via `openai-php/laravel` (`gpt-4o` for sales conversation, `gpt-4o-mini` for intent detection)
- **This repo is a pure API backend** — no Blade views, no Inertia, no Filament. JSON only.
- Frontend (web dashboard) is a **separate project/repo** (Vue SPA or similar) consuming `/api/v1` — never add UI pages here.
- Admin needs in MVP are served via the same API (admin-role endpoints) or artisan commands — no admin UI package in this repo.
- Code style: Pint. Static analysis: Larastan.
- Future clients (do NOT build now): Flutter mobile app, TG Mini App frontend, marketing site

## Commands

All PHP commands run inside Docker:

```bash
docker compose exec laravel.test php artisan <cmd>
docker compose exec laravel.test php artisan test
docker compose exec laravel.test ./vendor/bin/pint
docker compose exec laravel.test ./vendor/bin/phpstan analyse
```

Windows host; project lives under OneDrive path with Cyrillic chars — always quote paths.

## Architecture (modular monolith, API-first)

Single Laravel app, strict module boundaries, one deploy. Do NOT split into microservices. Processes are separated at runtime only: web (webhooks + API + dashboard), queue worker (all AI calls), scheduler.

**API-first rule:** every client (TG bot, separate web dashboard SPA, future Flutter app, TG Mini App) consumes the same business logic. Controllers and bot handlers are thin; ALL business logic lives in `app/Services/` and `app/Agents/`. The bot calls the same services the API does — never duplicate logic per client.

**Auth & API conventions for external SPA clients:** Sanctum token auth, CORS configured for the dashboard origin, consistent JSON envelope via API Resources, versioned routes (`/api/v1/...`). Breaking API changes require a new version — external frontends deploy independently.

```
app/
├── Agents/                  # AI layer (Strategy pattern)
│   ├── Contracts/Agent.php  # handle(AgentContext): AgentResult
│   ├── AbstractAgent.php    # OpenAI call + tool-calling loop + retry
│   ├── IntentDetectionAgent.php   # gpt-4o-mini: intent + language
│   ├── SalesAgent.php             # main customer conversation (tools)
│   ├── OrderCollectorAgent.php    # collects name/phone/address
│   ├── ProductEntryAgent.php      # photo+voice/text → product card draft
│   ├── AgentRegistry.php          # intent → agent (Factory)
│   └── DTO/                       # AgentContext, AgentResult, ToolCall
├── Services/
│   ├── Llm/Contracts/LlmClient.php  # DIP: agents depend on this, never on OpenAI directly
│   ├── Llm/OpenAiClient.php
│   ├── Inventory/InventoryService.php   # search, product CRUD, stock movements
│   ├── Crm/LeadService.php
│   ├── Orders/OrderService.php
│   └── Channels/InstagramService.php    # Instagram API with Instagram Login (OAuth + send DM); TelegramChannel (later)
├── Jobs/
│   ├── ProcessIncomingInstagramMessage.php  # inbound IG DM pipeline (queued — webhook returns 200 fast)
│   └── SendChannelMessage.php       # (later) outbound with retry for other channels
├── Http/Controllers/
│   ├── Webhooks/InstagramWebhookController.php  # GET verify + POST receive (signature-checked); Telegram (later)
│   └── Api/V1/      # versioned REST API (Sanctum auth) — dashboard SPA, mini app, Flutter
└── Models/  # Store, User, Channel, Product, StockMovement, Customer, Conversation, Message, Lead, Order, Subscription
```

Patterns in use: Strategy (agents), Factory/Registry (AgentRegistry), DTO (typed agent I/O), Adapter (channels), DIP (LlmClient). Deliberately NOT used for MVP: Repository pattern, CQRS, event sourcing, multi-LLM providers.

## Non-negotiable domain rules

1. **AI never invents facts.** Price, stock, product details come ONLY from tool calls (`search_inventory`, `get_product_details`, `create_lead`, `create_order_draft`, `escalate_to_human`). There is NO tool to change prices or grant discounts — the AI must be structurally unable to do it. Hallucinated price = dead product.
2. **Multi-tenancy from day one.** Every domain table has `store_id`; models use the `BelongsToStore` trait/global scope. Never query across stores. One DB, no DB-per-tenant.
3. **Stock is derived from `stock_movements`** (append-only in/out records), never written directly to a column. Sellers must be able to audit why a count changed.
4. **Webhooks respond < 100ms**: persist payload, dispatch job, return 200. All OpenAI calls happen in queue workers, never in the request cycle.
5. **Human-in-the-loop:** conversations have `mode: suggest|auto`. New stores start in `suggest` (AI reply goes to the owner with ✅ send / ✏️ edit buttons). `escalate_to_human` flips conversation to `needs_human` + notifies owner.
6. **Debounce incoming messages:** customers send bursts ("aka" / "rtx 4060" / "bormi"). Wait 3–5s (Redis timer reset per message), merge, then one AI call.
7. **Track tokens per message** (`messages.tokens`, agent_used) — per-store cost analytics later.
8. **Billing gates AI replies only**: expired subscription stops AI responses; inventory/CRM data stays visible and is never deleted.

## Bot topology

- **Platform bot** (`/webhooks/telegram/platform`, ours, single): onboarding wizard (register store → BotFather token instructions → owner pastes token), quick owner actions, notifications. Conversation-state machine kept in Redis/DB (`awaiting_bot_token`, `adding_product`, …).
- **Per-store bots** (`/webhooks/telegram/{channel_uuid}`): each shop creates its own bot via BotFather and pastes the token; we validate via `getMe`, call `setWebhook` with a `secret_token`, store the token encrypted. Customers talk to the store's bot; AI answers there.
- Local dev needs a public HTTPS tunnel for webhooks (ngrok/cloudflared).

## Language & market context

- Customers write mixed Uzbek/Russian, informal ("narxi qancha aka", "skolko stoit"). Agents must handle both; reply in the customer's language.
- Local sales culture includes haggling — the AI acknowledges politely but never changes price (no tool for it).
- Payments (Payme/Click/Uzum) are NOT integrated in MVP; subscription billing = payment link/receipt + manual admin confirmation (admin API endpoint / artisan command).
- UI копи for sellers: Uzbek first. "Sklad" = inventory/warehouse, "lead" = so'rov.

## MVP scope guard

In scope (this repo): TG bot (customer AI + owner onboarding), REST API v1 covering inventory, CRM, conversations, settings, subscription (for the external dashboard SPA), admin-role endpoints, suggest/auto modes, trial → paid subscription.
In scope (separate repo): web dashboard SPA consuming the API.
Out of scope until after launch: Telegram bot channel, TG Mini App checkout, Flutter app, marketing site, online payment integration, analytics dashboards, multi-user-per-store roles.

## Instagram integration (built)

Uses **Instagram API with Instagram Login** (NOT Facebook Login + Pages). Shop owners log in with their Instagram Business/Creator account directly — no Facebook Page. OAuth: `instagram.com/oauth/authorize` → `api.instagram.com/oauth/access_token` (short-lived) → `graph.instagram.com/access_token` (long-lived); all resource/messaging calls use `graph.instagram.com`. Scopes: `instagram_business_basic`, `instagram_business_manage_messages`. Config in `config/chatig.php` (`instagram.*`), creds via `INSTAGRAM_*` env.

Inbound flow: `GET /api/v1/webhooks/instagram` verifies the handshake (`hub.mode`/`hub.verify_token`/`hub.challenge`); `POST` is `X-Hub-Signature-256`-verified (HMAC-SHA256 of raw body with app secret) then dispatches `ProcessIncomingInstagramMessage`. The job resolves channel→store (Tenancy), stores the inbound `Message`, runs `SalesAgent` (tool-calling loop: `search_inventory`, `save_lead`), then in `auto` mode sends the reply via `InstagramService::sendMessage` and in `suggest` mode stores it as a `suggested` message for the owner. Idempotent on `message.mid`.

## Testing

- Agents are unit-tested against a fake `LlmClient` (never hit OpenAI in tests).
- Services (InventoryService etc.) get feature tests with the real DB.
- Webhook controllers: test signature/secret verification and fast-ack behavior.
- Run `pint` + `phpstan` before committing.
