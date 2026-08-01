# DOKPLOY_STALE_CONTAINER_TRIAGE

## Purpose
Diagnose and fix the "deploy says done, container never changes" failure mode in Dokploy-hosted applications. A deployed app's wire never updates (new commit, stale beacon hash, unchanged endpoints) even though the deploy API reports success.

## When To Use
- An app is deployed via Dokploy (dashboard.itak.live or similar) from a GitHub repo with autoDeploy enabled.
- New commits land on the branch, deployment records show status "done" with errorMessage null, but the live site serves old code.
- The container image ID / beacon hash / build marker never changes across deploys.

## Required Inputs
- Dokploy API key (with admin scope) and dashboard base URL.
- The applicationId of the deployed app.
- A wire marker that MUST change on a real deploy (beacon.js hash, an endpoint response, a page byte count).

## Steps
1. Confirm the wire is stale: hash a static asset (e.g. /assets/beacon.js) before and after a deploy; fetch it 3-5x to rule out replica-pool variance (single hash = single stale replica, not a pool).
2. Fire a real deploy through the API:
   `POST /api/application.deploy` with `{"applicationId":"..."}` and header `x-api-key: <key>`.
   - Control: same call with a GARBAGE key must 401. Stored key 200 + garbage 401 = key is VALID (do not trust an audit that only tried wrong paths).
3. Read the deployment record: `GET /api/trpc/deployment.all?batch=1&input={"0":{"json":{"applicationId":"..."}}}` (URL-encode input).
   - Real build: 60s+ duration, commit SHA in description.
   - Paper no-op: status "done" in 1-6s, errorMessage null, logPath under /etc/dokploy/logs/.
4. Read the app config: `GET /api/trpc/application.one?...` — check serverId, buildServerId, sourceType, branch, buildPath, autoDeploy, replicas.
5. Root-cause check: `GET /api/trpc/server.all?batch=1&input={"0":{"json":null}}`.
   - EMPTY array = Dokploy has NO registered build/runtime server. This is the root cause: deploys are recorded but nothing executes docker build/run. The container never cycles.
6. Try forcing a cycle: `application.update` with `{"applicationId":"...","cleanCache":true}` returns true but still no-op when no server exists. (Config change alone does not recreate the container without a server.)
7. Escalate to a human with the three options:
   A. SSH to the VPS and cycle the container manually (docker ps / docker restart / re-pull), or fix the Dokploy agent.
   B. In the Dokploy dashboard, re-register the server (Settings > Servers > Add, with the agent install), then Redeploy.
   C. Grant SSH (add the ed25519 public key to authorized_keys for a user with docker access) so an agent can cycle it.

## Expected Output
A receipt with: the paper-no-op deployment timestamps + durations, the server.all empty result, the control-test proof the API key is valid, and the stale wire marker before/after. Do NOT claim "deploy verified" until the wire marker actually changes.

## Gotchas
- 1.6s "done" deploys are never real Docker builds.
- REST /api/projects and /api/applications are 404s in some Dokploy versions; tRPC is the real surface (server.all, deployment.all, application.one, application.update).
- SSH "permission denied (publickey)" on root/dokploy with a stored key usually means the key was never installed into authorized_keys, not that the server is down (port 22 timeout would mean down).
- Missing measurement: always record the wire marker BEFORE and AFTER; absence of after-proof means the deploy did not happen.
