# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# ChatiG — AI Sales Platform

AI-powered sales automation for social-media sellers in Uzbekistan. A shop owner connects their Instagram account; an AI agent answers customers from the shop's real inventory and collects leads into a mini CRM. A Telegram Mini App serves the catalog + checkout to customers. Paid SaaS (subscription per store).

## Stack

- PHP `^8.3` / Laravel `^13.8`, **PostgreSQL 16 + pgvector**, Redis (queue + cache)
- **Pure API backend** — JSON only, no Blade/Inertia/Filament/admin UI. The web dashboard SPA and the Mini App frontend live in **separate repos** and consume `/api/v1`. Never add UI pages here.
- Auth: Sanctum tokens for the dashboard SPA; **phone + SMS OTP** login/registration (no passwords). Mini App customers authenticate via verified Telegram WebApp `initData`.
- OpenAI via direct HTTP client (`OpenAiClient`), `gpt-4o` for sales, `text-embedding-3-small` for embeddings.
- Docker via Laravel Sail (app on **port 8080**). Code style: Pint. Static analysis: Larastan.
- **Tests run on sqlite in-memory** (see `phpunit.xml`); app/queue run on Postgres/Redis. pgvector DDL/queries are guarded by `DB::getDriverName() === 'pgsql'` so sqlite skips them and falls back to keyword search.

## Commands

PHP commands run inside Docker (Windows host; project lives under a OneDrive path with Cyrillic chars — always quote paths):

```bash
docker compose exec laravel.test php artisan test
docker compose exec laravel.test php artisan test --filter SalesAgentTest   # single test/class
docker compose exec laravel.test ./vendor/bin/pint
docker compose exec laravel.test ./vendor/bin/phpstan analyse
```

Run `pint` + `phpstan` before committing.

## Architecture (modular monolith, API-first)

Single Laravel app, strict module boundaries, one deploy. **Do NOT split into microservices.** Runtime processes only: web (webhooks + API), queue worker (all AI calls), scheduler.

**API-first rule:** controllers and the inbound-message job are thin; ALL business logic lives in `app/Services/` and `app/Agents/`. Every client (dashboard SPA, Mini App, future Flutter) calls the same services.

```
app/
├── Agents/
│   ├── SalesAgent.php            # customer conversation; tool-calling loop, MAX_ITERATIONS=5
│   ├── DTO/                      # AgentContext (in), AgentResult (out)
│   └── Tools/                    # the ONLY way the model touches real data
│       ├── Contracts/Tool.php    # name/description/parameters/handle
│       ├── AbstractTool.php      # builds OpenAI function definition()
│       ├── ToolContext.php       # store + conversation + customer passed to every tool
│       ├── SearchInventoryTool / SearchShopInfoTool / SaveLeadTool / ShareCatalogTool
├── Services/
│   ├── Llm/        # LlmClient (DIP) — OpenAiClient | FakeLlmClient; EmbeddingClient — OpenAi | Fake
│   ├── Inventory/  # InventoryService (keyword + pgvector semanticSearch, stock movements), ProductEmbeddingService, ProductImageService
│   ├── ShopFacts/  # ShopFactService + embeddings (address/phone/hours/delivery, semantic lookup)
│   ├── Crm/LeadService.php
│   ├── Orders/OrderService.php
│   ├── Channels/InstagramService.php   # Instagram OAuth + send DM
│   ├── Auth/       # OtpService, Ai/PromptGeneratorService, Ai/AiSettingsService
│   └── Sms/        # SmsSender (DIP) — LogSmsSender (dev) | EskizSmsSender (Eskiz.uz)
├── Jobs/ProcessIncomingInstagramMessage.php   # inbound IG DM pipeline (queued)
├── Http/
│   ├── Controllers/Api/V1/      # dashboard endpoints; MiniApp/ subfolder for Mini App
│   ├── Controllers/Api/V1/Webhooks/InstagramWebhookController.php
│   ├── Middleware/  # ResolveStore, ResolveStoreFromPublicId, VerifyInstagramSignature, VerifyTelegramInitData
│   └── Resources/   # API Resources (JSON envelope)
├── Support/Tenancy.php          # holds the current tenant Store for the request
└── Models/  # Store, User, Channel, Product, ProductImage, StockMovement, Customer,
             # Conversation, Message, Lead, Order, OrderItem, ShopFact, AiConfig, OtpCode
```

Patterns: Strategy (tools), DTO (agent I/O), Adapter (channels), DIP (`LlmClient`, `EmbeddingClient`, `SmsSender`, bound by driver in `AppServiceProvider`).

