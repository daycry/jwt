# Examples

Reference patterns for integrating `daycry/jwt` in real-world applications. Each subfolder is self-contained — copy what you need into your project.

| Folder | Pattern |
|---|---|
| [`middleware/`](middleware/) | `Authorization: Bearer …` filter that decodes once and exposes the parsed token to controllers |
| [`refresh-tokens/`](refresh-tokens/) | Short-lived access token + long-lived refresh token, with rotation |
| [`multi-tenant/`](multi-tenant/) | Per-tenant signing keys with a verifier router |

These files are intentionally not autoloaded — they exist as documentation. Each one starts with a comment block explaining the inputs and assumptions.

> **Per-request configuration:** prefer the immutable `with*()` customisers (`withExpiresAt()`, `withLeeway()`, …) or `clone config('JWT')` over mutating the shared `config('JWT')` singleton, so concurrent requests can't trample each other's settings. See [`docs/advanced.md`](../docs/advanced.md) for the full guidance.
