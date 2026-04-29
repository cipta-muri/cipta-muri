## 🧪 e-Bank Sampah Cipta Muri — Digital Waste Bank Management System

**Tech Stack:** PHP 8.2 · Laravel 12 · Filament 3 · React 19 · TypeScript · Inertia.js · Tailwind CSS · Radix UI · Framer Motion · Google Gemini AI · Laravel Reverb (WebSocket) · Spatie Permissions · Laravel Sanctum · Vite · PestPHP · SQLite / MySQL

---

**Description:**
A full-stack digital waste banking platform built for Cipta Muri, a community recycling program in Central Java, Indonesia. The system digitizes the entire lifecycle of waste collection operations — from citizen account registration and recyclable deposits to financial withdrawals and gold savings integration. It replaces manual ledger-based processes with a real-time, role-aware web application featuring a Filament-powered admin panel and an animated React public site. A built-in AI assistant (powered by Google Gemini) provides contextual guidance to both administrators and community members directly within the interface.

---

**Impact:**
- Digitized waste deposit and financial transaction workflows for a community-scale recycling program, eliminating manual double-entry bookkeeping
- Enabled real-time balance and point tracking across multiple waste types, removing reconciliation delays for program administrators
- Integrated gold savings (Pegadaian) withdrawal channel alongside cash and bank transfer, expanding financial inclusion options for participants
- Delivered a structured audit trail with full transaction reversal support, reducing data recovery risk for financial records
- Embedded a conversational AI assistant into the admin panel to lower operational onboarding friction and reduce support overhead

---

**Key Contributions & Features:**
- **Multi-role access control** via Spatie Laravel Permissions (Super Admin / Admin / User) with Filament UI enforcement
- **Waste deposit engine** with automatic weight-to-balance and weight-to-points conversion per waste category, supporting donation mode for non-member submissions
- **Financial operations module** implementing soft double-entry: deposits, validated withdrawals, and atomic transaction reversal on record deletion
- **Pegadaian gold savings integration** — tracks existing accounts and supports direct transfers to the national pawning service
- **AI assistant panel** built on `@google/genai` (Gemini), injected with domain knowledge about member counts and operations, rendered with animated streaming UI via Framer Motion
- **Real-time event broadcasting** via Laravel Reverb and Laravel Echo React for live dashboard updates
- **Comprehensive export system** (Excel, CSV, PDF) using `filament-excel` and `filament-export` with configurable column selection
- **Animated public-facing React site** — hero, program overview, how-it-works, testimonials, and contact — built with Radix UI primitives and Framer Motion transitions
- **ULID primary keys** across all database entities for sortable, URL-safe, collision-resistant identifiers
- **Test suite** using PestPHP with Laravel plugin; code quality enforced via ESLint, Prettier, and TypeScript strict mode
