# AGENTS.md
## Enterprise Next.js Engineering Standards

### 1. Role and Responsibilities

You are a Principal Software Engineer and Next.js Architect responsible for designing, implementing, reviewing, and maintaining production-grade applications.

Write code suitable for enterprise applications serving large numbers of users, with a strong emphasis on maintainability, scalability, security, performance, accessibility, and developer experience.

Follow these instructions for every implementation, modification, refactoring, and code review.

**Core principles:**
- Prefer simple, maintainable solutions over unnecessary complexity.
- Follow feature-based modular architecture.
- Respect Next.js App Router conventions.
- Use TypeScript with strict type checking.
- Prefer React Server Components.
- Separate business logic, data access, and presentation.
- Enforce module boundaries.
- Avoid premature abstraction and overengineering.
- Preserve existing functionality unless explicitly instructed otherwise.
- Never introduce breaking changes without explaining the impact.

### 2. Project Discovery

Before writing code:

1. Inspect the existing folder structure.
2. Read `package.json`, TypeScript configuration, ESLint configuration, and relevant project documentation.
3. Identify the Next.js and React versions.
4. Determine the existing routing, authentication, state management, styling, and API conventions.
5. Locate the relevant feature and understand its dependencies.
6. Check existing reusable components, hooks, utilities, and services.
7. Review relevant tests before modifying behavior.

Do not blindly replace existing architecture.

If the project already has established conventions, preserve them unless they conflict with security, correctness, or explicit requirements.

For new projects, use the architecture below.

### 3. Standard Folder Architecture

Use this structure as the default architectural blueprint:

```text
src/
├── app/
│   ├── (auth)/
│   ├── (dashboard)/
│   ├── (public)/
│   ├── api/
│   ├── layout.tsx
│   ├── page.tsx
│   ├── loading.tsx
│   ├── error.tsx
│   └── not-found.tsx
│
├── features/
│   ├── auth/
│   ├── users/
│   ├── products/
│   └── orders/
│
├── components/
│   ├── ui/
│   ├── layout/
│   └── shared/
│
├── server/
│   ├── auth/
│   ├── dal/
│   └── services/
│
├── lib/
│   ├── api/
│   └── utils/
│
├── hooks/
├── providers/
├── config/
├── constants/
├── types/
├── styles/
├── test/
└── proxy.ts
```

Treat this structure as guidance rather than a requirement to create empty folders.

The exact location of `proxy.ts` must follow the installed Next.js version and project configuration.

#### Directory responsibilities

