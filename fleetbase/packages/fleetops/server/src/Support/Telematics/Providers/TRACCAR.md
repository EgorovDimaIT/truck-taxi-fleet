# Traccar connector — setup guide

This connector is **additive**: it registers a new provider key (`traccar`) in
`config/telematics.php` and adds `TraccarProvider.php` next to the other native
providers. No existing routes, controllers, or providers were modified or
removed.

Traccar is push-based — Fleetbase does not poll it. You configure either a
**Traccar Server** (position forwarding) or the **Traccar Client** mobile app
to send positions to Fleetbase's existing generic telematics webhook:

```
POST https://<your-fleetbase-host>/webhooks/telematics/traccar
```

## 1. Create the integration in Fleetbase

1. In Fleetbase, go to **FleetOps → Telematics** and add a new integration.
2. Choose **Traccar** from the provider list.
3. (Optional) Set a **Shared Secret** — if set, incoming requests must send it
   back in the `X-Webhook-Signature` header.
4. Save. Fleetbase will show you the integration's UUID — you'll need it below
   as `<telematic-uuid>` so incoming webhooks resolve to *this* integration
   (skip this if you set a Shared Secret and only ever link one Traccar
   integration — Fleetbase can match by signature alone in that case).

Your final webhook URL:

```
https://<your-fleetbase-host>/webhooks/telematics/traccar?telematic=<telematic-uuid>
```

## 2a. Option A — Traccar Server "Position Forwarding"

This is the same JSON contract GeoPulse uses for its own Traccar source, so a
server already forwarding to GeoPulse can additionally (or instead) forward to
Fleetbase.

Edit `traccar.xml` on your Traccar Server:

```xml
<entry key='forward.enable'>true</entry>
<entry key='forward.type'>json</entry>
<entry key='forward.url'>https://<your-fleetbase-host>/webhooks/telematics/traccar?telematic=<telematic-uuid></entry>
<entry key='forward.header'>X-Webhook-Signature: YOUR_SHARED_SECRET</entry>
```

Restart Traccar Server after saving. Full forwarding options:
https://www.traccar.org/forward/

## 2b. Option B — Traccar Client app (direct, no server)

Point the Traccar Client app's server URL directly at the same Fleetbase
webhook URL from step 1. The app sends flat OsmAnd-style fields
(`id`, `lat`, `lon`, `timestamp`, `speed`, `bearing`, `altitude`, `batt`, ...),
which the connector also understands natively — no Traccar Server required in
this mode.

## What gets mapped

| Fleetbase field | Source (Server JSON)                  | Source (Client / flat) |
|---|---|---|
| location         | `position.latitude/longitude`         | `lat`/`lon` |
| speed (km/h)     | `position.speed` (knots → km/h)       | `speed` |
| heading          | `position.course`                     | `bearing`/`course` |
| altitude         | `position.altitude`                   | `altitude` |
| battery          | `position.attributes.batteryLevel`    | `batt`/`battery` |
| ignition         | `position.attributes.ignition`        | — |
| odometer         | `position.attributes.totalDistance`   | — |
| occurred_at      | `position.fixTime`/`deviceTime`/`serverTime` | `timestamp` |
| device identity  | `device.uniqueId`/`device.id`         | `id`/`deviceId` |

## Optional: device discovery from a Traccar Server

If you also fill in **Traccar Server URL** (+ username/password) in the
integration's credentials, Fleetbase can call that server's `GET /api/devices`
to list and link devices — useful for pre-populating device names before the
first position arrives. This is entirely optional; push-only setups can leave
these fields blank.
