# HTTP Contracts

This directory contains HTTP interface definitions shared between services.

The primary artifact will be an OpenAPI specification describing the private
HTTP interface exposed by the Python AI service.

The browser never communicates directly with the AI service.

Browser
→ Next.js
→ Laravel
→ Python
→ Laravel
→ Browser