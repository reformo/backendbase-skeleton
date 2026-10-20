# Nginx HTTP Rate Limiting

Nginx limits general API traffic before PHP-FPM starts application work.
IdentityAndAccess retains its existing contact verification limits in application code.

## Policy

| Setting | Value |
| --- | --- |
| Sustained request rate | 30 requests per second per client IP |
| Burst allowance | 60 excess requests, admitted without delay |
| Shared counter | One IP counter across ExampleApi, UserApi, and SystemAdminApi |
| Counter storage | A 10 MiB shared memory zone in one Nginx instance |
| Methods | All methods, including GET, HEAD, OPTIONS, and write retries |
| Public health routes | Count toward the same request limit |
| Internal readiness | `/internal-ready` has no rate counter; keep its existing network access restrictions |
| Rejection | HTTP 429, `application/problem+json`, `Retry-After: 1`, and `Cache-Control: no-store` |

The burst allowance permits a short increase above the sustained rate. It is not an additional per-second quota.
After the allowance is exhausted, Nginx rejects excess requests without forwarding them to PHP-FPM.
One second permits 30 further requests as the excess count decreases. Other traffic can consume that capacity.

The response has root Problem Details fields:

```json
{
  "type": "about:blank",
  "code": "system/rate-limit-exceeded",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "The API request limit was reached."
}
```

Application verification failures retain their original body and `Retry-After` value.
Keep `fastcgi_intercept_errors` and `proxy_intercept_errors` disabled for application locations.
Nginx rejections occur before authentication and HTTP replay. They do not create an idempotency claim or consume a contact reservation.
Retry a rejected write with its original `Idempotency-Key` and unchanged request after the indicated wait.
Successful replay requests still count as HTTP traffic. Application quotas retain their separate rules.

## Install the configuration

Run these commands from the reviewed checkout on the identified server:

```sh
sudo install -d -m 0755 /etc/nginx/backendbase
sudo install -m 0644 resources/deployment/nginx/http-rate-limit.conf /etc/nginx/backendbase/http-rate-limit.conf
sudo install -m 0644 resources/deployment/nginx/api-rate-limit.conf /etc/nginx/backendbase/api-rate-limit.conf
```

Include the zone once inside `http {}`. Include the server policy inside each API `server {}` block.
Keep the existing TLS, API routing, PHP-FPM upstream, and maintenance configuration.

```nginx
http {
    include /etc/nginx/backendbase/http-rate-limit.conf;

    server {
        # Existing API listener, TLS, routing, and PHP-FPM configuration.
        include /etc/nginx/backendbase/api-rate-limit.conf;
    }
}
```

Clear the key only inside the existing, network-restricted `location = /internal-ready` block:

```nginx
set $apiClientRateKey "";
```

Keep that location's `allow` and `deny` rules. Other locations retain the IP key, including an unrestricted path with the same name.
Do not include the server policy in the separate object-storage CDN server.
Do not override `limit_req` in child locations without retaining this shared policy.
Nginx inherits parent limits only when the child location has no `limit_req` directive.

Add the target's required CORS and security response headers inside `@api-rate-limit` before activation.
That location declares response headers, so ordinary `add_header` inheritance does not retain parent headers.
Match allowed browser origins to the owning API configuration. Expose `Retry-After` when browser clients must read it.
Do not reflect arbitrary `Origin` values.

Validate the complete server configuration before an authorized reload:

```sh
sudo nginx -t
sudo systemctl reload nginx
```

Local repository checks do not install these files or reload a target server.

## Client addresses and instance scope

The key uses Nginx's `$binary_remote_addr`. Forwarded client headers do not change that address by default.
When another trusted proxy precedes Nginx, configure its exact addresses with `set_real_ip_from` and the matching `real_ip_header`.
Never trust forwarded addresses from arbitrary clients. Preserve `$remote_addr` as PHP-FPM's `REMOTE_ADDR`.
The existing contact limiter uses that server parameter too. No PHP address or verification policy changes are required.

Keep PHP-FPM and other application listeners inaccessible to external clients that could bypass Nginx.
Users behind one public IP share the quota. API credentials and different API paths do not create new counters.
Shared memory coordinates workers within one Nginx instance. Separate Nginx instances have separate quotas.
A deployment-wide quota across multiple instances requires a separately selected coordination service.

## Verify and observe

Use the cached Nginx image recorded in `tests/Deployment/Runtime/runtime_services.py`.
Run the isolated check from the repository root:

```sh
python3 tests/Deployment/nginx-rate-limits.py
```

The check uses one new, labelled container, temporary configuration, and a loopback-only port.
It uses no application, database, Redis, or notification services. It removes its owned container after success or failure.
It checks both Nginx configurations, shared limits across APIs and workers, all HTTP methods, burst rejection, and recovery.
It also checks separate clients, untrusted forwarded headers, preserved application errors, and internal readiness under load.
The production deployment rehearsal includes the same policy files. Its mixed workload is paced at 25 requests per second.

Nginx records rejected requests at warning level. Monitor HTTP 429 counts and the Nginx error log.
Use the problem code to distinguish gateway rejection from `identity/verification-limited`.
Do not record credentials, request bodies, or contact values in rate-limit diagnostics.

References: [Nginx request limiting](https://nginx.org/en/docs/http/ngx_http_limit_req_module.html),
[trusted client addresses](https://nginx.org/en/docs/http/ngx_http_realip_module.html),
and [contact verification](contact-verification.md).