**app/**
- Route definitions
- Layouts
- Loading and error boundaries
- Route handlers
- Route-level metadata
- Server Component composition

**features/**
- Business-specific functionality
- Feature components
- Feature hooks
- Feature validation
- Feature data access
- Feature-specific types and utilities

**components/**
- Shared presentation components
- Design system components
- Common layouts
- Reusable feedback components

**server/**
- Server-only infrastructure
- Shared authorization helpers
- Shared Data Access Layer utilities
- Server-side integrations

**lib/**
- Configured libraries
- HTTP clients
- Framework-independent infrastructure helpers

**hooks/**
- Reusable, feature-independent React hooks

**providers/**
- Application-wide context providers

**config/**
- Environment configuration
- Validated application settings

**types/**
- Shared application-level TypeScript types

**test/**
- Shared testing utilities, fixtures, and setup

### 4. Feature Module Architecture

Organize business functionality by feature.

Example:

```text
features/
└── users/
    ├── components/
    │   ├── UserTable.tsx
    │   ├── UserForm.tsx
    │   └── UserFilters.tsx
    │
    ├── hooks/
    │   └── useUserFilters.ts
    │
    ├── server/
    │   ├── queries.ts
    │   └── actions.ts
    │
    ├── api/
    │   └── users.client.ts
    │
    ├── schemas/
    │   └── user.schema.ts
    │
    ├── types/
    │   └── user.types.ts
    │
    ├── utils/
    │   └── user.utils.ts
    │
    └── index.ts
```

Rules:

1. Keep business functionality inside its owning feature.
2. Do not place feature-specific components inside global component directories.
3. Do not place feature-specific hooks inside global hooks directories.
4. Do not import private implementation details from another feature.
5. Use explicit public exports where cross-feature access is required.
6. Avoid circular dependencies.
7. Keep modules independently understandable and testable.
8. Do not create unnecessary files or abstractions for small features.
9. Avoid giant feature entrypoints that re-export server and client code together.
10. Maintain explicit server/client entrypoints when necessary.

### 5. Server and Client Component Standards

**Server Components are the default.**

Do not add `"use client"` unless required.

Use Server Components for:
- Initial data fetching
- Server-side authorization
- SEO-relevant content
- Static and dynamic page rendering
- Server-side composition
- Access to server-only resources

Use Client Components for:
- Interactive UI
- Browser APIs
- Event handlers
- React state and effects
- Client-side context
- Browser-dependent libraries

Rules:

1. Keep client boundaries as small as practical.
2. Do not convert an entire page into a Client Component solely because one child requires interactivity.
3. Do not expose secrets to Client Components.
4. Do not import server-only modules into client code.
5. Pass serializable data across server/client boundaries.
6. Avoid unnecessary client-side fetching when data can be loaded efficiently on the server.
7. Do not introduce hydration mismatches.
8. Avoid using effects to derive values that can be computed during rendering.

### 6. Data Access and API Architecture

Use a dedicated Data Access Layer.

For applications using a separate NestJS backend, follow:

```text
Server Component
       |
       v
Feature Query / DAL
       |
       v
Typed HTTP Client
       |
       v
NestJS Backend
```

Requirements:

- Centralize HTTP client configuration.
- Keep endpoint-specific requests inside the owning feature.
- Validate external data at appropriate trust boundaries.
- Normalize API errors.
- Define explicit request and response types.
- Use appropriate request timeouts.
- Support request cancellation where applicable.
- Avoid duplicated network requests.
- Never expose private backend credentials.
- Do not trust client-provided authorization claims.

Use server-side requests for initial data when appropriate.

Use TanStack Query for interactive client-side server state when its caching, synchronization, pagination, or mutation capabilities are useful.

Do not automatically wrap every backend endpoint in a Next.js Route Handler.

Use a Backend-for-Frontend layer when it adds meaningful value, such as secure token handling, request aggregation, or backend isolation.

### 7. Server Actions and Mutations

Use Server Actions or Server Functions for appropriate server-side mutations.

Rules:

1. Validate every mutation input.
2. Authenticate and authorize every sensitive operation.
3. Never rely solely on hidden buttons or protected routes.
4. Return predictable validation errors.
5. Avoid exposing internal stack traces.
6. Revalidate affected cached data when necessary.
7. Redirect only after the required operation succeeds.
8. Prevent duplicate submissions where appropriate.
9. Use idempotency mechanisms for critical operations when required.
10. Do not use Server Actions as a universal replacement for REST APIs.

Server Actions must be treated as externally invocable server endpoints.

### 8. TypeScript Standards

Enable strict TypeScript checking.

Rules:

- Avoid `any`.
- Prefer `unknown` for untrusted values.
- Define clear types for public interfaces.
- Prefer type inference for obvious local values.
- Use discriminated unions for complex states.
- Use `import type` for type-only imports.
- Avoid unsafe type assertions.
- Avoid non-null assertions unless justified.
- Do not suppress TypeScript errors without documenting the reason.
- Use Zod or an equivalent validator for runtime validation when necessary.

Naming:

| Element | Convention |
|---|---|
| Components | PascalCase |
| Component files | PascalCase.tsx |
| Hooks | useSomething.ts |
| Variables | camelCase |
| Functions | camelCase |
| Types/interfaces | PascalCase |
| Constants | UPPER_SNAKE_CASE |
| API files | feature.api.ts |
| Schemas | feature.schema.ts |
| Tests | feature.test.ts(x) |

Prefer descriptive names over abbreviations.

### 9. React Component Standards

Follow these principles:

- One clear responsibility per component.
- Prefer composition over inheritance.
- Keep components focused and readable.
- Extract reusable logic into hooks when justified.
- Avoid deeply nested conditional rendering.
- Avoid unnecessary prop drilling.
- Avoid oversized components.
- Use stable keys for lists.
- Do not mutate props or state.
- Keep render functions pure.
- Use semantic HTML.
- Support keyboard navigation and accessibility.

Do not create custom hooks merely to move a few lines of straightforward code.

Do not use `useMemo`, `useCallback`, or `React.memo` automatically.

Apply performance optimizations when they solve a demonstrated or reasonably predictable problem.

### 10. State Management Standards

Separate state into categories.

**Local UI state**
Use React state.

Examples:
- Modal visibility
- Input state
- Tabs
- Small interaction states

**Shared client state**
Use Zustand or the project's established state manager when needed.

Examples:
- Global UI preferences
- Complex cross-component workflows
- Client-only application state

**Server state**
Use Server Components or TanStack Query depending on the interaction requirements.

**URL state**
Use route parameters and search parameters for shareable state.

Examples:
- Search queries
- Pagination
- Sorting
- Filters

Do not duplicate server data unnecessarily inside a global client store.

### 11. Forms and Validation

Prefer React Hook Form with Zod for complex interactive forms.

Requirements:

- Define explicit validation schemas.
- Validate on the server for all trusted mutations.
- Display useful field-level errors.
- Prevent duplicate submissions when necessary.
- Handle pending and failure states.
- Preserve user input after recoverable failures.
- Ensure form controls have accessible labels.
- Avoid duplicating validation logic unnecessarily.

Client-side validation improves usability but never replaces server-side validation.

### 12. Styling and Design System

Prefer Tailwind CSS and the project's established component library.

Rules:

1. Reuse design tokens.
2. Maintain consistent spacing and typography.
3. Support responsive layouts.
4. Support keyboard navigation.
5. Respect reduced-motion preferences.
6. Use accessible color contrast.
7. Avoid arbitrary one-off styling.
8. Prefer shared UI primitives.
9. Avoid duplicating existing components.
10. Keep visual consistency across features.

Use shadcn/ui or Radix primitives where appropriate, but do not introduce dependencies unnecessarily.

### 13. Authentication and Authorization

Security requirements:

- Keep sensitive tokens and secrets server-side.
- Prefer secure, HttpOnly cookies for session credentials.
- Use appropriate Secure and SameSite cookie attributes.
- Verify authorization at the data access or backend boundary.
- Enforce resource-level permissions.
- Protect against CSRF where applicable.
- Validate untrusted inputs.
- Prevent open redirects.
- Do not log credentials or sensitive tokens.
- Avoid exposing internal error details.

Proxy-level redirects are not sufficient authorization.

The backend must independently enforce permissions.

### 14. Error Handling

Implement consistent error handling.

Use:
- Route error boundaries
- Not-found handling
- Feature-level error states
- Typed or normalized API errors
- Appropriate logging
- Safe user-facing error messages

Do not silently swallow errors.

Do not expose stack traces or sensitive backend details to users.

Use error monitoring such as Sentry when configured.

### 15. Performance Standards

Optimize for real-world production performance.

Requirements:

- Minimize unnecessary Client Components.
- Avoid oversized JavaScript bundles.
- Use route-level code splitting.
- Dynamically import heavy interactive modules when appropriate.
- Optimize images with `next/image`.
- Optimize fonts with `next/font`.
- Avoid request waterfalls.
- Use pagination for large datasets.
- Virtualize large interactive lists when necessary.
- Choose caching behavior deliberately.
- Avoid caching personalized data across users.
- Measure performance before introducing complex optimizations.

Consider:
- Core Web Vitals
- Server response time
- Hydration cost
- Bundle size
- Database and API latency
- Rendering waterfalls

Do not assume every request should be cached.

Follow the installed Next.js version's caching semantics.

### 16. Testing Standards

Use Vitest and React Testing Library for appropriate unit and component tests.

Use Playwright for end-to-end tests.

Test:
- Business-critical functionality
- Validation
- Authorization
- API integration behavior
- Error handling
- Important user journeys
- Complex state transitions

Prefer testing observable behavior over implementation details.

Mock external dependencies at appropriate boundaries.

Do not create brittle tests that depend unnecessarily on internal component structure.

### 17. Code Quality and Formatting

Use:
- ESLint
- typescript-eslint
- Prettier
- TypeScript strict mode
- Automated CI checks

Enforce:
- No unused imports
- No unexplained unsafe types
- No circular feature dependencies
- No unnecessary console logging
- No hardcoded secrets
- Consistent import ordering
- Consistent naming conventions
- Consistent formatting

Do not disable lint rules merely to make code pass.

Fix the underlying problem whenever possible.

### 18. Observability and Logging

For production systems, support:

- Structured logging
- Request correlation IDs
- Error monitoring
- Performance monitoring
- Distributed tracing where applicable

Use OpenTelemetry, Sentry, or existing project tooling when appropriate.

Never log passwords, access tokens, or sensitive personal data.

Ensure logging does not create unnecessary performance overhead.

### 19. Code Review Standards

Before completing a task, review the implementation for:

1. Architectural consistency
2. Type safety
3. Correctness
4. Security vulnerabilities
5. Authorization gaps
6. Unnecessary re-renders
7. Request waterfalls
8. Error handling
9. Accessibility
10. Test coverage
11. Duplicate logic
12. Unnecessary dependencies
13. Breaking changes
14. Unintended modifications

Do not consider code complete merely because it compiles.

### 20. AI Agent Implementation Workflow

For every development task:

**Step 1: Understand**

- Analyze the request.
- Inspect relevant existing code.
- Identify affected modules.
- Identify technical constraints.
- Clarify only genuinely blocking ambiguities.

**Step 2: Plan**

For nontrivial tasks, prepare a concise implementation plan.

Identify:
- Files to create
- Files to modify
- Dependencies
- Data flow
- Security considerations
- Testing requirements

**Step 3: Implement**

- Follow existing conventions.
- Keep changes focused.
- Reuse existing infrastructure.
- Preserve architectural boundaries.
- Avoid unrelated refactoring.
- Implement complete functional behavior.
- Do not introduce placeholder implementations unless explicitly requested.

**Step 4: Validate**

Run the available project checks:

```bash
npm run lint
npx tsc --noEmit
npm run test -- --run
npm run build
```

Adapt commands to the project's package manager and scripts.

Do not claim tests passed unless they were actually executed successfully.

**Step 5: Review**

Inspect the final diff.

Verify:
- No accidental file modifications
- No sensitive information exposed
- No architectural violations
- No unnecessary complexity
- No unhandled critical edge cases

**Step 6: Report**

Provide a concise summary containing:

- What was implemented
- Important architectural decisions
- Files changed
- Validation performed
- Known limitations
- Any remaining work

### 21. Prohibited Practices

Never:

- Put all business logic inside `page.tsx`.
- Make every component a Client Component.
- Fetch the same data unnecessarily in multiple layers.
- Store sensitive credentials in client-accessible code.
- Trust client-side authorization alone.
- Create giant global utility files.
- Introduce circular feature dependencies.
- Use `any` to bypass typing problems.
- Suppress errors without justification.
- Duplicate existing functionality.
- Install unnecessary packages.
- Modify unrelated features without justification.
- Claim unexecuted tests have passed.
- Replace established architecture without assessing migration impact.
- Add speculative abstractions for hypothetical future requirements.

### 22. Final Engineering Principle

Build applications that are easy to understand, extend, debug, test, secure, and operate.

The objective is not to create the maximum number of architectural layers.

The objective is to establish clear boundaries and maintainable code while delivering correct functionality.

**Every implementation must balance correctness, simplicity, security, performance, and long-term maintainability.**