### Conventions

- **API responses:** controllers extend `ApiController` — use `ok($data, $meta)` / `message($str)`. Envelope is `{ "data": ..., "meta"?: ... }`. Wrap models in `Http/Resources/`.
- **DI bindings** live in `AppServiceProvider::register` and switch on config drivers (`chatig.llm.driver`, `chatig.sms.driver`) — set `LLM_DRIVER`/`SMS_DRIVER`. `Tenancy` is a singleton.
- **Config** is centralized in `config/chatig.php` (OTP, SMS, LLM models, Instagram, Telegram, trial). Read via `config('chatig.*')`.
- **Seller-facing copy is Uzbek** (in code: prompts, SMS, error messages).

## Non-negotiable domain rules

1. **AI never invents facts.** Price/stock/details come ONLY from tool calls (`search_inventory`, `search_shop_info`, `save_lead`, `get_catalog_link`). There is NO tool to change price or grant a discount — the model is structurally unable to.
2. **Multi-tenancy from day one.** Every domain table has `store_id`; models use the `BelongsToStore` trait (global scope + auto-fill on create). The store comes from the `Tenancy` singleton (set by `ResolveStore` middleware from the auth user, or by the inbound job from the resolved channel) — `store_id` is NEVER accepted from client input. To read across stores in a job, use `withoutGlobalScope('store')` then `Tenancy::set()`.
3. **Stock is derived from `stock_movements`** (append-only). `Product.quantity` is recomputed by `InventoryService` via movements (`createProduct` applies initial qty as a movement; `adjustStock` appends one). `updateProduct` intentionally does NOT touch quantity.
4. **Webhooks respond fast:** persist payload, dispatch job, return 200. All OpenAI/Instagram calls happen in queue workers (`ProcessIncomingInstagramMessage`), never in the request cycle.
5. **Human-in-the-loop:** `AiConfig.mode` is `suggest|auto`. In `suggest`, the AI reply is stored as a `suggested` Message for the owner; in `auto` it's sent. If the tool loop doesn't converge (or a tool escalates), `AgentResult.needsHuman` flips the conversation to `needs_human`.
6. **Idempotent inbound:** the job ignores duplicate deliveries by `Message.external_mid`.
7. **Track tokens per message** (`messages.tokens`, `agent_used`, `tool_calls`).
8. **Billing gates AI replies only**: expired subscription stops AI responses; inventory/CRM data stays visible and is never deleted.

## Channels

- **Instagram (built):** Instagram API with **Instagram Login** (NOT Facebook Login + Pages). OAuth: `instagram.com/oauth/authorize` → `api.instagram.com/oauth/access_token` (short) → `graph.instagram.com/access_token` (long); all calls use `graph.instagram.com`. Scopes `instagram_business_basic`, `instagram_business_manage_messages`. Inbound: `GET /api/v1/webhooks/instagram` verifies the handshake; `POST` is `X-Hub-Signature-256` (HMAC-SHA256 of raw body) verified by `VerifyInstagramSignature`, then dispatches the job which resolves channel→store, stores the `Message`, runs `SalesAgent`, and sends (auto) or suggests (suggest).
- **Telegram Mini App (built, catalog only):** one shared platform bot opens the Mini App per store via `t.me/<bot>/app?startapp=<store_public_id>`. Routes under `/api/v1/mini-app/...` are guarded by `VerifyTelegramInitData` (HMAC of WebApp `initData` with the bot token + freshness check) then `ResolveStoreFromPublicId`. There is **no Telegram AI chat yet** — Mini App = catalog + order placement only.

## Testing

- Agents/tools are unit-tested against `FakeLlmClient` / `FakeEmbeddingClient` — **never hit OpenAI**. Set `LLM_DRIVER=fake` is implicit via test config; SMS uses `FakeSmsSender` / `LogSmsSender`.
- Services (Inventory, Lead, ShopFact) get unit tests with the DB; feature tests cover HTTP endpoints, webhook signature/secret verification, fast-ack, and Mini App initData auth (`tests/Feature/MiniApp/BuildsInitData.php` helper signs valid initData).
- Tests run on sqlite — keep pgvector queries behind a `pgsql` driver guard so they fall back to keyword search under test.

## Out of scope until after launch

Telegram AI chat channel, Flutter app, marketing site, online payment integration (Payme/Click/Uzum — MVP billing is payment link/receipt + manual admin confirmation), analytics dashboards, multi-user-per-store roles. Deliberately NOT used: Repository pattern, CQRS, event sourcing, multi-LLM providers.
