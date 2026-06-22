# Asterisk Deployment — RATIB Contact Center

## Prerequisites

- Asterisk 18+ with PJSIP, WebRTC (WSS), AMI enabled
- Firewall: UDP 10000-20000 (RTP), TCP 5038 (AMI), TCP 8089 (WSS)

## Install

1. Copy configs to `/etc/asterisk/`:
   - `extensions_rcc.conf`
   - `queues_rcc.conf`
   - `pjsip_rcc.conf`
   - `rtp_rcc.conf`

2. Add to `extensions.conf`:
   ```
   #include extensions_rcc.conf
   ```

3. Add to `queues.conf`:
   ```
   #include queues_rcc.conf
   ```

4. Merge `pjsip_rcc.conf` into `pjsip.conf` or `#include pjsip_rcc.conf`

5. Configure AMI in `manager.conf`:
   ```
   [rcc]
   secret = YOUR_AMI_SECRET
   read = all
   write = all
   ```

6. Set environment on RCC server:
   ```
   RCC_AMI_HOST=pbx.internal
   RCC_AMI_PORT=5038
   RCC_AMI_USER=rcc
   RCC_AMI_PASS=YOUR_AMI_SECRET
   RCC_SIP_WSS_URI=wss://pbx.rateb.sa:8089/ws
   ```

7. Start workers:
   ```bash
   php bin/rcc-voice-worker.php
   php bin/rcc-realtime-hub.php
   ```

8. Systemd (optional): use `bin/rcc-realtime-hub.service` and create similar unit for `rcc-voice-worker.php`.

## Tenant isolation

- Dialplan contexts: `rcc-ivr-tenant-{id}`, `rcc-tenant-{id}`
- PJSIP endpoints: `agent{tenantId}_{extension}`
- Channel variables: `RCC_TENANT_ID`, `RCC_CALL_ID`, `RCC_AGENT_ID`

## Recording

Add to queue dialplan or `rcc-queue` context:
```
MixMonitor(/var/spool/asterisk/monitor/${UNIQUEID}.wav,b)
```
