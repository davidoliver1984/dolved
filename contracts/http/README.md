# HTTP Contracts

- `retrieval-call/rc1/` defines ADR-0018's synchronous, purpose-scoped
  Laravel-to-Python retrieval protocol.

This directory contains HTTP interface definitions shared between services.

The primary artifact will be an OpenAPI specification describing the private
HTTP interface exposed by the Python AI service.

The browser never communicates directly with the AI service. It normally calls
Laravel directly for platform and authentication APIs. Next.js may selectively fetch
from Laravel while rendering, but is not a mandatory backend-for-frontend and does
not make independent authorisation decisions.

Browser
→ Laravel
→ Python
→ Laravel
→ Browser

Selective rendering path:

Browser
→ Next.js
→ Laravel
→ Next.js
→ Browser
