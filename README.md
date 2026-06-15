# Contabo Cloud Compute Provisioning Module for WHMCS

[![WHMCS Version](https://img.shields.io/badge/WHMCS-8.x%20or%20higher-blue.svg)](https://docs.whmcs.com/)
[![Contabo API Version](https://img.shields.io/badge/Contabo%20API-v1%20%2F%201.0.0-orange.svg)](https://api.contabo.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

An enterprise-grade provisioning engine built for WHMCS 8.x+ that integrates seamlessly with the **Contabo Compute Management REST API (v1)**. This module automates the entire infrastructure delivery lifecycle, empowering admins with granular product controls and clients with self-service cloud operations.

---

## 🚀 Key Features

### 🛠️ Admin Features

- **Full Lifecycle Provisioning:** Automate server creation, suspension, unsuspension, and complete termination based on automated payment logic.
- **Granular Infrastructure Management:** Perform direct action controls including Power On, Power Off, ACPI Shut Down, and Force Reboot.
- **Server Customization:** Dynamically modify individual instance Display Names to align database states with Contabo.
- **Telemetry & Network Audits:** View comprehensive server status data flags, assigned MAC addresses, and network details inside the admin services area.
- **VPS Snapshot Management:** Provision, list, delete, and revert system targets using localized snapshots _(VPS Packages Only)_.
- **OS Distribution Rebuilds:** Trigger a hard formatting array on a live instance utilizing chosen alternative OS distribution images.
- **Task Log Histories:** Access structural client activity audit history trails natively from the module interface.
- **Product Field Assignments:** Map custom fields globally to isolate distinct Regions, Plans, and Operating System images per WHMCS product shell.
- **Configurable Hostname Profiles:** Define dynamic default hostname prefixes applied automatically on instantiation hooks.
- **Automated Transactions Mailers:** Fire real-time WHMCS system notifications instantly when instances are created or rebuilt.

### 👥 Client Area Features

- **Power Control Matrix:** Client-driven execution of Power On, Power Off, ACPI Shut Down, and Force Reboots.
- **Real-time Diagnostics:** Instant visibility into instance metadata, assigned IPs, runtime states, and region parameters.
- **Self-Service Backups:** Secure snapshot capture, restoration checkpoints, and automated storage drops.
- **On-Demand Operating System Reinstalls:** Rebuild cloud environments using a curated drop-down list of standard operating system images.
- **Audit Transparency:** Read recent task logs and lifecycle actions executed on their specific instance.
- **Event Notifications:** Receive immediate confirmation emails once system creation or rebuild operations clear the queue.

---

## 📋 Requirements

- **WHMCS Version:** `8.x` or higher (Built using the modern PHP Capsule database layer and localAPI wrappers).
- **PHP Runtime:** `7.4` to `8.2+`
- **Contabo API Access:** Active Contabo Customer Control Panel API credentials (OAuth2 Client ID, Client Secret, API User, API Password).
- **System Extensibility:** Open outbound network communication access on ports `443` to both `auth.contabo.com` and `api.contabo.com`.

---

## ⛓️ Architectural API Endpoints Mapping Reference

This module interfaces natively with the **Contabo v1 API Engine** utilizing structural OAuth2 client-credentials grant authentications. The downstream endpoint routes mapped per internal method call are outlined below:

### Core Environment Gateways

- **Identity Token Domain:** `POST https://auth.contabo.com/auth/realms/contabo/protocol/openid-connect/token`
- **Resource Base Path:** `https://api.contabo.com/v1`

### Lifecycle Hook Actions Mapping

| WHMCS Module Method Hook                 | Trigger Action Method | Targeted Contabo API Endpoint                                        |
| :--------------------------------------- | :-------------------- | :------------------------------------------------------------------- |
| `contabo_CreateAccount`                  | **POST**              | `/v1/compute/instances`                                              |
| `contabo_SuspendAccount`                 | **POST**              | `/v1/compute/instances/{instanceId}/actions/stop`                    |
| `contabo_UnsuspendAccount`               | **POST**              | `/v1/compute/instances/{instanceId}/actions/start`                   |
| `contabo_TerminateAccount`               | **POST**              | `/v1/compute/instances/{instanceId}/cancel`                          |
| `contabo_PowerOn`                        | **POST**              | `/v1/compute/instances/{instanceId}/actions/start`                   |
| `contabo_PowerOff`                       | **POST**              | `/v1/compute/instances/{instanceId}/actions/stop`                    |
| `contabo_Shutdown`                       | **POST**              | `/v1/compute/instances/{instanceId}/actions/shutdown`                |
| `contabo_Reboot`                         | **POST**              | `/v1/compute/instances/{instanceId}/actions/restart`                 |
| `contabo_ClientArea` (Rebuild)           | **PUT**               | `/v1/compute/instances/{instanceId}`                                 |
| `contabo_ClientArea` (Snapshots List)    | **GET**               | `/v1/compute/instances/{instanceId}/snapshots`                       |
| `contabo_ClientArea` (Create Snapshot)   | **POST**              | `/v1/compute/instances/{instanceId}/snapshots`                       |
| `contabo_ClientArea` (Delete Snapshot)   | **DELETE**            | `/v1/compute/instances/{instanceId}/snapshots/{snapshotId}`          |
| `contabo_ClientArea` (Rollback Snapshot) | **POST**              | `/v1/compute/instances/{instanceId}/snapshots/{snapshotId}/rollback` |
| `contabo_ClientArea` (Audit History)     | **GET**               | `/v1/compute/instances/actions/audits`                               |
| `Helper::getCachedImages` (OS Images)    | **GET**               | `/v1/compute/images`                                                 |
| `contabo_AdminServicesTabFields`         | **PATCH**             | `/v1/compute/instances/{instanceId}`                                 |

---

## 🛠️ Installation & Setup

1. **Upload Module Code:** Clone or upload the repository directory structure directly into your WHMCS directory space at `/modules/servers/contabo/`. Ensure all directory permission boundaries are respected.
2. **Database Field Interfacing:**
   - Navigate to **System Settings > Products/Services > Products**.
   - Edit your desired Contabo product profile, and select the **Custom Fields** configuration header.
   - Add a new tracking field configured exactly as named: `instanceId`.
   - Set the Field Type parameter to `Text` and configure the checkbox attributes to **Admin Only**.
3. **Module Parameters Registration:**
   - Switch directly to the **Module Settings** panel on your target product.
   - Choose **Contabo Cloud Compute** from the drop-down module list.
   - Populate your unique `ClientId`, `ClientSecret`, `ApiUser` (Email entry), and `ApiPassword` values retrieved from your Contabo Account Panel under the Security setting context.
   - Input default presets for regions (`eu`, `us-central`, etc.) along with target infrastructure base product codes.

---

## 🛡️ Security Implementation Matrix

To ensure compliance within critical enterprise payment operations, the engine integrates strict systemic defense configurations:

- **CSRF Defenses:** Enforces native validation checks on all incoming multi-tenant payload requests via token injection mappings (`checkToken()`).
- **Input Injection Mitigation:** Strict type-casting algorithms and precise regular expressions (`preg_replace`) eliminate parameter pollution vectors across API pathways.
- **Information Disclosure Prevention:** Suppresses detailed API core stack trace exposures in front-facing panels, safely processing raw exception errors through WHMCS internal system module logs.
- **Rate-Limit Optimization:** Implements local database data caches for resource endpoints (such as `/v1/compute/images`) to maintain maximum processing speeds while completely mitigating API rate limits.

---

## 📄 License

This provisioning integration module framework is open-source software licensed under the terms of the **[MIT License](LICENSE)**.
