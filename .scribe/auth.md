# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_JWT_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Get a JWT token by calling `POST /api/v1/auth/login` with email and password. Use the `access_token` as Bearer token.
