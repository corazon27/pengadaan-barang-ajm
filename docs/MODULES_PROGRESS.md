# Module 1 – Authentication & User Profile API

**Implemented Routes & Methods**
- POST `/api/v1/auth/login` – public
- POST `/api/v1/auth/logout` – `auth:sanctum` (user‑authenticated)
- GET `/api/v1/auth/me` – `auth:sanctum`
- PUT `/api/v1/auth/profile` – `auth:sanctum`

**Token Abilities**
- Tokens carry role claims as abilities: `role:SUPERADMIN`, `role:BUYER_B2B`, `role:BUYER_B2G`.
- Claims are used in authorization policies (e.g., `UserPolicy` checks `role === UserRole::SUPERADMIN`).

**Envelope Format**
- Standard response: `{ success, message, data, errors }`.
- Validation failures → 422, unauthorized → 401, both wrapped in same envelope.

**Key Files Created/Modified**
- `app/Models/User.php` – added `HasApiTokens`.
- `app/Traits/ApiResponser.php` – successResponse / errorResponse.
- `app/Http/Requests/Auth/LoginRequest.php` & `UpdateProfileRequest.php`.
- `app/Http/Resources/UserResource.php`.
- `app/Http/Controllers/Api/Auth/AuthController.php` & `ProfileController.php`.
- `app/Policies/UserPolicy.php`.
- `routes/api.php` – routes under `api/v1`.
- `bootstrap/app.php` – added `apiPrefix('api/v1')` and exception handlers.