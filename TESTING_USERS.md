# Testing User Accounts & Credentials

This document provides all pre-configured user credentials across the administrative, staff, and consumer tiers for testing web, mobile, and backend features.

> **Default Password:** `12345678` across all seeded testing accounts unless otherwise noted.

---

## 1. Administrative Accounts (Full Control & Demo Mode)

Use these accounts to access the Admin Panel at [http://localhost:8000/login](http://localhost:8000/login).

| Name | Email | Password | Role / Type | Capabilities & Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@swann.com` | `12345678` | `admin` | Full super administrator permissions: user management, video uploads, subscription pricing, system settings, and database modifications. |
| **Ivan Norris** | `demo@swann.com` | `12345678` | `demo_admin` | Demo Administrator with full read/inspection access across panels, but mutation guard restricts deleting or modifying core settings. |

---

## 2. Staff & Editorial Roles (Content & Live Broadcast Management)

Granular staff roles configured via Spatie RBAC for scoped management in the CMS:

| Name | Email | Password | Role / Type | Permissions & Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **Channel Manager** | `channel.manager@noorcinema.com` | `12345678` | `channel_manager` | Live TV channels, categories, broadcast media, and TV banner management. |
| **Video Editor** | `video.editor@noorcinema.com` | `12345678` | `video_editor` | Movies, TV Shows, Seasons, Episodes, Videos, Clips, Cast & Crew, and Genres. |

---

## 3. Consumer End-Users (Next.js Web & Mobile Apps)

Use these accounts to test authentication, streaming entitlements, paywalls, and profile switching at [http://localhost:3000/login](http://localhost:3000/login).

### A. Subscribed / VIP Members (`is_subscribe = 1`)
These accounts simulate paying members who have active access to premium & restricted content tiers.

| Name | Email | Password | Phone / OTP | Subscription Status | Sub-Profiles Available |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **John Doe** | `john@gmail.com` | `12345678` | `+911234567890` | **Active Subscriber** | Profile 1: Adult (John Doe)<br>Profile 2: Child / Kids |
| **Tristan Erickson** | `tristan@gmail.com` | `12345678` | `+447911123456` | **Active Subscriber** | Profile 1: Adult (Tristan)<br>Profile 2: Child / Kids |
| **Felix Harris** | `felix@gmail.com` | `12345678` | `+12124567890` | **Active Subscriber** | Profile 1: Adult (Felix)<br>Profile 2: Child / Kids |
| **Harry Victoria** | `harry@gmail.com` | `12345678` | `+447911123456` | **Active Subscriber** | Profile 1: Adult (Harry)<br>Profile 2: Child / Kids |
| **Joy Hanry** | `joy@gmail.com` | `12345678` | `+81312345678` | **Active Subscriber** | Profile 1: Adult (Joy)<br>Profile 2: Child / Kids |
| **Deborah Thomas** | `deborah@gmail.com` | `12345678` | `+81312355678` | **Active Subscriber** | Profile 1: Adult (Deborah)<br>Profile 2: Child / Kids |
| **Katie Brown** | `katie@gmail.com` | `12345678` | `+12124467890` | **Active Subscriber** | Profile 1: Adult (Katie)<br>Profile 2: Child / Kids |
| **Stella Green** | `stella@gmail.com` | `12345678` | `+913465756789` | **Active Subscriber** | Profile 1: Adult (Stella)<br>Profile 2: Child / Kids |

---

### B. Free / Unsubscribed Users (`is_subscribe = 0`)
Use these accounts to test upgrade prompts, subscription paywalls, Pay-Per-View checkout modals, and locked video player screens:

| Name | Email | Password | Phone / OTP | Subscription Status | Sub-Profiles Available |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Jorge Perez** | `jorge@gmail.com` | `12345678` | `+496912345678` | **Free Tier (Unsubscribed)** | Adult (Jorge) & Kids |
| **Dorothy Erickson** | `dorothy@gmail.com` | `12345678` | `+12124577890` | **Free Tier (Unsubscribed)** | Adult (Dorothy) & Kids |
| **Lisa Lucas** | `lisa@gmail.com` | `12345678` | `+12124567790` | **Free Tier (Unsubscribed)** | Adult (Lisa) & Kids |
| **Tracy Jones** | `tracy@gmail.com` | `12345678` | `+913465656789` | **Free Tier (Unsubscribed)** | Adult (Tracy) & Kids |

---

## 4. Multi-Profile Testing Matrix

Every user in the database is automatically provisioned with at least two profiles to test the profile switcher and content gating:

1. **Standard / Adult Profile (`is_child_profile = 0`):**
   - Access to full library (PG, PG-13, TV-MA, Unrated content).
2. **Kids Profile (`is_child_profile = 1`, Name: `kids`):**
   - Automatically filters out all restricted content (`is_restricted = 1`).
   - Gated with PIN / parental lock when configured.

---

## 5. API Testing Endpoints

| Action | HTTP Method | Endpoint | Example Body |
| :--- | :--- | :--- | :--- |
| **Email Login** | `POST` | `/api/v3/login` | `{"email": "admin@swann.com", "password": "12345678"}` |
| **OTP Verification** | `POST` | `/api/v3/verify-otp` | `{"mobile": "+911234567890", "otp": 1234}` |
| **Profiles List** | `GET` | `/api/v3/user-profiles` | *(Requires Bearer Token)* |
| **Switch Profile** | `POST` | `/api/v3/switch-profile` | `{"profile_id": 5}` |
