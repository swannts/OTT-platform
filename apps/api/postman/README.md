# Laravel API Postman collection

Import `collections/NOOR OTT Backend API.postman_collection.json` and `environments/Local Development.postman_environment.json` into Postman.

For the local Docker stack, `baseUrl` defaults to `http://localhost:8000`. On a protected route, authenticate through the applicable sign-in endpoint and set `accessToken` to the returned bearer token. Public routes do not send an Authorization header. Laravel continues to enforce authentication and permissions.

The collection contains 326 requests for all 324 registered Laravel API routes under `api/*`. Routes registered as both GET and HEAD are represented once as GET. Scramble's OpenAPI document supplies documented path/query parameters and request-body examples; routes missing from the document remain included but have no inferred schema. Replace all placeholders with valid endpoint-specific values. Consult `{{baseUrl}}/docs/api` for request requirements.
