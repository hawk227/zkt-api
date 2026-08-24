# Cloudflare Worker migration

This is the production deployment target for the ZKTeco attendance gateway. It replaces Laravel runtime code with a native TypeScript Cloudflare Worker, D1, and Queues.

## Architecture

`ZKTeco ADMS terminal -> /iclock/cdata -> D1 raw attendance event -> Queue -> employee matching -> GET /api/v1/attendance`

The Worker accepts standard iClock/ADMS `ATTLOG` POSTs at `/iclock/cdata?SN=DEVICE_SERIAL&table=ATTLOG`. It acknowledges quickly with `OK`, deduplicates records at write time, and sends new event IDs to a Queue so employee linking is off the device request path.

## First deployment

1. Run `npm install`.
2. Authenticate with `npx wrangler login`.
3. Create a D1 database: `npx wrangler d1 create zkteco-attendance`.
4. Copy the returned database ID into `wrangler.jsonc` for the deployment environment.
5. Create queues: `npx wrangler queues create zkteco-attendance-events` and `npx wrangler queues create zkteco-attendance-events-staging`.
6. Apply schema: `npx wrangler d1 migrations apply zkteco-attendance --remote`.
7. Set the API secret interactively: `npx wrangler secret put ADMIN_API_KEY --env production`.
8. Deploy: `npx wrangler deploy --env production`.

## Device setup

Create the device before configuring the terminal:

```bash
curl -X POST https://YOUR_WORKER/api/v1/devices \
  -H 'X-Attendance-Key: YOUR_SECRET' -H 'Content-Type: application/json' \
  -d '{"serial_number":"DEVICE_SERIAL","name":"Head Office","timezone":"Asia/Dhaka"}'
```

Configure the ZKTeco terminal in ADMS / Cloud Server mode to use `https://YOUR_WORKER/iclock/cdata`. Its serial number must match the registered `serial_number` exactly.

The legacy Laravel app remains in the repository as a reference and local PHP option; deploy this folder for the Cloudflare option.
