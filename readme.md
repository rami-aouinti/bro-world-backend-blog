# Bro World Backend

## Project Overview
Bro World Backend powers the Bro World blog and community experience. It exposes a JSON REST API built with Symfony 7 that covers blog creation and moderation, post publishing, audience engagement (likes, comments, reactions), and operational insights such as per-month statistics. The service follows a layered domain-driven design where transport controllers broker traffic to application resources and domain repositories, keeping business logic isolated from framework concerns.

Key capabilities include:
- CRUD endpoints for managing blogs and posts, backed by DTO-driven validation and reusable REST action traits.
- Interaction workflows for visitors through comment and like resources, complete with messaging pipelines for notifications and search indexing.
- Cached statistics endpoints that aggregate activity across the platform and serve low-latency analytics to authenticated consumers.

## Tech Stack
The application ships as a containerized environment orchestrated with Docker Compose. Major components are:
- **Symfony 7 + PHP 8.4 FPM** for the API runtime and background workers.
- **Nginx** as the HTTP entry point.
- **MySQL 8** for relational persistence.
- **Redis** for caching, locks, and queues.
- **RabbitMQ 4** for asynchronous messaging.
- **Elasticsearch 7 + Kibana** for search indexing and observability dashboards.
- **Mailpit** for capturing outbound email in development.

Supporting tools include PHPUnit, Easy Coding Standard, PHPStan, PHP Insights, Rector, PhpMetrics, PhpMD, PhpCPD, Composer QA utilities, and Qodana configuration for deeper analysis.

## Environment Configuration
The `compose.yaml`, `compose-staging.yaml`, `compose-prod.yaml`, and `compose-test-ci.yaml` files define isolated stacks for local development, staging mirroring, production-ready simulations, and CI/testing respectively. The Makefile wraps the Docker orchestration with environment-specific targets:

| Stage | Build | Start | Stop | Tear Down |
| --- | --- | --- | --- | --- |
| Development | `make build` | `make start` | `make stop` | `make down` |
| Testing/CI | `make build-test` | `make start-test` | `make stop-test` | `make down-test` |
| Staging | `make build-staging` | `make start-staging` | `make stop-staging` | `make down-staging` |
| Production | `make build-prod` | `make start-prod` | `make stop-prod` | `make down-prod` |

Other helpful targets:
- `make generate-jwt-keys` to provision JWT key pairs for authentication.
- `make messenger-setup-transports`, `make create-roles-groups`, `make migrate`, and `make migrate-cron-jobs` to prepare databases, background jobs, and security ACLs.
- `make ssh`, `make ssh-nginx`, `make ssh-mysql`, etc. to open shells inside running containers.
- `make logs-*` to stream service logs from the host.

Application secrets, port mappings, feature toggles, and Docker arguments are centralized in `.env`, with overrides available per environment through `.env.local`, `.env.staging`, and `.env.prod`. Adjust database credentials, message broker users, Elasticsearch credentials, Redis ports, mailers, and JWT settings before promoting an environment.

### Local Services
Once the development stack is running you can reach supporting UIs at:
- Swagger UI: http://localhost/api/doc
- RabbitMQ management: http://localhost:15672
- Kibana: http://localhost:5601
- Mailpit: http://localhost:8025

## Running Tests & Quality Gates
Execute the full PHPUnit suite from the host with:
```bash
make phpunit
```

Supplementary quality tooling is available through dedicated targets:
- Static analysis: `make phpstan`
- Coding standards: `make ecs` (fixable violations via `make ecs-fix`) and `make phpcs`
- Architecture metrics: `make phpmetrics`
- Code smells: `make phpmd`
- Duplicate detection: `make phpcpd` / `make phpcpd-html-report`
- Dependency hygiene: `make composer-normalize`, `make composer-validate`, `make composer-unused`, `make composer-require-checker`
- Holistic insights: `make phpinsights`

Refer to `make help` for a full catalog of automation commands.

## API Usage
All API routes are served beneath `/api` with versioned prefixes. Highlights include:
- `/api/v1/blog` for blog administration (create, update, patch, list, fetch by id, id collection, counts).
- `/api/v1/post` for post lifecycle management with similar CRUD semantics.
- `/api/v1/statistics` for cached per-month aggregates of posts, blogs, likes, and comments.

The platform uses JWT bearer tokens. Generate keys via `make generate-jwt-keys`, configure issuers/clients to request tokens, and send authenticated requests with the `Authorization: Bearer <token>` header. Anonymous access is limited to explicitly whitelisted public routes.

OpenAPI documentation is generated through NelmioApiDocBundle and exposed in the Swagger UI, making it simple to explore payload schemas, available query parameters, and authentication requirements.

## Contribution Guidelines
- Follow PSR-12 and Symfony best practices, applying strict types and rich domain models.
- Keep transport (controllers, subscribers, handlers), application (resources, services), infrastructure (repositories), and domain (entities, messages) layers decoupled and testable.
- Accompany features with application, integration, and unit tests. Target automation coverage before opening pull requests.
- Run `make ecs`, `make phpstan`, and `make phpunit` locally to catch regressions early, then use additional QA targets as needed.
- Document non-trivial workflows or architectural decisions in the `docs/` directory and update the Swagger schema for new endpoints.

## Deployment Considerations
- Prepare environment-specific overrides in `.env.prod` or `.env.staging` for secrets, database endpoints, queues, and cache backends.
- Use `make env-prod` or `make env-staging` to compile cached Symfony configuration (`.env.local.php`).
- Build immutable images with `make build-prod` (or staging equivalent) before pushing to registries; then orchestrate with the matching Compose file or translate settings into your target infrastructure (Kubernetes, ECS, etc.).
- Initialize production data stores with the migration and setup targets (`make migrate-no-test`, `make messenger-setup-transports`, `make create-roles-groups`, etc.).
- Monitor asynchronous workloads (messenger consumers) by running the Supervisord container or provisioning equivalent workers in your platform.
- Review Elasticsearch license options and adjust `docker/elasticsearch/config/elasticsearch.yml` if you need trial-only features before shipping.

## Getting Support
For deeper dives, see the topic guides in `docs/` (development workflow, testing, Postman collections, messenger usage, Swagger, IDE integration) and leverage `make help` to inspect the automation surface area.
